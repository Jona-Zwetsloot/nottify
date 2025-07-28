<?php
require_once 'config.php';

// Save recent albums
if (!file_exists('library') || !file_exists('library/data.json')) {
    exit(json_encode(['error' => 'Required files do not exist yet. Please visit the index page first to generate the required files.']));
}

$content = file_get_contents('library/data.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid data.json']));
}
$data = json_decode($content, true);

if ($_GET['album'] && is_string($_GET['album'])) {
    array_unshift($data['recent_albums'], $_GET['album']);
    $data['recent_albums'] = array_unique($data['recent_albums']);
    if (count($data['recent_albums']) > 10) {
        $data['recent_albums'] = array_slice($data['recent_albums'], 0, 10);
    }
}
write_file('library/data.json', json_encode($data));