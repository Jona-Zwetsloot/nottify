<?php
require_once 'config.php';

if (!isset($spotify, $spotify['enabled'], $spotify['client_id'], $spotify['client_secret'], $spotify['redirect_uri']) || !$spotify['enabled']) {
    exitMessage('Spotify is disabled', 'The instance owner has disabled the Spotify integration.');
}

if (isset($_GET['disconnect']) && $_GET['disconnect'] == 'true') {
    unset($_SESSION['spotify_token']);
    unset($_SESSION['spotify_refresh']);
    unset($_SESSION['spotify_expires']);
    unset($_SESSION['spotify_time']);
    unset($_SESSION['spotify_state']);
    exitMessage('Success', 'Your Spotify connection has been deleted.');
} else if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || empty($_SESSION['spotify_state']) || $_GET['state'] != $_SESSION['spotify_state']) {
        exitMessage('State error', 'The given state does not match the previously generated state.');
    } else {
        $post_data = [
            'code' => $_GET['code'],
            'redirect_uri' => $spotify['redirect_uri'],
            'grant_type' => 'authorization_code'
        ];
        try {
            $ch = curl_init('https://accounts.spotify.com/api/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'content-type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_USERPWD, $spotify['client_id'] . ':' . $spotify['client_secret']);
            $output = curl_exec($ch);
            $result = json_decode($output, true);
            $accessToken = $result['access_token'];
            $refreshToken = $result['refresh_token'];
            $expiresIn = $result['expires_in'];

            if (empty($accessToken)) {
                exitMessage('Error', 'Something went wrong. We could not retrieve your access token.');
            }

            $_SESSION['spotify_token'] = $accessToken;
            $_SESSION['spotify_refresh'] = $refreshToken;
            $_SESSION['spotify_expires'] = $expiresIn;
            $_SESSION['spotify_time'] = time();
            unset($_SESSION['spotify_state']);

            $content = file_get_contents('library/albums.json', true);
            if (!json_validate($content)) {
                exit(json_encode(['error' => 'Invalid albums.json']));
            }
            $albums = json_decode($content, true);

            $content = file_get_contents('library/tracks.json', true);
            if (!json_validate($content)) {
                exit(json_encode(['error' => 'Invalid tracks.json']));
            }
            $tracks = json_decode($content, true);

            $json = requestURL('https://api.spotify.com/v1/me/playlists?offset=0&limit=50', 'spotify');
            addAlbums($json);

            $json = requestURL('https://api.spotify.com/v1/me/albums?offset=0&limit=50', 'spotify');
            addAlbums($json);

            $json = requestURL('https://api.spotify.com/v1/me/tracks?offset=0&limit=50', 'spotify');
            addTracks(['id' => 'favorites', 'name' => 'favorites', 'images' => []], $json);
            for ($i = 0; $i < floor($json['total'] / 50); $i++) {
                $json = requestURL('https://api.spotify.com/v1/me/tracks?offset=' . (($i + 1) * 50) . '&limit=50', 'spotify');
                addTracks(['id' => 'favorites', 'name' => 'favorites', 'images' => []], $json);
            }

            write_file('library/tracks.json', json_encode($tracks));
            write_file('library/albums.json', json_encode($albums));
            exitMessage('Success', 'Your Spotify account has been added.', ['href' => './', 'text' => 'Back to nottify']);
        } catch (Exception $e) {
            exitMessage('Error', sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
        }
    }
} else if (!empty($_GET['error'])) {
    if ($_GET['error'] == 'access_denied') {
        exitMessage('Access denied', 'Changed your mind? No problem. Your Spotify account has not been connected to nottify.');
    } else {
        exitMessage('Error', 'Something happened (' + $_GET['error'] + ')');
    }
} else if (empty($_SESSION['spotify_state'])) {
    $_SESSION['spotify_state'] = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(16 / strlen($x)))), 1, 16);

    http_response_code(303);
    $scopes = [
        'ugc-image-upload',
        'user-read-playback-state',
        'user-modify-playback-state',
        'user-read-currently-playing',
        'app-remote-control',
        'streaming',
        'playlist-read-private',
        'playlist-read-collaborative',
        'playlist-modify-private',
        'playlist-modify-public',
        'user-follow-modify',
        'user-follow-read',
        'user-read-playback-position',
        'user-top-read',
        'user-read-recently-played',
        'user-library-modify',
        'user-library-read',
        'user-read-email',
        'user-read-private',

    ];
    header('Location: https://accounts.spotify.com/authorize?response_type=code&client_id=' . $spotify['client_id'] . '&scope=' . rawurlencode(implode(' ', $scopes)) . '&redirect_uri=' . rawurlencode($spotify['redirect_uri']) . '&state=' . $_SESSION['spotify_state']);
    exit;
} else {
    exitMessage('Already connected', 'You\'ve already connected your Spotify account.');
}
