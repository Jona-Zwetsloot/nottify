<?php
// Upload a track. Returns JSON.
require_once '../config.php';
header('Content-type: application/json');

if (array_key_exists('change_metadata_enabled', $config) && !$config['change_metadata_enabled']) {
    exit(json_encode(['error' => text('edits_disabled')]));
}

if (!file_exists('../library') || !file_exists('../library/tracks.json') || !file_exists('../library/albums.json')) {
    exit(json_encode(['error' => text('required_files_missing')]));
}

$content = file_get_contents('../library/albums.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => str_replace('<file>', 'albums.json', text('invalid_json'))]));
}
$albums = json_decode($content, true);

$albumId = uniqid(rand());

$albums[$albumId] = [
    'name' => '',
    'picture' => null,
    'tracks' => [],
    'artists' => [],
    'added' => time(),
    'version' => 1,
];

writeFile('../library/albums.json', json_encode($albums));

// Output
exit(json_encode([
    'album_key' => $albumId,
    'album' => $albums[$albumId],
]));
