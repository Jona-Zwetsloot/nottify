<?php
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    header('Content-type: application/json');
    exit(json_encode(['error' => 'This is a backend PHP file. It\'s not accessible from the client-side.']));
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
            header('Content-type: application/json');
            exit(json_encode(['error' => 'Could not save file. Try again later.']));
        }
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        header('Content-type: application/json');
        exit(json_encode(['error' => 'Could not get file lock. Try again later.']));
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
            if (array_key_exists('curl_verify_ssl_certificates', $config) && !$config['curl_verify_ssl_certificates']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            }
            $output = curl_exec($ch);
            if ($output === false) {
                exitMessage('Curl error', curl_error($ch));
                return;
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($service != 'lastfm' && ($status < 200 || $status >= 300)) {
                exitMessage('Invalid status code (' . $status . ')', $output);
                return;
            }
            if ($rateLimit) {
                sleep(1);
            }
        } catch (Exception $e) {
            exitMessage('Error', sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
        }
    }
    if (!json_validate($output)) {
        exitMessage('Invalid JSON', $output);
        return;
    }
    if ($cache && !file_exists($savePath)) {
        if (!file_exists(dirname(__FILE__) . '/cache')) {
            mkdir(dirname(__FILE__) . '/cache', 0777, true);
        }
        if (!file_exists(dirname(__FILE__) . '/cache/' . preg_replace($config['folder_regex'], '', $service))) {
            mkdir(dirname(__FILE__) . '/cache/' . preg_replace($config['folder_regex'], '', $service), 0777, true);
        }
        write_file($savePath, $output);
    }
    return json_decode($output, true);
}

// Add Spotify tracks to library
function addTracks($album, $albumTracks, $hide = false)
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
    global $albums, $tracks;
    if (array_key_exists($album['id'], $albums)) {
        return;
    }
    // Create base album object
    $albums[$album['id']] = [
        'name' => $album['name'],
        'picture' => [
            'url' => 'api/image-proxy?url=' . rawurlencode($album['images'][0]['url']),
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
    addTracks($album, $albumTracks, $hide);
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

// Request artist info, or load from cache
function getArtist($artistName)
{
    global $config, $spotify, $genius, $albums, $tracks;
    $artistFile = dirname(__FILE__) . '/cache/artists/' . preg_replace($config['folder_regex'], '', $artistName) . '.json';

    if (file_exists($artistFile)) {
        $content = file_get_contents($artistFile, true);
        if (!json_validate($content)) {
            exitMessage('error', 'Invalid artist JSON');
        }
        $artist = json_decode($content, true);
    } else {
        if (empty($tracks)) {
            $content = file_get_contents(dirname(__FILE__) . '/library/tracks.json', true);
            if (!json_validate($content)) {
                exitMessage('error', 'Invalid tracks.json');
            }
            $tracks = json_decode($content, true);
        }

        if (empty($albums)) {
            $content = file_get_contents(dirname(__FILE__) . '/library/albums.json', true);
            if (!json_validate($content)) {
                exitMessage('error', 'Invalid albums.json');
            }
            $albums = json_decode($content, true);
        }

        // Protect against API abuse if enabled
        if (!array_key_exists('prevent_api_abuse', $config) || $config['prevent_api_abuse']) {
            $artistInLibrary = false;
            foreach ($tracks as $track) {
                if (isset($track['meta']['artists'])) {
                    $track['meta']['artist'] = implode(', ', $track['meta']['artists']);
                } else if (!isset($track['meta']['artist'])) {
                    $track['meta']['artist'] = '';
                }
                if (in_array($artistName, explode(', ', $track['meta']['artist']))) {
                    $artistInLibrary = true;
                    break;
                }
            }
            if (!$artistInLibrary) {
                exitMessage('Artist does not exist in library', 'Request blocked to prevent API abuse. Set "prevent_api_abuse" in the config file to false to remove this warning.');
            }
        }

        if (!file_exists(dirname(__FILE__) . '/cache')) {
            mkdir(dirname(__FILE__) . '/cache', 0777, true);
        }
        if (!file_exists(dirname(__FILE__) . '/cache/artists')) {
            mkdir(dirname(__FILE__) . '/cache/artists', 0777, true);
        }

        $artist = [
            'name' => $artistName,
            'sources' => [],
            'genres' => [],
            'picture' => 'svg/placeholder.svg',
            'socials' => [],
            'facts' => [],
            'popular' => [],
            'albums' => [],
        ];

        // Add Musicbrainz data
        if (isset($config['artist_info_providers']) && in_array('musicbrainz', $config['artist_info_providers'])) {

            // First, use the search endpoint to get the artist Musicbrainz ID
            $search = requestURL('https://musicbrainz.org/ws/2/artist/?limit=1&query=' . rawurlencode($artist['name']), 'musicbrainz', false, true);
            array_push($artist['sources'], 'musicbrainz');

            // If we get any results, we can continue
            if (isset($search['artists'], $search['artists'][0], $search['artists'][0]['id'])) {

                // Add artist Musicbrainz page to socials
                $mbid = $search['artists'][0]['id'];
                $artist['socials']['musicbrainz'] = 'https://musicbrainz.org/artist/' . $mbid;

                // Get genres
                if (!empty($search['artists'][0]['tags'])) {
                    usort($search['artists'][0]['tags'], function ($a, $b) {
                        return $b['count'] <=> $a['count'];
                    });
                    foreach ($search['artists'][0]['tags'] as $tag) {
                        array_push($artist['genres'], $tag['name']);
                    }
                }

                // Then, get the artist info and tracks
                $artistInfo = requestURL('https://musicbrainz.org/ws/2/artist/' . $mbid . '?inc=url-rels+releases', 'musicbrainz', false);

                // Add artist facts
                $aliases = [];
                if (isset($search['artists'][0]['aliases'])) {
                    foreach ($search['artists'][0]['aliases'] as $alias) {
                        if ($alias['type'] == 'Legal name') {
                            $artist['facts']['Legal name'] = $alias['name'];
                        } else {
                            array_push($aliases, $alias['name']);
                        }
                    }
                }
                if (!empty($artistInfo['disambiguation'])) {
                    $artist['facts']['Disambiguation'] = $artistInfo['disambiguation'];
                }
                if (isset($artistInfo['area'], $artistInfo['area']['name'])) {
                    $artist['facts']['Location'] = $artistInfo['area']['name'] . (empty($artistInfo['country']) ? '' : ' (' . $artistInfo['country'] . ')');
                }
                if (isset($artistInfo['begin-area'], $artistInfo['begin-area']['name'])) {
                    $artist['facts']['Started in'] = $artistInfo['begin-area']['name'];
                }
                if (isset($artistInfo['end-area'], $artistInfo['end-area']['name'])) {
                    $artist['facts']['Ended in'] = $artistInfo['end-area']['name'];
                }
                if (!empty($artistInfo['gender'])) {
                    $artist['facts']['Gender'] = $artistInfo['gender'];
                }
                if (isset($artistInfo['life-span'])) {
                    if (!empty($artistInfo['life-span']['begin'])) {
                        $artist['facts']['Born'] = $artistInfo['life-span']['begin'];
                    }
                    if (!empty($artistInfo['life-span']['end'])) {
                        $artist['facts']['Died'] = $artistInfo['life-span']['end'];
                    }
                }
                if (count($aliases) != 0) {
                    $artist['facts']['Aliases'] = implode(', ', $aliases);
                }

                // Get social links
                if (isset($artistInfo['relations'])) {
                    foreach ($artistInfo['relations'] as $relation) {
                        $platform = null;
                        $url = $relation['url']['resource'];
                        $type = $relation['type'];

                        if ($type == 'official homepage') {
                            $platform = 'link';
                        } else if ($type == 'last.fm') {
                            $platform = 'lastfm';
                        } else if ($type == 'IMDb') {
                            $platform = 'imdb';
                        } else if ($type == 'bandcamp' || $type == 'discogs' || $type == 'soundcloud' || $type == 'youtube') {
                            $platform = $type;
                        } else if (str_starts_with($url, 'https://open.spotify.com/')) {
                            $platform = 'spotify';
                        } else if (str_starts_with($url, 'https://genius.com/')) {
                            $platform = 'genius';
                        } else if (str_starts_with($url, 'https://www.deezer.com/')) {
                            $platform = 'deezer';
                        } else if (str_starts_with($url, 'https://www.facebook.com/')) {
                            $platform = 'facebook';
                        } else if (str_starts_with($url, 'https://www.instagram.com/')) {
                            $platform = 'instagram';
                        } else if (str_starts_with($url, 'https://www.tiktok.com/')) {
                            $platform = 'tiktok';
                        } else if (str_starts_with($url, 'https://music.amazon.')) {
                            $platform = 'amazonmusic';
                        } else if (str_starts_with($url, 'https://tidal.com/')) {
                            $platform = 'tidal';
                        } else if (str_starts_with($url, 'https://music.youtube.com/')) {
                            $platform = 'youtubemusic';
                        } else if (str_starts_with($url, 'https://twitter.com/') || str_starts_with($url, 'https://x.com/') || str_starts_with($url, 'https://www.x.com/')) {
                            // Fuck that nazi guy, still offer people a way to see what their fav artist posted
                            $platform = 'twitter';
                            $url = str_replace('https://x.com/', 'https://xcancel.com/', str_replace('https://www.x.com/', 'https://xcancel.com/', str_replace('https://twitter.com/', 'https://xcancel.com/', $url)));
                        } else if (str_starts_with($url, 'https://itunes.apple.com/') || str_starts_with($url, 'https://music.apple.com/')) {
                            $platform = 'applemusic';
                        }
                        if ($platform != null && !array_key_exists($platform, $artist['socials'])) {
                            $artist['socials'][$platform] = $url;
                        }
                    }
                }
                if (isset($artistInfo['releases']) && empty($tracks)) {
                    foreach ($artistInfo['releases'] as $track) {
                        array_push($artist['popular'], [
                            'image' => null,
                            'full_name' => $track['title'],
                            'name' => $track['title'],
                            'date' => $track['date'],
                            'id' => $track['id'],
                            'url' => 'https://musicbrainz.org/release/' . $track['id'],
                        ]);
                    }
                }
            }
        }

        // Add Genius data
        if (isset($genius, $genius['enabled'], $genius['apikey']) && $genius['enabled'] && isset($config['artist_info_providers']) && in_array('genius', $config['artist_info_providers'])) {

            // First, use the search endpoint to get the artist Genius ID
            $search = requestURL('https://api.genius.com/search?q=' . rawurlencode($artist['name']), 'genius', false);
            array_push($artist['sources'], 'genius');

            if (isset($search['response'], $search['response']['hits'])) {

                // Genius searches for both artists AND tracks, so we need to ignore the tracks
                foreach ($search['response']['hits'] as $hit) {
                    if (isset($hit['result'], $hit['result']['primary_artist'], $hit['result']['primary_artist']['name'], $hit['result']['primary_artist']['id']) && strtolower($hit['result']['primary_artist']['name']) == strtolower($artistName)) {
                        $geniusId = $hit['result']['primary_artist']['id'];
                        break;
                    }
                }

                // If we get any results, we can continue
                if (isset($geniusId)) {

                    $artist['popular'] = [];

                    // Add tracks from the search results to the popular track section if made by the requested artist
                    foreach ($search['response']['hits'] as $hit) {
                        $track = $hit['result'];
                        $isFromArtist = $track['primary_artist']['id'] == $geniusId;
                        if (!$isFromArtist) {
                            foreach ($track['featured_artists'] as $featuredArtist) {
                                if ($featuredArtist['id'] == $geniusId) {
                                    $isFromArtist = true;
                                    break;
                                }
                            }
                        }
                        if ($isFromArtist) {
                            array_push($artist['popular'], [
                                'image' => $track['song_art_image_thumbnail_url'],
                                'full_name' => $track['full_title'],
                                'name' => $track['title'],
                                'date' => $track['release_date_with_abbreviated_month_for_display'],
                                'id' => $track['id'],
                                'url' => $track['url'],
                            ]);
                        }
                    }

                    // Then, get the artist info
                    $artistInfo = requestURL('https://api.genius.com/artists/' . rawurlencode($geniusId), 'genius', false);
                    if (isset($artistInfo['response'], $artistInfo['response']['artist'])) {
                        if (!empty($artistInfo['response']['artist']['followers_count'])) {
                            $artist['followers'] = $artistInfo['response']['artist']['followers_count'];
                        }
                        if (!empty($artistInfo['response']['artist']['iq'])) {
                            $artist['iq'] = $artistInfo['response']['artist']['iq'];
                        }
                        if (!empty($artistInfo['response']['artist']['url'])) {
                            $artist['socials']['genius'] = $artistInfo['response']['artist']['url'];
                        }
                        if (!empty($artistInfo['response']['artist']['facebook_name'])) {
                            $artist['socials']['genius'] = 'https://www.facebook.com/' . $artistInfo['response']['artist']['facebook_name'];
                        }
                        if (!empty($artistInfo['response']['artist']['instagram_name'])) {
                            $artist['socials']['instagram'] = 'https://www.instagram.com/' . $artistInfo['response']['artist']['instagram_name'] . '/';
                        }
                        if (!empty($artistInfo['response']['artist']['twitter_name'])) {
                            $artist['socials']['twitter'] = 'https://xcancel.com/' . $artistInfo['response']['artist']['twitter_name'];
                        }
                        if (!empty($artistInfo['response']['artist']['image_url']) && !str_contains($artistInfo['response']['artist']['image_url'], 'default_avatar')) {
                            $artist['picture'] = 'api/image-proxy?url=' . rawurlencode($artistInfo['response']['artist']['image_url']);
                        }
                        if (!empty($artistInfo['response']['artist']['header_image_url']) && !str_contains($artistInfo['response']['artist']['header_image_url'], 'default_avatar') && $artistInfo['response']['artist']['header_image_url'] != $artistInfo['response']['artist']['image_url']) {
                            $artist['banner'] = 'api/image-proxy?url=' . rawurlencode($artistInfo['response']['artist']['header_image_url']);
                        }
                        if (isset($artistInfo['response']['artist']['description'], $artistInfo['response']['artist']['description']['dom'])) {
                            function parseGeniusJsonHtml($json)
                            {
                                if (!isset($json['tag'])) {
                                    return;
                                }
                                $tag = filter_var($json['tag'], FILTER_SANITIZE_SPECIAL_CHARS);
                                $html = '<' . ($tag == 'root' ? 'div id="description"' : $tag);
                                if (isset($json['attributes'])) {
                                    foreach ($json['attributes'] as $key => $value) {
                                        $html .= ' ' . filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS) . '="' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '"';
                                    }
                                }
                                $html .= '>';
                                if (isset($json['children'])) {
                                    foreach ($json['children'] as $child) {
                                        if (is_string($child)) {
                                            $html .= filter_var($child, FILTER_SANITIZE_SPECIAL_CHARS);
                                        } else {
                                            $html .= parseGeniusJsonHtml($child);
                                        }
                                    }
                                }
                                $html .= '</' . ($tag == 'root' ? 'div' : $tag) . '>';
                                return $html;
                            }
                            $artist['description'] = parseGeniusJsonHtml($artistInfo['response']['artist']['description']['dom']);
                        }
                    }

                    // Then, get the artist tracks
                    $artistTracks = requestURL('https://api.genius.com/artists/' . rawurlencode($geniusId) . '/songs', 'genius', false);
                    foreach ($artistTracks['response']['songs'] as $track) {
                        array_push($artist['albums'], [
                            'image' => $track['song_art_image_thumbnail_url'],
                            'full_name' => $track['full_title'],
                            'name' => $track['title'],
                            'date' => $track['release_date_with_abbreviated_month_for_display'],
                            'id' => $track['id'],
                            'url' => $track['url'],
                        ]);
                    }
                }
            }
        }

        // Add Spotify data
        $spotifyEnabled = isset($spotify) && $spotify['enabled'] && !empty($_SESSION['spotify_token']);
        if ($spotifyEnabled && isset($config['artist_info_providers']) && in_array('spotify', $config['artist_info_providers'])) {
            require_once 'refresh-token.php';

            // When setting the limit to 1, the Spotify API will reverse the 1st and 2nd result
            // Which means, setting the limit AND offset to 1 WILL return the first item. FUCKING HELL
            // However, since we need a long term solution, I've opted to set the limit to 2, which DOES work for some reason
            $artistInfo = requestURL('https://api.spotify.com/v1/search?q=' . rawurlencode($artist['name']) . '&type=artist&limit=2', 'spotify', false);
            array_push($artist['sources'], 'spotify');

            // If we get any results, we can continue
            if (isset($artistInfo['artists'], $artistInfo['artists']['items'][0], $artistInfo['artists']['items'][0], $artistInfo['artists']['items'][0]['id'])) {
                if (isset($artistInfo['artists']['items'][0]['images'], $artistInfo['artists']['items'][0]['images'][0], $artistInfo['artists']['items'][0]['images'][0]['url'])) {
                    if ($artist['picture'] != 'svg/placeholder.svg' && empty($artist['banner'])) {
                        $artist['banner'] = 'api/image-proxy?url=' . rawurlencode($artistInfo['artists']['items'][0]['images'][0]['url']);
                    } else {
                        $artist['picture'] = 'api/image-proxy?url=' . rawurlencode($artistInfo['artists']['items'][0]['images'][0]['url']);
                    }
                }
                if (isset($artistInfo['artists']['items'][0]['followers'], $artistInfo['artists']['items'][0]['followers']['total'])) {
                    $artist['followers'] = $artistInfo['artists']['items'][0]['followers']['total'];
                }
                if (isset($artistInfo['artists']['items'][0]['external_urls'], $artistInfo['artists']['items'][0]['external_urls']['spotify'])) {
                    $artist['socials']['spotify'] = $artistInfo['artists']['items'][0]['external_urls']['spotify'];
                }
                if (!empty($artistInfo['artists']['items'][0]['genres'])) {
                    $artist['genres'] = $artistInfo['artists']['items'][0]['genres'];
                }

                $spotifyId = $artistInfo['artists']['items'][0]['id'];
                $popular = requestURL('https://api.spotify.com/v1/artists/' . $spotifyId . '/albums?limit=12', 'spotify', false);

                if (isset($popular['items'])) {
                    addAlbums($popular, true);
                    write_file('../library/tracks.json', json_encode($tracks));
                    write_file('../library/albums.json', json_encode($albums));

                    $artist['popular'] = [];
                    foreach ($popular['items'] as $album) {
                        if (empty($album['id'])) {
                            continue;
                        }
                        array_push($artist['popular'], [
                            'image' => 'api/image-proxy?url=' . rawurlencode($album['images'][0]['url']),
                            'full_name' => $album['name'],
                            'name' => $album['name'],
                            'date' => $album['release_date'],
                            'id' => $album['id'],
                            'url' => $album['uri'],
                        ]);
                    }
                }
            }
        }

        write_file($artistFile, json_encode($artist));
    }
    return $artist;
}
