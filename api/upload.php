<?php
// Upload a track. Returns JSON.
require_once '../config.php';
header('Content-type: application/json');

if (array_key_exists('uploads_enabled', $config) && !$config['uploads_enabled']) {
    exit(json_encode(['error' => text('uploads_disabled')]));
}

if (!file_exists('../library') || !file_exists('../library/tracks.json') || !file_exists('../library/albums.json')) {
    exit(json_encode(['error' => text('required_files_missing')]));
    exit(json_encode(['error' => text('required_files_missing')]));
}

// INPUT SANITIZATION

// Check if required parameters are set
if (empty($_POST['meta']) || !json_validate($_POST['meta']) || empty($_POST['format']) || !json_validate($_POST['format']) || empty($_FILES['file'])) {
    exit(json_encode(['error' => text('invalid_request')]));
}

// Check if file is audio file
if (extension_loaded('fileinfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['file']['tmp_name']);
} else {
    exit(json_encode(['error' => text('finfo_missing')]));
}
if (!str_starts_with($mimeType, 'audio/') && $mimeType != 'application/octet-stream') {
    exit(json_encode(['filetype_not_supported' => str_replace('<type>', $mimeType, text('filetype_not_supported'))]));
}

// Check if multiple files are given
if (is_array($_FILES['file']['error'])) {
    exit(json_encode(['error' => text('multiple_files_not_supported')]));
}

// Check if file is corrupt
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(['error' => text('file_corrupt')]));
}

// Check if the track is a duplicate and should be disallowed
$content = file_get_contents('../library/tracks.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => str_replace('<file>', 'tracks.json', text('invalid_json'))]));
}
$tracks = json_decode($content, true);
$meta = json_decode($_POST['meta'], true);
$format = json_decode($_POST['format'], true);


// UPLOADING

// Place everything neatly organised in subfolder
$folderName = trim(preg_replace($config['folder_regex'], '', isset($meta['album']) ? $meta['album'] : 'default'));
$displayDir = 'library/' . (empty($folderName) ? 'default' : $folderName);
$dir = '../' . $displayDir;
$name = trim(preg_replace($config['folder_regex'], '', pathinfo($_FILES['file']['name'], PATHINFO_FILENAME)));
if (empty($name)) {
    $name = uniqid(rand());
}
$ext = empty(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) ? '' : '.' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

// Make sure file with generated name does not exist
while (file_exists($dir . '/' . $name . $ext)) {
    if (isset($meta['artist']) && !empty(preg_replace($config['folder_regex'], '', $meta['artist']))) {
        $name .= preg_replace($config['folder_regex'], '', $meta['artist']);
    } else {
        $name .= uniqid(rand());
    }
}

// Create folder if it does not exist
if (!file_exists($dir)) {
    try {
        mkdir($dir, 0777, true);
    } catch (Exception $e) {
        exit(json_encode(['error' => str_replace('<dir>', $dir, text('could_not_create_directory'))]));
    }
}

// Save audio file
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $name . $ext)) {
    exit(json_encode(['error' => str_replace('<file>', $name . $ext, text('could_not_save_file'))]));
}

if (empty($meta['title'])) {
    $meta['title'] = $_FILES['file']['name'];
}

$trackId = uniqid(rand());

$tracks[$trackId] = [
    'name' => $displayDir . '/' . $name . $ext,
    'added' => time(),
    'pictures' => [],
    'meta' => $meta,
    'format' => $format,
    'size' => filesize($dir . '/' . $name . $ext),
];

// Save pictures in metadata to filesystem
if (isset($_POST['picture']) && is_array($_POST['picture'])) {
    $i = 0;
    foreach ($_POST['picture'] as $picture) {
        $content = base64_decode($picture);

        // Get the MIME type of the image
        if (extension_loaded('fileinfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($content);
        } else {
            exit(json_encode(['error' => text('finfo_missing')]));
        }

        // Validate MIME type (only allow images)
        if (!str_starts_with($mimeType, 'image/')) {
            continue;
        }

        $ext = mimeToFileExtension($mimeType);

        $filename = $displayDir . '/' . $name . ($i == 0 ? '' : $i + 1) . '.' . $ext;
        writeFile('../' . $filename, $content);
        array_push($tracks[$trackId]['pictures'], [
            'url' => $filename,
            'mime' => $mimeType,
            'version' => 1,
        ]);
        $i++;
    }
}

// Sound normalization
if (isset($_POST['gain']) && is_float((float)$_POST['gain']) && $_POST['gain'] >= 0.02 && $_POST['gain'] <= 20) {
    $tracks[$trackId]['gain'] = (float)$_POST['gain'];
}

// Add to library
writeFile('../library/tracks.json', json_encode($tracks));

// Add or update album data
$content = file_get_contents('../library/albums.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => str_replace('<file>', 'albums.json', text('invalid_json'))]));
}
$albums = json_decode($content, true);
$albumName = isset($meta['album']) ? $meta['album'] : '';
foreach ($albums as $key => $value) {
    if ($albums[$key]['name'] != null && $albums[$key]['name'] == $albumName) {
        $albumId = $key;
        break;
    }
}
if (empty($albumId)) {
    $albumId = uniqid(rand());
}

if (array_key_exists($albumId, $albums)) {
    if ($albums[$albumId]['picture'] == null && count($tracks[$trackId]['pictures']) != 0) {
        $albums[$albumId]['picture'] = $tracks[$trackId]['pictures'][0];
        $albums[$albumId]['picture']['track'] = true;
    }
    array_push($albums[$albumId]['tracks'], $trackId);
} else {
    $albums[$albumId] = [
        'name' => isset($meta['album']) ? $meta['album'] : '',
        'picture' => count($tracks[$trackId]['pictures']) == 0 ? null : $tracks[$trackId]['pictures'][0],
        'tracks' => [$trackId],
        'artists' => [],
        'added' => time(),
        'version' => 1,
    ];
    if (count($tracks[$trackId]['pictures']) != 0) {
        $albums[$albumId]['picture']['track'] = true;
    }
}
if (isset($meta['artists'])) {
    foreach ($meta['artists'] as $artist) {
        if (!in_array($artist, $albums[$albumId]['artists'])) {
            array_push($albums[$albumId]['artists'], $artist);
        }
    }
} else if (isset($meta['artist'])) {
    if (!in_array($meta['artist'], $albums[$albumId]['artists'])) {
        array_push($albums[$albumId]['artists'], $meta['artist']);
    }
}

writeFile('../library/albums.json', json_encode($albums));

// Output
exit(json_encode([
    'track_key' => $trackId,
    'track' => $tracks[$trackId],
    'album_key' => $albumId,
    'album' => $albums[$albumId],
]));
