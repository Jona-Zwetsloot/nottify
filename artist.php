<?php
require_once 'config.php';

if (empty($_GET['q'])) {
    exit;
}

$artistName = empty($_GET['q']) ? '' : $_GET['q'];
$artistFile = 'cache/artists/' . preg_replace($config['folder_regex'], '', $artistName) . '.json';

if (file_exists($artistFile)) {
    $content = file_get_contents($artistFile, true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid artist JSON']));
    }
    $artist = json_decode($content, true);
} else {
    $content = file_get_contents('library/tracks.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid tracks.json']));
    }
    $tracks = json_decode($content, true);

    $content = file_get_contents('library/albums.json', true);
    if (!json_validate($content)) {
        exit(json_encode(['error' => 'Invalid albums.json']));
    }
    $albums = json_decode($content, true);

    // Protect against API abuse if enabled
    if (!array_key_exists('prevent_api_abuse', $config) || $config['prevent_api_abuse']) {
        $artistInLibrary = false;
        foreach ($tracks as $track) {
            if (isset($track['meta']['artists'])) {
                $track['meta']['artist'] = implode(', ', $track['meta']['artists']);
            } else if (!isset($track['meta']['artist'])) {
                $track['meta']['artist'] = '';
            }
            if (in_array($_GET['q'], explode(', ', $track['meta']['artist']))) {
                $artistInLibrary = true;
                break;
            }
        }
        if (!$artistInLibrary) {
            exit('<p>Artist does not exist in library. Request blocked to prevent API abuse. Set "prevent_api_abuse" in the config file to false to remove this warning.</p>');
        }
    }

    if (!file_exists('cache')) {
        mkdir('cache', 0777, true);
    }
    if (!file_exists('cache/artists')) {
        mkdir('cache/artists', 0777, true);
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
            if (isset($artistInfo['name'])) {
                $artist['name'] = $artistInfo['name'];
            }

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
                if (isset($hit['result'], $hit['result']['primary_artist'], $hit['result']['primary_artist']['name'], $hit['result']['primary_artist']['id']) && strtolower($hit['result']['primary_artist']['name']) == strtolower($_GET['q'])) {
                    $geniusId = $hit['result']['primary_artist']['id'];
                    $artistName = $hit['result']['primary_artist']['name'];
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
                        foreach ($track['featured_artists'] as $artist) {
                            if ($artist['id'] == $geniusId) {
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
                        $artist['picture'] = 'image-proxy?url=' . rawurlencode($artistInfo['response']['artist']['image_url']);
                    }
                    if (!empty($artistInfo['response']['artist']['header_image_url']) && !str_contains($artistInfo['response']['artist']['header_image_url'], 'default_avatar') && $artistInfo['response']['artist']['header_image_url'] != $artistInfo['response']['artist']['image_url']) {
                        $artist['banner'] = 'image-proxy?url=' . rawurlencode($artistInfo['response']['artist']['header_image_url']);
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
                    $artist['banner'] = 'image-proxy?url=' . rawurlencode($artistInfo['artists']['items'][0]['images'][0]['url']);
                } else {
                    $artist['picture'] = 'image-proxy?url=' . rawurlencode($artistInfo['artists']['items'][0]['images'][0]['url']);
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
                write_file('library/tracks.json', json_encode($tracks));
                write_file('library/albums.json', json_encode($albums));

                $artist['popular'] = [];
                foreach ($popular['items'] as $album) {
                    if (empty($album['id'])) {
                        continue;
                    }
                    array_push($artist['popular'], [
                        'image' => 'image-proxy?url=' . rawurlencode($album['images'][0]['url']),
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


// Output data

echo '<img id="picture" src="' . filter_var($artist['picture'], FILTER_SANITIZE_SPECIAL_CHARS) . '">';
if (empty($artist['banner'])) {
    echo '<div id="banner"></div>';
} else {
    echo '<img id="banner" src="' . filter_var($artist['banner'], FILTER_SANITIZE_SPECIAL_CHARS) . '">';
}
echo '<h1>' . filter_var($artist['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h1>';
echo '<div id="description-and-info-wrapper">';
if (!empty($artist['description'])) {
    echo $artist['description'];
}
if (count($artist['facts']) != 0) {
    echo '<div id="detailed-artist-info">';
    foreach ($artist['facts'] as $key => $value) {
        echo '<div><p>' . filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS) . '</p><p>' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div>';
    }
    echo '</div>';
}
echo '</div>';

if (count($artist['socials']) != 0) {
    echo '<br><div id="socials">';
    foreach ($artist['socials'] as $key => $value) {
        echo '<a href="' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '"><img src="svg/' . $key . '.svg"></a>';
    }
    echo '</div>';
}
echo '<br>';
echo '<h3 id="in-library"></h3>';

if (count($artist['popular']) != 0) {
    echo '<br><h3 id="popular-tracks"></h3><div class="other-tracks">';
    foreach ($artist['popular'] as $track) {
        if (str_starts_with($track['url'], 'spotify:')) {
            echo '<div class="tile spotify-content" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var($track['image'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><div><div class="play" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></div></div>';
        } else {
            echo '<a target="_blank" href="' . filter_var($track['url'], FILTER_SANITIZE_SPECIAL_CHARS) . '" class="tile search-result">' . (empty($track['image']) ? '' : '<img loading="lazy" src="image-proxy?url=' . filter_var(rawurlencode($track['image']), FILTER_SANITIZE_SPECIAL_CHARS) . '">') . '<div><h3 title="' . filter_var($track['full_name'], FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></a>';
        }
    }
    echo '</div>';
}

if (count($artist['albums']) != 0) {
    echo '<br><h3 id="more-from-artist"></h3><div class="other-tracks">';
    foreach ($artist['albums'] as $track) {
        if (str_starts_with($track['url'], 'spotify:')) {
            echo '<div class="tile spotify-content" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var($track['image'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><div><div class="play" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></div></div>';
        } else {
            echo '<a target="_blank" href="' . filter_var($track['url'], FILTER_SANITIZE_SPECIAL_CHARS) . '" class="tile search-result">' . (empty($track['image']) ? '' : '<img loading="lazy" src="image-proxy?url=' . filter_var(rawurlencode($track['image']), FILTER_SANITIZE_SPECIAL_CHARS) . '">') . '<div><h3 title="' . filter_var($track['full_name'], FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></a>';
        }
    }
    echo '</div>';
}
