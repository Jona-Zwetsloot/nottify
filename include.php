<?php
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    header('Content-type: application/json');
    exit(json_encode(['error' => text('blocked_backend')]));
}

// Start session
if (!isset($_SESSION)) {
    $cookie_lifetime = 365 * 24 * 60 * 60;
    session_set_cookie_params($cookie_lifetime);
    session_start();
}

// Load requested language
if (array_key_exists('language', $config) && $config['language'] != 'auto' && file_exists(dirname(__FILE__) . '/locales/' . $config['language'] . '.json')) {
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
            if (file_exists(dirname(__FILE__) . '/locales/' . $language . '.json')) {
                $userLanguage = $language;
                break;
            }
        }
    }
}
if (empty($userLanguage)) {
    $userLanguage = 'en';
}
$translationJSON = file_get_contents(dirname(__FILE__) . '/locales/' . $userLanguage . '.json', true);
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
function mimeToFileExtension($mime)
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
function writeFile($path, $content)
{
    if (!file_exists($path)) {
        file_put_contents($path, $content);
        return;
    }

    $fp = fopen($path, 'r+', true);

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        if (fwrite($fp, $content) === false) {
            header('Content-type: application/json');
            exit(json_encode(['error' => str_replace('<file>', pathinfo($path, PATHINFO_FILENAME), text('could_not_save_file'))]));
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        header('Content-type: application/json');
        exit(json_encode(['error' => text('file_lock')]));
    }

    fclose($fp);
}

// Exit with a nice formatted message
function exitMessage($title, $description, $button = null)
{
    global $notifications;
    if (isset($notifications)) {
        $notifications .= 'sendNotification(\'' . filter_var(str_replace('\'', '\\\'', $title), FILTER_SANITIZE_SPECIAL_CHARS) . '\', \'' . filter_var(str_replace('\'', '\\\'', $description), FILTER_SANITIZE_SPECIAL_CHARS) . '\');';
    } else {
        exit('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS) . '</title><link rel="stylesheet" href="resources/stylesheet.css"><meta name="author" content="Jona Zwetsloot"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" type="image/svg" href="svg/nottify.svg"></head><body><div id="center-message"><h3>' . filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($description, FILTER_SANITIZE_SPECIAL_CHARS) . '</p>' . ($button == null ? '' : '<a class="button" href="' . $button['href'] . '">' . $button['text'] . '</a>') . '</div></body></html>');
    }
}

// Request something from an API and cache or ratelimit it
function requestURL($url, $service = 'default', $cache = true, $rateLimit = false)
{
    global $config, $genius;
    $savePath = dirname(__FILE__) . '/cache/' . preg_replace($config['folder_regex'], '', $service) . '/' . preg_replace($config['folder_regex'], '', strtolower($url));

    if (file_exists($savePath)) {
        $output = file_get_contents($savePath, true);
    } else {
        try {
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
            if (array_key_exists('curl_use_default_cacert', $config) && !$config['curl_use_default_cacert']) {
                curl_setopt($ch, CURLOPT_CAINFO, str_replace('\\', '/', dirname(__FILE__)) . '/resources/cacert.pem');
            }
            if (array_key_exists('curl_verify_ssl_certificates', $config) && !$config['curl_verify_ssl_certificates']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            }
            $output = curl_exec($ch);
            if ($output === false) {
                exitMessage(text('curl_error'), $url . ', ' . curl_error($ch));
                return;
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($service != 'lastfm' && ($status < 200 || $status >= 300)) {
                exitMessage(str_replace('<status>', $status, text('invalid_status_code')), $url . ', ' . $output);
                return;
            }
            if ($rateLimit) {
                sleep(1);
            }
        } catch (Exception $e) {
            exitMessage(text('curl_error'), $url . ', ' . $e->getCode() . ', ' . $e->getMessage());
            return;
        }
    }
    if (!json_validate($output)) {
        exitMessage(text('error'), str_replace('<url>', $url, text('invalid_response')) . ' ' . $output);
        return;
    }
    if ($cache && !file_exists($savePath)) {
        if (!file_exists(dirname(__FILE__) . '/cache')) {
            mkdir(dirname(__FILE__) . '/cache', 0777, true);
        }
        if (!file_exists(dirname(__FILE__) . '/cache/' . preg_replace($config['folder_regex'], '', $service))) {
            mkdir(dirname(__FILE__) . '/cache/' . preg_replace($config['folder_regex'], '', $service), 0777, true);
        }
        writeFile($savePath, $output);
    }
    return json_decode($output, true);
}

// Add Spotify tracks to library
function addTracks($album, $albumTracks, $hide = false)
{
    global $albums, $tracks, $trackCount;
    $i = 1;
    // Now loop through album/playlist tracks
    foreach ($albumTracks['items'] as $item) {
        if (isset($trackCount)) {
            $trackCount++;
        }
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
        if ($hide) {
            $tracks[$item['id']]['hide'] = true;
        }
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
                'url' => 'api/image-proxy?url=' . rawurlencode($biggestImage),
                'version' => 1,
            ];
        }
        $i++;
    }
}

// Add Spotify album to library
function addAlbum($album, $hide = false)
{
    global $albums, $tracks, $albumCount, $playlistCount;
    if (!array_key_exists($album['id'], $albums)) {
        // Create base album object
        $albums[$album['id']] = [
            'name' => $album['name'],
            'picture' => (isset($album['images'], $album['images'][0]) && !empty($album['images'][0]['url'])) ? [
                'url' => 'api/image-proxy?url=' . rawurlencode($album['images'][0]['url']),
                'version' => 1,
                'track' => false,
            ] : null,
            'source' => 'spotify',
            'tracks' => [],
            'artists' => [],
            'version' => 1,
        ];
        if ($hide) {
            $albums[$album['id']]['hide'] = true;
        }
    }
    // Get first 50 tracks and add them to playlist
    if (array_key_exists('tracks', $album)) {
        $url = $album['tracks']['href'];
        if (array_key_exists('items', $album['tracks'])) {
            if (isset($albumCount)) {
                $albumCount++;
            }
            // We're dealing with an album, the first 50 tracks are given
            $albumTracks = $album['tracks'];
        } else {
            // We're dealing with a playlist, request first 50 tracks
            if (isset($playlistCount)) {
                $playlistCount++;
            }
            $albumTracks = requestURL($url, 'spotify');
        }
    } else {
        if (isset($albumCount)) {
            $albumCount++;
        }
        $url = 'https://api.spotify.com/v1/albums/' . $album['id'] . '/tracks?offset=0&limit=50';
        $albumTracks = requestURL($url, 'spotify');
    }
    addTracks($album, $albumTracks, $hide);

    $url = strtok($url, '?');
    $total = $albumTracks['total'];
    $limit = $albumTracks['limit'];

    for ($i = 0; $i < floor($total / $limit); $i++) {
        $albumTracks = requestURL($url . '?offset=' . (($i + 1) * $limit) . '&limit=' . $limit, 'spotify');
        addTracks($album, $albumTracks, $hide);
    }
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
