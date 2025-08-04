<?php
// Upload a track. Returns JSON.
require_once '../config.php';
header('Content-type: application/json');

if (array_key_exists('change_metadata_enabled', $config) && !$config['change_metadata_enabled']) {
    exit(json_encode(['error' => 'Invalid request. Edits are disabled.']));
}

if (!file_exists('../library') || !file_exists('../library/tracks.json') || !file_exists('../library/albums.json')) {
    exit(json_encode(['error' => 'Required files do not exist yet. Please visit the index page first to generate the required files.']));
}

$content = file_get_contents('../library/albums.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid albums.json']));
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

write_file('../library/albums.json', json_encode($albums));

// Output
exit(json_encode([
    'album_key' => $albumId,
    'album' => $albums[$albumId],
]));
