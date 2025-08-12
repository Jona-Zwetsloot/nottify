<?php
// Connect your Spotify account.
require_once 'config.php';

if (!isset($spotify, $spotify['enabled'], $spotify['client_id'], $spotify['client_secret'], $spotify['redirect_uri']) || !$spotify['enabled']) {
    exitMessage(text('error'), text('spotify_disabled'));
}

if (isset($_GET['disconnect']) && $_GET['disconnect'] == 'true') {
    unset($_SESSION['spotify_token']);
    unset($_SESSION['spotify_refresh']);
    unset($_SESSION['spotify_expires']);
    unset($_SESSION['spotify_time']);
    unset($_SESSION['spotify_state']);
    unset($_SESSION['spotify_progress']);
    exitMessage(text('success'), str_replace('<service>', 'Spotify', text('account_deleted')), ['href' => './', 'text' => text('back_to_nottify')]);
} else if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || empty($_SESSION['spotify_state']) || $_GET['state'] != $_SESSION['spotify_state']) {
        exitMessage(text('state_error'), text('state_error_description'), ['href' => 'spotify', 'text' => text('try_again')]);
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
            if (array_key_exists('curl_use_default_cacert', $config) && !$config['curl_use_default_cacert']) {
                curl_setopt($ch, CURLOPT_CAINFO, str_replace('\\', '/', dirname(__FILE__)) . '/resources/cacert.pem');
            }
            if (array_key_exists('curl_verify_ssl_certificates', $config) && !$config['curl_verify_ssl_certificates']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            }
            $output = curl_exec($ch);
            if ($output === false) {
                exitMessage(text('curl_error'), curl_error($ch));
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status < 200 || $status >= 300) {
                exitMessage(str_replace('<status>', $status, text('invalid_status_code')), $output);
            }

            $result = json_decode($output, true);
            $accessToken = $result['access_token'];
            $refreshToken = $result['refresh_token'];
            $expiresIn = $result['expires_in'];

            if (empty($accessToken)) {
                exitMessage(text('unexpected_response'), $output);
            }

            $_SESSION['spotify_token'] = $accessToken;
            $_SESSION['spotify_refresh'] = $refreshToken;
            $_SESSION['spotify_expires'] = $expiresIn;
            $_SESSION['spotify_time'] = time();
            $_SESSION['spotify_progress'] = 0;
            unset($_SESSION['spotify_state']);

            http_response_code(303);
            header('Location: import-spotify-library');
        } catch (Exception $e) {
            exitMessage(text('curl_error'), $e->getCode() . ', ' . $e->getMessage());
        }
    }
} else if (!empty($_GET['error'])) {
    if ($_GET['error'] == 'access_denied') {
        exitMessage(text('access_denied'), str_replace('<service>', 'Spotify', text('access_denied')));
    } else {
        exitMessage(text('error'), text('unexpected_response') . ' ' . $_GET['error']);
    }
} else {
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
}
