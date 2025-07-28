<?php
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    exit(json_encode(['error' => 'Something went wrong.']));
}

require_once 'config.php';

if (isset($_SESSION['spotify_token'], $_SESSION['spotify_time'], $_SESSION['spotify_expires'], $_SESSION['spotify_refresh'])) {
    // If token expires within 10 seconds (or already has expired), request a new one
    if (time() - (int)$_SESSION['spotify_time'] + 10 > (int)$_SESSION['spotify_expires']) {
        $post_data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $_SESSION['spotify_refresh']
        ];
        try {
            $_SESSION['spotify_time'] = time();
            $ch = curl_init('https://accounts.spotify.com/api/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'content-type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($spotify['client_id'] . ':' . $spotify['client_secret'])
            ]);
            $output = curl_exec($ch);
            $result = json_decode($output, true);
            $accessToken = $result['access_token'];
            $refreshToken = empty($result['refresh_token']) ? $_SESSION['spotify_refresh'] : $result['refresh_token'];
            $expiresIn = $result['expires_in'];

            if (empty($accessToken)) {
                unset($_SESSION['spotify_token']);
                unset($_SESSION['spotify_refresh']);
                unset($_SESSION['spotify_expires']);
                unset($_SESSION['spotify_time']);
                unset($_SESSION['spotify_state']);
            } else {
                $_SESSION['spotify_token'] = $accessToken;
                $_SESSION['spotify_refresh'] = $refreshToken;
                $_SESSION['spotify_expires'] = $expiresIn;
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
}
