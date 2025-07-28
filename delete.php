<?php
require_once 'config.php';
if (array_key_exists('delete_enabled', $config) && !$config['delete_enabled']) {
    exit(json_encode(['error' => 'Invalid request. Users are not allowed to change metadata.']));
}

if (!file_exists('library') || !file_exists('library/tracks.json') || !file_exists('library/albums.json')) {
    exit(json_encode(['error' => 'Required files do not exist yet. Please visit the index page first to generate the required files.']));
}

function directoryEmpty($dir)
{
    if (!file_exists($dir)) {
        return false;
    }
    $handle = opendir($dir);
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            closedir($handle);
            return false;
        }
    }
    closedir($handle);
    return true;
}

function removeTrack($id)
{
    global $tracks, $config;
    if (!array_key_exists($id, $tracks)) {
        exit(json_encode(['error' => 'Track could not be found']));
    }

    // Check if file exists and is in allowed directory before deleting it
    // Also make sure the associated pictures are deleted
    $track = explode('/', $tracks[$id]['name']);
    if (file_exists($tracks[$id]['name']) && str_starts_with($tracks[$id]['name'], 'library/')) {
        unlink($tracks[$id]['name']);
    }
    if (count($track) >= 2 && directoryEmpty('library/' . preg_replace($config['folder_regex'], '', $track[1]))) {
        rmdir('library/' . preg_replace($config['folder_regex'], '', $track[1]));
    }


    foreach ($tracks[$id]['pictures'] as $picture) {
        if (str_starts_with($picture['url'], 'library/') && file_exists($picture['url'])) {
            unlink($picture['url']);
        }
    }
    if (!empty($tracks[$id]['lyrics']['url']) && str_starts_with($tracks[$id]['lyrics']['url'], 'library/') && file_exists($tracks[$id]['lyrics']['url'])) {
        unlink($tracks[$id]['lyrics']['url']);
    }

    unset($tracks[$id]);
}

// Remove album
if (!empty($_POST['album'])) {
    $json = json_decode(file_get_contents('library/albums.json', true), true);

    if (array_key_exists($_POST['album'], $json)) {
        if (isset($_POST['permanent']) && $_POST['permanent'] == 'true') {
            $content = file_get_contents('library/tracks.json', true);
            if (!json_validate($content)) {
                exit(json_encode(['error' => 'Invalid tracks.json']));
            }
            $tracks = json_decode($content, true);
            foreach ($json[$_POST['album']]['tracks'] as $track) {
                removeTrack($track);
            }
            write_file('library/tracks.json', json_encode($tracks));
        }
        unset($json[$_POST['album']]);

        if (count($json) == 0) {
            unlink('library/albums.json');
        } else {
            write_file('library/albums.json', json_encode($json));
        }
    }
}

// Remove track
if (!empty($_POST['track'])) {
    $content = file_get_contents('library/tracks.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid tracks.json']));
    }
    $tracks = json_decode($content, true);

    removeTrack($_POST['track']);

    if (count($tracks) == 0) {
        unlink('library/tracks.json');
    } else {
        write_file('library/tracks.json', json_encode($tracks));
    }

    $content = file_get_contents('library/albums.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid albums.json']));
    }
    $albums = json_decode($content, true);
    foreach ($albums as $key => $value) {
        $albums[$key]['tracks'] = array_values(array_diff($albums[$key]['tracks'], [$_POST['track']]));
    }
    write_file('library/albums.json', json_encode($albums));
}
exit(json_encode(['status' => 'success']));
