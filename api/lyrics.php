<?php
// Upload or delete lyrics. Returns JSON.
require_once '../config.php';
header('Content-type: application/json');

if (array_key_exists('uploads_enabled', $config) && !$config['uploads_enabled']) {
    exit(json_encode(['error' => text('uploads_disabled')]));
}

if (!file_exists('../library')) {
    mkdir('../library', 0777, true);
}
if (!file_exists('../library/tracks.json')) {
    writeFile('../library/tracks.json', '[]');
}

// Check if required parameters are set
if (empty($_POST['track'])) {
    exit(json_encode(['error' => text('invalid_request')]));
}

$content = file_get_contents('../library/tracks.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => str_replace('<file>', 'tracks.json', text('invalid_json'))]));
}
$tracks = json_decode($content, true);
if (!array_key_exists($_POST['track'], $tracks)) {
    exit(json_encode(['error' => text('track_not_found')]));
}

if (str_starts_with($tracks[$_POST['track']]['name'], 'library/')) {
    if (str_contains($tracks[$_POST['track']]['name'], '.')) {
        $filename = preg_replace('/[^.]+?$/', 'lrc', $tracks[$_POST['track']]['name']) . '.lrc';
    } else {
        $filename = $tracks[$_POST['track']]['name'] . '.lrc';
    }
} else {
    if (!file_exists('../library/spotify')) {
        mkdir('../library/spotify', 0777, true);
    }
    $filename = 'library/spotify/' . preg_replace($config['folder_regex'], '', $tracks[$_POST['track']]['name']) . '.lrc';
}
if (file_exists('../' . $filename)) {
    unlink('../' . $filename);
}
if (empty($_POST['lyrics'])) {
    unset($tracks[$_POST['track']]['lyrics']);
    writeFile('../library/tracks.json', json_encode($tracks));

    exit(json_encode(['status' => 'success']));
} else {
    writeFile('../' . $filename, $_POST['lyrics']);

    $tracks[$_POST['track']]['lyrics'] = [
        'url' => $filename,
        'version' => empty($tracks[$_POST['track']]['lyrics']) ? 1 : $tracks[$_POST['track']]['lyrics']['version'] + 1,
    ];
    writeFile('../library/tracks.json', json_encode($tracks));

    // Output
    exit(json_encode($tracks[$_POST['track']]['lyrics']));
}
