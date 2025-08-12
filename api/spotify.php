<?php
// Request user Spotify library in small steps
require_once '../config.php';
header('Content-type: application/json');

// PHP does not support async functions, which is not nice when you need to make a lot of API calls
// The simplest way to prevent the page from being stuck in loading state for an hour is to make multiple smaller requests
// This way we can also display a progress bar, although it is wildly inaccurate (some steps will take a lot longer than others)
$stepCount = 8;

$spotifyEnabled = isset($spotify, $spotify['enabled'], $spotify['client_id'], $spotify['client_secret'], $spotify['redirect_uri']) && $spotify['enabled'] && !empty($_SESSION['spotify_token']) && isset($_SESSION['spotify_progress']) && is_int((int)$_SESSION['spotify_progress']) && $_SESSION['spotify_progress'] >= 0 && $_SESSION['spotify_progress'] < $stepCount;
if (!$spotifyEnabled) {
    exit(json_encode(['error' => 'Not authorized.']));
}

// Refresh token if needed
require_once 'refresh-token.php';

function getSmallestImage($images)
{
    $smallest = PHP_INT_MAX;
    $smallestImage = null;
    foreach ($images as $image) {
        if (!isset($image['height'], $image['width'], $image['url'])) {
            continue;
        }
        $pixels = $image['height'] * $image['width'];
        if ($pixels < $smallest) {
            $smallest = $pixels;
            $smallestImage = $image['url'];
        }
    }
    return $smallestImage;
}

// Load library for specific steps
$loadLibrary = [2, 4, 5, 7];
$currentStep = $_SESSION['spotify_progress'];
if (in_array($currentStep, $loadLibrary)) {
    $content = file_get_contents('../library/albums.json', true);
    if (!json_validate($content)) {
        exitMessage('error', str_replace('<file>', 'albums.json', text('invalid_json')));
    }
    $albums = json_decode($content, true);

    $content = file_get_contents('../library/tracks.json', true);
    if (!json_validate($content)) {
        exitMessage('error', str_replace('<file>', 'tracks.json', text('invalid_json')));
    }
    $tracks = json_decode($content, true);
}

$next = '';
$progress = 0;
$image = null;
$trackCount = 0;
$playlistCount = 0;
$albumCount = 0;
// Request profile data
if ($_SESSION['spotify_progress'] == 0) {
    $json = requestURL('https://api.spotify.com/v1/me', 'spotify');
    $next = text('importing_spotify_1');
    $progress = 5;
    if (isset($json, $json['images'], $json['images'][0], $json['images'][0]['url'])) {
        $image = $json['images'][0]['url'];
    }
}
// Request playlists in user library
else if ($_SESSION['spotify_progress'] == 1) {
    $json = requestURL('https://api.spotify.com/v1/me/playlists?offset=0&limit=50', 'spotify');
    for ($i = 0; $i < floor($json['total'] / 50); $i++) {
        requestURL('https://api.spotify.com/v1/me/playlists?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
    }
    $next = text('importing_spotify_2');
    $progress = 10;
    if (isset($json, $json['items'], $json['items'][0], $json['items'][0]['images'])) {
        $image = getSmallestImage($json['items'][0]['images']);
    }
}
// Request tracks for each playlist in user library
else if ($_SESSION['spotify_progress'] == 2) {
    $json = requestURL('https://api.spotify.com/v1/me/playlists?offset=0&limit=50', 'spotify');
    addAlbums($json);
    for ($i = 0; $i < floor($json['total'] / 50); $i++) {
        $json = requestURL('https://api.spotify.com/v1/me/playlists?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
        addAlbums($json);
    }
    $next = str_replace('<count>', $json['total'], text('importing_spotify_3'));
    $progress = 30;
    if (isset($json, $json['items'], $json['items'][0])) {
        $random = array_rand($json['items']);
        if (isset($json['items'][$random]['images'])) {
            $image = getSmallestImage($json['items'][$random]['images']);
        }
    }
}
// Request albums in user library
else if ($_SESSION['spotify_progress'] == 3) {
    $json = requestURL('https://api.spotify.com/v1/me/albums?offset=0&limit=50', 'spotify');
    for ($i = 0; $i < floor($json['total'] / 50); $i++) {
        requestURL('https://api.spotify.com/v1/me/albums?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
    }
    $next = text('importing_spotify_4');
    $progress = 35;
    if (isset($json, $json['items'], $json['items'][0], $json['items'][0]['album'], $json['items'][0]['album']['images'])) {
        $image = getSmallestImage($json['items'][0]['album']['images']);
    }
}
// Request tracks for each album in user library
else if ($_SESSION['spotify_progress'] == 4) {
    $json = requestURL('https://api.spotify.com/v1/me/albums?offset=0&limit=50', 'spotify');
    addAlbums($json);
    for ($i = 0; $i < floor($json['total'] / 50); $i++) {
        $json = requestURL('https://api.spotify.com/v1/me/albums?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
        addAlbums($json);
    }
    $next = str_replace('<count>', $json['total'], text('importing_spotify_5'));
    $progress = 55;
    if (isset($json, $json['items'], $json['items'][0])) {
        $random = array_rand($json['items']);
        if (isset($json['items'][$random]['album'], $json['items'][$random]['album']['images'])) {
            $image = getSmallestImage($json['items'][$random]['album']['images']);
        }
    }
}
// Request user saved tracks
else if ($_SESSION['spotify_progress'] == 5) {
    $json = requestURL('https://api.spotify.com/v1/me/tracks?offset=0&limit=50', 'spotify');
    addTracks(['id' => 'favorites', 'name' => 'favorites', 'images' => []], $json);
    for ($i = 0; $i < floor($json['total'] / 50); $i++) {
        $json = requestURL('https://api.spotify.com/v1/me/tracks?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
        addTracks(['id' => 'favorites', 'name' => 'favorites', 'images' => []], $json);
    }
    $next = text('importing_spotify_6');
    $progress = 75;
    if (isset($json, $json['items'], $json['items'][0], $json['items'][0]['track'], $json['items'][0]['track']['album'], $json['items'][0]['track']['album']['images'])) {
        $image = getSmallestImage($json['items'][0]['track']['album']['images']);
    }
}
// Request featured albums for home page
else if ($_SESSION['spotify_progress'] == 6) {
    $json = requestURL('https://api.spotify.com/v1/search?q=tag:new&type=album&limit=19', 'spotify');
    $next = text('importing_spotify_7');
    $progress = 80;
    if (isset($json, $json['albums'], $json['albums']['items'], $json['albums']['items'][0], $json['albums']['items'][0]['images'])) {
        $image = getSmallestImage($json['albums']['items'][0]['images']);
    }
}
// Load featured albums as hidden to library
else if ($_SESSION['spotify_progress'] == 7) {
    $json = requestURL('https://api.spotify.com/v1/search?q=tag:new&type=album&limit=19', 'spotify');
    if (isset($json['albums'], $json['albums']['items'], $json['albums']['items'][0])) {
        foreach ($json['albums']['items'] as $album) {
            addAlbum(requestURL('https://api.spotify.com/v1/albums/' . $album['id'], 'spotify'), true);
        }
    }
    $json = requestURL('https://api.spotify.com/v1/me', 'spotify');
    $next = $json['display_name'];
    $progress = 100;
    $image = 'svg/placeholder.svg';
    if (isset($json, $json['images'], $json['images'][0], $json['images'][0]['url'])) {
        $image = $json['images'][0]['url'];
    }
}
$_SESSION['spotify_progress']++;

// Save library if modified
if (in_array($currentStep, $loadLibrary)) {
    writeFile('../library/tracks.json', json_encode($tracks));
    writeFile('../library/albums.json', json_encode($albums));
}

// Add some randomness to make the progress bar seem more trustworthy or something, idk
if ($progress != 100) {
    $progress += rand(-3, 3);
}

exit(json_encode([
    'continue' => $_SESSION['spotify_progress'] < $stepCount,
    'progress' => $progress,
    'next' => $next,
    'image' => $image,
    'tracks' => $trackCount,
    'playlists' => $playlistCount,
    'albums' => $albumCount,
]));
