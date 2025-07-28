<?php
// Start session 
if (!isset($_SESSION)) {
    $cookie_lifetime = 365 * 24 * 60 * 60;
    session_set_cookie_params($cookie_lifetime);
    session_start();
}

// Load requested language
if (array_key_exists('language', $config) && $config['language'] != 'auto' && file_exists('locales/' . $config['language'] . '.json')) {
    $userLanguage = $config['language'];
} else {
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $prefLocales = array_reduce(
            explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
            function ($res, $el) {
                list($l, $q) = array_merge(explode(';q=', $el), [1]);
                $res[$l] = (float) $q;
                return $res;
            },
            []
        );
        arsort($prefLocales);
        foreach ($prefLocales as $language => $priority) {
            $language = strtolower(preg_replace('/[^a-zA-Z]/', '', preg_replace('/-[\s\S]/', '', $language)));
            if (file_exists('locales/' . $language . '.json')) {
                $userLanguage = $language;
                break;
            }
        }
    }
}
if (empty($userLanguage)) {
    $userLanguage = 'en';
}
$translationJSON = file_get_contents('locales/' . $userLanguage . '.json', true);
$translations = json_decode($translationJSON, true);

// Get text string in active language
function text($key)
{
    global $translations;
    if (array_key_exists($key, $translations)) {
        if (is_string($translations[$key])) {
            return $translations[$key];
        } else {
            return $translations[$key][array_rand($translations[$key], 1)];
        }
    } else {
        return $key;
    }
}

// Convert mime to extension
// Audio metadata includes images with mime type without extension
function mime2ext($mime)
{
    $mime_map = [
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-bitmap' => 'bmp',
        'image/x-xbitmap' => 'bmp',
        'image/x-win-bitmap' => 'bmp',
        'image/x-windows-bmp' => 'bmp',
        'image/ms-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/cdr' => 'cdr',
        'image/x-cdr' => 'cdr',
        'image/gif' => 'gif',
        'image/x-icon' => 'ico',
        'image/x-ico' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/jp2' => 'jp2',
        'image/jpx' => 'jp2',
        'image/jpm' => 'jp2',
        'image/jpeg' => 'jpeg',
        'image/pjpeg' => 'jpeg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/vnd.adobe.photoshop' => 'psd',
        'image/svg+xml' => 'svg',
        'image/tiff' => 'tiff',
        'image/webp' => 'webp',
    ];

    return isset($mime_map[$mime]) ? $mime_map[$mime] : null;
}

// Write file safely without risking corruption by simultaneous writes
// Simultaneous writes are rejected because of the file lock
function write_file($path, $content)
{
    if (!file_exists($path)) {
        file_put_contents($path, $content);
        return;
    }

    $fp = fopen($path, 'r+', true);

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        if (fwrite($fp, $content) === false) {
            exit(json_encode(['error' => 'Could not save file. Try again later.']));
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        exit(json_encode(['error' => 'Could not get file lock. Try again later.']));
    }

    fclose($fp);
}

// Exit with a nice formatted message
function exitMessage($title, $description, $button = null)
{
    exit('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS) . '</title><link rel="stylesheet" href="index.css"><meta name="author" content="Jona Zwetsloot"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" type="image/svg" href="svg/nottify.svg"></head><body><div id="center-message"><h3>' . filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($description, FILTER_SANITIZE_SPECIAL_CHARS) . '</p>' . ($button == null ? '' : '<a class="button" href="' . $button['href'] . '">' . $button['text'] . '</a>') . '</div></body></html>');
}

// Request something from an API and cache or ratelimit it
function requestURL($url, $service = 'default', $cache = true, $rateLimit = false)
{
    global $config, $genius;
    $savePath = 'cache/' . preg_replace($config['folder_regex'], '', $service) . '/' . preg_replace($config['folder_regex'], '', strtolower($url));

    if (file_exists($savePath)) {
        $output = file_get_contents($savePath, true);
    } else {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $config['useragent']);
        if ($service == 'spotify') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'content-type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $_SESSION['spotify_token'],
            ]);
        } else if ($service == 'genius') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $genius['apikey'],
            ]);
        } else if ($service == 'musicbrainz') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json'
            ]);
        }
        $output = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status == 0) {
            exitMessage('Server offline', 'Please check the connection.');
        } else if ($service != 'lastfm' && ($status < 200 || $status >= 300)) {
            exitMessage('Invalid status code (' . $status . ')', $output);
        }
        if ($rateLimit) {
            sleep(1);
        }
    }
    if (!json_validate($output)) {
        exitMessage('Invalid JSON', $output);
    }
    if ($cache && !file_exists($savePath)) {
        if (!file_exists('cache')) {
            mkdir('cache', 0777, true);
        }
        if (!file_exists('cache/' . preg_replace($config['folder_regex'], '', $service))) {
            mkdir('cache/' . preg_replace($config['folder_regex'], '', $service), 0777, true);
        }
        write_file($savePath, $output);
    }
    return json_decode($output, true);
}

// Add Spotify tracks to library
function addTracks($album, $albumTracks)
{
    global $albums, $tracks;
    $i = 1;
    // Now loop through album/playlist tracks
    foreach ($albumTracks['items'] as $item) {
        $addedAt = time();
        // Playlist tracks are under the "track" key
        if (array_key_exists('track', $item)) {
            $addedAt = strtotime($item['added_at']);
            $item = $item['track'];
        }
        // If no idea is present, or the track is unavailable for some reason, do not display it
        if (empty($item['id']) || array_key_exists($item['id'], $tracks) || (array_key_exists('restrictions', $item) && array_key_exists('reason', $item['restrictions']))) {
            continue;
        }
        // Add track to album
        if (!in_array($item['id'], $albums[$album['id']]['tracks'])) {
            array_push($albums[$album['id']]['tracks'], $item['id']);
        }

        // Create base track object
        $tracks[$item['id']] = [
            'name' => $item['uri'],
            'added' => $addedAt,
            'pictures' => [],
            'meta' => [
                'track' => [
                    'no' => $i,
                    'of' => $albumTracks['total'],
                ],
                'title' => $item['name'],
                'album' => array_key_exists('album', $item) ? $item['album']['name'] : $album['name'],
            ],
            'format' => [],
            'source' => 'spotify',
        ];
        // Get artists in comma separated string
        $artists = [];
        foreach ($item['artists'] as $artist) {
            array_push($artists, $artist['name']);
        }
        $tracks[$item['id']]['meta']['artist'] = implode(', ', $artists);
        // Get biggest image
        $biggest = 0;
        $iterateOver = array_key_exists('album', $item) ? $item['album']['images'] : $album['images'];
        foreach ($iterateOver as $image) {
            $pixels = $image['height'] * $image['width'];
            if ($pixels > $biggest) {
                $biggest = $pixels;
                $biggestImage = $image['url'];
            }
        }
        if (!empty($biggestImage)) {
            $tracks[$item['id']]['pictures'][0] = [
                'url' => 'image-proxy?url=' . rawurlencode($biggestImage),
                'version' => 1,
            ];
        }
        $i++;
    }
}

// Add Spotify album to library
function addAlbum($album, $hide = false)
{
    global $albums, $tracks;
    if (array_key_exists($album['id'], $albums)) {
        return;
    }
    // Create base album object
    $albums[$album['id']] = [
        'name' => $album['name'],
        'picture' => [
            'url' => 'image-proxy?url=' . rawurlencode($album['images'][0]['url']),
            'version' => 1,
            'track' => false,
        ],
        'source' => 'spotify',
        'tracks' => [],
        'artists' => [],
        'version' => 1,
    ];
    if ($hide) {
        $albums[$album['id']]['hide'] = true;
    }
    // Albums contain all tracks, playlist tracks need to be requested through given href
    if (array_key_exists('tracks', $album)) {
        if (array_key_exists('items', $album['tracks'])) {
            $albumTracks = $album['tracks'];
        } else {
            $albumTracks = requestURL($album['tracks']['href'], 'spotify');
        }
    } else {
        $albumTracks = requestURL('https://api.spotify.com/v1/albums/' . $album['id'] . '/tracks', 'spotify');
    }
    addTracks($album, $albumTracks);
}

// Add Spotify albums to library
function addAlbums($json, $hide = false)
{
    global $albums, $tracks;
    // Loop through result items, which can either be a playlist or an album
    foreach ($json['items'] as $album) {
        // Get the playlist/album ID
        if (!array_key_exists('id', $album)) {
            $album = $album['album'];
        }
        if (empty($album['id'])) {
            continue;
        }
        addAlbum($album, $hide);
    }
}
