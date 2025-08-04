<?php
// Proxy audio stream, is used for radio streaming. Returns audio stream.
require_once '../config.php';

header('Content-type: application/json');
if (array_key_exists('change_metadata_enabled', $config) && !$config['change_metadata_enabled']) {
    exit(json_encode(['error' => 'Invalid request. Users are not allowed to change metadata.']));
}

if (!file_exists('../library') || !file_exists('../library/tracks.json') || !file_exists('../library/albums.json')) {
    exit(json_encode(['error' => 'Required files do not exist yet. Please visit the index page first to generate the required files.']));
}

// Edit album
if (!empty($_POST['album'])) {
    $content = file_get_contents('../library/albums.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid albums.json']));
    }
    $albums = json_decode($content, true);
    if ($_POST['album'] == 'disconnected') {
        if (empty($albums['disconnected'])) {
            $albums['disconnected'] = [
                'name' => 'Disconnected',
                'picture' => [
                    'url' => 'svg/disconnected.svg',
                    'mime' => 'image\/svg',
                    'version' => 1,
                    'track' => false
                ],
                'tracks' => [],
                'artists' => [],
                'added' => time(),
                'version' => 1
            ];
        }
    }
    if (!array_key_exists($_POST['album'], $albums)) {
        exit(json_encode(['error' => 'Album could not be found']));
    }
    // Update album name
    if (isset($_POST['name'])) {
        $albums[$_POST['album']]['name'] = $_POST['name'];
        write_file('../library/albums.json', json_encode($albums));
        exit(json_encode(['status' => 'success']));
    }
    if (isset($_POST['hide'])) {
        $albums[$_POST['album']]['hide'] = $_POST['hide'] == 'true';
        write_file('../library/albums.json', json_encode($albums));
        exit(json_encode(['status' => 'success']));
    }
    // Update album image
    else if (!empty($_FILES['file'])) {
        // Get the MIME type of the image
        if (extension_loaded('fileinfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['file']['tmp_name']);
        } else {
            exit(json_encode(['error' => 'Please install the fileinfo PHP extension']));
        }

        // Validate MIME type (only allow images)
        if (!str_starts_with($mimeType, 'image/')) {
            exit(json_encode(['error' => 'Filetype "' . $mimeType . '" is not supported. Make sure you upload an image.']));
        }

        // Delete old file if it does not belong to a track
        if (!empty($albums[$_POST['album']]['picture']) && !$albums[$_POST['album']]['picture']['track'] && str_starts_with($albums[$_POST['album']]['picture']['url'], 'library/') && file_exists($albums[$_POST['album']]['picture']['url'])) {
            unlink($albums[$_POST['album']]['picture']['url']);
        }

        // Generate name
        $name = preg_replace($config['folder_regex'], '', pathinfo($albums[$_POST['album']]['name'], PATHINFO_FILENAME));
        if (empty($name)) {
            $name = uniqid(rand());
        }
        $ext = '.' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

        // Make sure file with generated name does not exist
        while (file_exists('../library/' . $name . $ext)) {
            $name .= uniqid(rand());
        }

        // Save file
        if (!move_uploaded_file($_FILES['file']['tmp_name'], '../library/' . $name . $ext)) {
            exit(json_encode(['error' => 'Server error']));
        }

        $albums[$_POST['album']]['picture'] = [
            'url' => 'library/' . $name . $ext,
            'mime' => $mimeType,
            'track' => false,
            'version' => $albums[$_POST['album']]['picture']['version'] + 1,
        ];

        write_file('../library/albums.json', json_encode($albums));
        exit(json_encode(['picture' => $albums[$_POST['album']]['picture']]));
    } else if (isset($_POST['tracks']) && is_array($_POST['tracks'])) {
        $content = file_get_contents('../library/tracks.json', true);
        if (!json_validate($content)) {
            exit(json_encode(['error' => 'Invalid tracks.json']));
        }
        $tracks = json_decode($content, true);
        $albums[$_POST['album']]['tracks'] = [];
        foreach ($_POST['tracks'] as $track) {
            if (array_key_exists($track, $tracks)) {
                array_push($albums[$_POST['album']]['tracks'], $track);
            }
        }
        write_file('../library/albums.json', json_encode($albums));
        exit(json_encode(['status' => 'success']));
    }
}

// Edit track
if (!empty($_POST['track'])) {
    $content = file_get_contents('../library/tracks.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid tracks.json']));
    }
    $tracks = json_decode($content, true);
    if (!array_key_exists($_POST['track'], $tracks)) {
        exit(json_encode(['error' => 'Track could not be found']));
    }
    // Update track image
    if (!empty($_FILES['file'])) {
        // Get the MIME type of the image
        if (extension_loaded('fileinfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['file']['tmp_name']);
        } else {
            exit(json_encode(['error' => 'Please install the fileinfo PHP extension']));
        }

        // Validate MIME type (only allow images)
        if (!str_starts_with($mimeType, 'image/')) {
            return;
        }

        if (empty($tracks[$_POST['track']]['pictures'])) {
            $tracks[$_POST['track']]['pictures'] = [];
        }

        $ext = mime2ext($mimeType);
        if (str_contains($tracks[$_POST['track']]['name'], '.')) {
            $filename = preg_replace('/\.[^.]+?$/', '.' . $ext, $tracks[$_POST['track']]['name']);
        } else {
            $filename = $tracks[$_POST['track']]['name'] . '.' . $ext;
        }
        if (file_exists('../' . $filename)) {
            unlink('../' . $filename);
        }

        if (!move_uploaded_file($_FILES['file']['tmp_name'], '../' . $filename)) {
            exit(json_encode(['error' => 'Server error']));
        }

        $tracks[$_POST['track']]['pictures'][0] = [
            'url' => $filename,
            'mime' => $mimeType,
            'version' => count($tracks[$_POST['track']]['pictures']) == 0 ? 1 : $tracks[$_POST['track']]['pictures'][0]['version'] + 1,
        ];

        write_file('../library/tracks.json', json_encode($tracks));
        exit(json_encode(['picture' => $tracks[$_POST['track']]['pictures'][0]]));
    }
    // Update track metadata
    else if (!empty($_POST['json']) && json_validate($_POST['json'])) {
        $data = json_decode($_POST['json'], true);

        if (!isset($data['name'], $data['added'], $data['pictures'], $data['meta'], $data['format'], $data['size']) || !is_string($data['name']) || !is_int($data['added']) || !is_array($data['pictures']) || !is_array($data['meta']) || !is_array($data['format']) || !is_int($data['size'])) {
            exit(json_encode(['error' => 'Invalid track data.']));
        }
        $tracks[$_POST['track']] = $data;
        if (isset($tracks[$_POST['track']]) && isset($tracks[$_POST['track']]['meta']) && isset($tracks[$_POST['track']]['meta']['artists'])) {
            unset($tracks[$_POST['track']]['meta']['artists']);
        }
        write_file('../library/tracks.json', json_encode($tracks));

        $content = file_get_contents('../library/albums.json', true);
        if (!json_validate($content)) {
            exit(json_encode(['error' => 'Invalid albums.json']));
        }
        $albums = json_decode($content, true);
        foreach ($albums as $key => $value) {
            $albums[$key]['artists'] = [];
            foreach ($albums[$key]['tracks'] as $track) {
                if (isset($tracks[$track]) && isset($tracks[$track]['meta']) && !empty($tracks[$track]['meta']['artist'])) {
                    $artists = explode(', ', $tracks[$track]['meta']['artist']);
                    foreach ($artists as $artist) {
                        if (!in_array($artist, $albums[$key]['artists'])) {
                            array_push($albums[$key]['artists'], $artist);
                        }
                    }
                }
            }
        }
        write_file('../library/albums.json', json_encode($albums));

        exit(json_encode(['status' => 'success']));
    }
}
