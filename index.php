<?php
require_once 'config.php';

$notifications = '';
if (!file_exists('library')) {
    mkdir('library', 0777, true);
}
if (!file_exists('library/tracks.json')) {
    write_file('library/tracks.json', '[]');
}
if (!file_exists('library/albums.json')) {
    $albums = [
        'all_tracks' => [
            'name' => 'All tracks',
            'picture' => [
                'url' => 'svg/all.svg',
                'mime' => 'image\/svg',
                'version' => 1,
                'track' => false
            ],
            'tracks' => [],
            'artists' => [],
            'added' => time(),
            'version' => 1
        ],
        'favorites' => [
            'name' => 'Favorites',
            'picture' => [
                'url' => 'svg/favorites.svg',
                'mime' => 'image\/svg',
                'version' => 1,
                'track' => false
            ],
            'tracks' => [],
            'artists' => [],
            'added' => time(),
            'version' => 1
        ],
        'disconnected' => [
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
        ]
    ];
    write_file('library/albums.json', json_encode($albums));
}
if (!file_exists('library/data.json')) {
    write_file('library/data.json', json_encode([
        'recent_albums' => []
    ]));
}

?>
<!DOCTYPE html>
<html>

<?php
// Attribution, required for license. Do not remove. 
echo '<!-- a nottify instance at ' . filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_SPECIAL_CHARS) . '. see https://github.com/Jona-Zwetsloot/nottify -->';
?>

<head>
    <meta charset="UTF-8" />
    <title>nottify</title>
    <link rel="stylesheet" href="resources/stylesheet.css">
    <meta name="author" content="Jona Zwetsloot">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg" href="svg/nottify.svg">
    <?php
    if (!array_key_exists('normalize_audio', $config) || $config['normalize_audio']) {
        // Use JS library for decoding
        echo '<script src="resources/lib/decode-audio-data-fast.js"></script>';
    }
    if (!array_key_exists('zip_support', $config) || $config['zip_support']) {
        // Use JS library for unpacking ZIPs
        echo '<script src="resources/lib/jszip.min.js"></script>';
    }
    if (!array_key_exists('radio_browser', $config) || $config['radio_browser']) {
        include_once 'api/radio-browser.php';
        if (file_exists('cache/radio-browser-baseurl.txt')) {
            $baseURL = file_get_contents('cache/radio-browser-baseurl.txt', true);
        } else {
            getBaseURL();
        }
    }
    $spotifyEnabled = isset($spotify) && $spotify['enabled'] && !empty($_SESSION['spotify_token']);
    if ($spotifyEnabled) {
        require_once 'api/refresh-token.php';
        $spotifyProfile = requestURL('https://api.spotify.com/v1/me', 'spotify');
    }
    $profilePicture = (isset($spotifyProfile, $spotifyProfile['images'], $spotifyProfile['images'][0], $spotifyProfile['images'][0]['url']) ? $spotifyProfile['images'][0]['url'] : 'svg/placeholder.svg');
    ?>
</head>

<body<?php
        if (array_key_exists('theme', $config)) {
            echo ' class="' . $config['theme'] . '"';
        }
        if ($spotifyEnabled) {
            echo ' data-spotify-token="' . filter_var($_SESSION['spotify_token'], FILTER_SANITIZE_SPECIAL_CHARS) . '"';
            echo ' data-spotify-token-expires="' . ((int)$_SESSION['spotify_time'] + (int)$_SESSION['spotify_expires']) . '"';
        }
        if (isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) && $lastfm['enabled']) {
            echo ' data-lastfm-scrobble="' . ((array_key_exists('lastfm_scrobble', $config) && !$config['lastfm_scrobble']) ? 'false' : 'true') . '"';
        }
        echo ' data-translation="locales/' . filter_var($userLanguage, FILTER_SANITIZE_SPECIAL_CHARS) . '.json"';
        echo ' data-normalize="' . ((!array_key_exists('normalize_audio', $config) || $config['normalize_audio']) ? 'true' : 'false') . '"';
        echo ' data-change-metadata="' . ((!array_key_exists('change_metadata_enabled', $config) || $config['change_metadata_enabled']) ? 'true' : 'false') . '"';
        echo ' data-calculate-gain="' . ((!array_key_exists('calculate_gain', $config) || $config['calculate_gain']) ? 'true' : 'false') . '"';
        echo ' data-track-radio-clicks="' . ((!array_key_exists('track_radio_clicks', $config) || $config['track_radio_clicks']) ? 'true' : 'false') . '"';
        if (!empty($baseURL)) {
            echo ' data-radio-browser-baseurl="' . $baseURL . '"';
        }
        ?>>
    <span id="tooltip"></span>
    <div id="fullscreen-container"></div>
    <div id="music-actions-menu">
        <button id="add-to-queue"><img src="svg/list.svg">
            <p></p>
        </button>
        <button id="remove-from-album"><img src="svg/remove.svg">
            <p></p>
        </button>
        <?php
        if (!array_key_exists('uploads_enabled', $config) || $config['uploads_enabled']) {
            echo '<button id="add-track"><img src="svg/plus.svg"><p>' . text('upload_track') . '</p></button>';
        }
        if (!array_key_exists('change_metadata_enabled', $config) || $config['change_metadata_enabled']) {
            echo '<button id="add-album"><img src="svg/library.svg"><p></p></button>';
        }
        ?>

        <button id="delete" class="red"><img src="svg/delete.svg">
            <p></p>
        </button>
    </div>
    <button id="back"></button>
    <div id="top-menu"><img id="logo" src="svg/nottify.svg"><input id="search" placeholder="<?php echo text('search') . '...'; ?>" class="search" type="text"><button id="friend-btn"></button><img id="user-profile-picture" src="<?php echo $profilePicture; ?>"></div>
    <div id="main">
        <div id="left-panel">
            <div>
                <div class="header">
                    <h3><?php echo text('library'); ?></h3>
                    <img id="toggle-layout" src="svg/grid.svg">
                    <img id="add" src="svg/plus.svg">
                </div>
                <input style="display: none;" multiple type="file" id="file" accept="
                <?php
                echo 'audio/*, .lrc, .txt';
                if (!array_key_exists('zip_support', $config) || $config['zip_support']) {
                    echo ', .zip';
                }
                ?>">
                <div id="album-listview"></div>
            </div>
        </div>
        <div data-resize="left-panel-width" class="resize"></div>
        <div id="middle-panel">
            <div id="home-tab">
                <?php

                $content = file_get_contents('library/data.json', true);
                if (!json_validate($content)) {
                    exitMessage('error', 'Invalid data.json');
                }
                $data = json_decode($content, true);
                $content = file_get_contents('library/tracks.json', true);
                if (!json_validate($content)) {
                    exitMessage('error', 'Invalid tracks.json');
                }
                $tracks = json_decode($content, true);
                $content = file_get_contents('library/albums.json', true);
                if (!json_validate($content)) {
                    exitMessage('error', 'Invalid albums.json');
                }
                $albums = json_decode($content, true);

                if ($spotifyEnabled) {
                    $spotifyAlbums = requestURL('https://api.spotify.com/v1/search?q=tag:new&type=album&limit=19', 'spotify');
                    if (isset($spotifyAlbums['albums'], $spotifyAlbums['albums']['items'], $spotifyAlbums['albums']['items'][0])) {
                        addAlbum(requestURL('https://api.spotify.com/v1/albums/' . $spotifyAlbums['albums']['items'][0]['id'], 'spotify'), true);
                        echo '<div id="highlight-box" class="spotify-content" data-id="' . $spotifyAlbums['albums']['items'][0]['id'] . '">';
                        $highlightArtists = [];
                        foreach ($spotifyAlbums['albums']['items'][0]['artists'] as $artist) {
                            array_push($highlightArtists, $artist['name']);
                        }
                        echo '<img loading="lazy" src="' . filter_var('api/image-proxy?url=' . rawurlencode($spotifyAlbums['albums']['items'][0]['images'][0]['url']), FILTER_SANITIZE_SPECIAL_CHARS) . '">';
                        echo '<div><div><h1>' . filter_var($spotifyAlbums['albums']['items'][0]['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h1><p>' . filter_var(implode(', ', $highlightArtists), FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><img loading="lazy" src="' . filter_var('api/image-proxy?url=' . rawurlencode($spotifyAlbums['albums']['items'][0]['images'][0]['url']), FILTER_SANITIZE_SPECIAL_CHARS) . '"></div>';
                        array_shift($spotifyAlbums['albums']['items']);
                        echo '</div>';
                    }
                }

                echo '<div id="feed">';
                echo '<div id="recent-albums">';
                $i = 1;
                foreach ($data['recent_albums'] as $album) {
                    if ($i > 8) {
                        break;
                    }
                    if (array_key_exists($album, $albums)) {
                        $image = 'svg/placeholder.svg';
                        if (!empty($albums[$album]['picture'])) {
                            $image = $albums[$album]['picture']['url'] . '?v=' . $albums[$album]['picture']['version'];
                        }
                        echo '<button data-id="' . filter_var($album, FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var($image, FILTER_SANITIZE_SPECIAL_CHARS) . '"><h3>' . filter_var($albums[$album]['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><div class="play" data-id="' . filter_var($album, FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></button>';
                    }
                    $i++;
                }
                echo '</div>';

                if ($spotifyEnabled) {
                    echo '<div>';
                    if (isset($spotifyAlbums['albums'], $spotifyAlbums['albums']['items'], $spotifyAlbums['albums']['items'][0])) {
                        foreach ($spotifyAlbums['albums']['items'] as $album) {
                            addAlbum(requestURL('https://api.spotify.com/v1/albums/' . $album['id'], 'spotify'), true);
                            $artists = [];
                            foreach ($album['artists'] as $artist) {
                                array_push($artists, $artist['name']);
                            }
                            echo '<div class="tile spotify-content" data-id="' . filter_var($album['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var('api/image-proxy?url=' . rawurlencode($album['images'][0]['url']), FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var($album['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var(implode(', ', $artists), FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><div><div class="play" data-id="' . filter_var($album['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></div></div>';
                        }
                    }
                    write_file('library/tracks.json', json_encode($tracks));
                    write_file('library/albums.json', json_encode($albums));
                    echo '</div>';
                }

                if (!array_key_exists('radio_browser', $config) || $config['radio_browser']) {
                    include_once 'api/radio-browser.php';
                    outputRadioStations(local: false);
                    outputRadioStations(local: true);
                }
                echo '</div>';
                ?>
            </div>
            <div id="search-tab"></div>
            <div id="album-tab"></div>
            <div id="lyrics-tab"><button class="button" id="remove-lyrics"><?php echo text('remove_lyrics'); ?></button>
                <div id="upload-lyrics"><img src="svg/not_found.svg">
                    <h2><?php echo text('add_lyrics'); ?></h2><button class="button" id="paste-lyrics"><?php echo text('paste_lyrics'); ?></button><label class="button" for="lrc-upload"><?php echo text('upload_lrc'); ?></label><button class="button" id="lrclib-lookup"><?php echo text('lrclib_lookup'); ?></button><input id="lrc-upload" type="file" accept=".lrc, .txt">
                </div>
                <div id="lyric-container"></div>
            </div>
            <div id="artist-tab"></div>
            <div id="profile-tab">
            </div>
        </div>
        <div data-resize="track-info-and-queue-width" class="resize"></div>
        <div id="track-extra-info" class="open">
            <div>
                <h3></h3>
                <?php
                if (!array_key_exists('uploads_enabled', $config) || $config['uploads_enabled']) {
                    echo '<label for="new-track-image"><img src="svg/placeholder.svg"></label><input id="new-track-image" type="file" accept="image/*">';
                } else {
                    echo '<img src="svg/placeholder.svg">';
                } ?>
                <div class="song">
                    <input type="text">
                    <input type="text">
                </div>
                <div id="metadata"></div>
            </div>
        </div>
        <div id="queue-list">
            <div>
                <h3><?php echo text('queue'); ?></h3>
                <div id="queue-listview"></div>
            </div>
        </div>
        <div id="friend-list" class="has-content">
            <div>
                <h3><?php echo text('friends'); ?></h3>
                <div id="friend-listview"></div>
            </div>
        </div>
    </div>
    <div id="player">
        <audio></audio>
        <div class="track-info">
            <img id="album-image" src="svg/placeholder.svg">
            <div>
                <h3 class="name"></h3>
                <p class="artist"></p>
            </div>
            <button data-event="toggleFavorite"></button>
            <button data-event="togglePlay"></button>
        </div>
        <div class="playback-controls">
            <div>
                <div class="playback-buttons">
                    <button data-event="toggleRandom"></button>
                    <button data-event="queuePrevious"></button>
                    <button data-event="togglePlay"></button>
                    <button data-event="queueNext"></button>
                    <button data-event="toggleRepeat"></button>
                </div>
                <div class="playback-slider">
                    <span class="currentTime"></span>
                    <input class="progress" type="range">
                    <span class="duration"></span>
                </div>
            </div>
        </div>
        <div class="advanced-controls">
            <button data-event="slower"></button>
            <p class="playbackRate"></p>
            <button data-event="faster"></button>
            <button id="lyrics"></button>
            <button id="info" class="active"></button>
            <button id="queue"></button>
            <button data-event="toggleMuted"></button>
            <input class="volume" type="range" max="100" value="100">
            <button id="fullscreen"></button>
        </div>
    </div>
    <div id="mobile-navbar"><button></button><button></button><button></button></div>
    <div id="notification-container"></div>
    <script>
        let tracks = <?php echo json_encode($tracks); ?>;
        let albums = <?php echo json_encode($albums); ?>;
    </script>
    <script>
        let translations = <?php echo $translationJSON; ?>;
    </script>
    <script src="resources/player.js"></script>
    <script src="resources/script.js"></script>
    <script src="resources/upload.js"></script>
    <?php
    if ($spotifyEnabled) {
        echo '<script src="resources/spotify.js"></script>';
    }
    if ($notifications != '') {
        echo '<script>' . $notifications . '</script>';
    }
    ?>
    </body>

</html>