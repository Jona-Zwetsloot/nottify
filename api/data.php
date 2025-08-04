<?php
// Save some activity data, which is used for statistics. Returns JSON.
require_once '../config.php';
header('Content-type: application/json');

// Save recent albums
if (!file_exists('../library') || !file_exists('../library/data.json')) {
    exit(json_encode(['error' => 'Required files do not exist yet. Please visit the index page first to generate the required files.']));
}

$content = file_get_contents('../library/data.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid data.json']));
}
$data = json_decode($content, true);

if (isset($_GET['track']) && is_string($_GET['track'])) {
    if (!isset($data['listened_tracks'])) {
        $data['listened_tracks'] = [];
    }
    array_unshift($data['listened_tracks'], [
        'time' => time(),
        'track' => $_GET['track'],
        'duration' => (isset($_GET['duration']) && is_float((float)$_GET['duration']) && $_GET['duration'] >= 0) ? (float)$_GET['duration'] : null,
        'album' => !empty($_GET['album']) ? $_GET['album'] : null,
        'hour' => (isset($_GET['hour']) && is_int((int)$_GET['hour']) && $_GET['hour'] >= 0 && $_GET['hour'] < 24) ? $_GET['hour'] : null,
        'month' => (isset($_GET['month']) && is_int((int)$_GET['month']) && $_GET['month'] >= 0 && $_GET['month'] < 12) ? $_GET['month'] : null,
    ]);
    if (count($data['listened_tracks']) > 100000) {
        $data['listened_tracks'] = array_slice($data['listened_tracks'], 0, 100000);
    }
} else if (isset($_GET['album']) && is_string($_GET['album'])) {
    array_unshift($data['recent_albums'], $_GET['album']);
    $data['recent_albums'] = array_unique($data['recent_albums']);
    if (count($data['recent_albums']) > 10) {
        $data['recent_albums'] = array_slice($data['recent_albums'], 0, 10);
    }
} else {
    exit(json_encode(['error' => 'Data is missing']));
}

write_file('../library/data.json', json_encode($data));

exit(json_encode(['status' => 'success']));
