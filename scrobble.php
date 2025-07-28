<?php
require_once 'config.php';

if (!isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) || !$lastfm['enabled'] || !isset($_SESSION['lastfm_user'], $_SESSION['lastfm_token'], $_GET['artist'], $_GET['track'])) {
    exit(json_encode(['error' => 'Invalid request.']));
}

if (array_key_exists('lastfm_scrobble', $config) && !$config['lastfm_scrobble']) {
    exit(json_encode(['error' => 'No scrobbling.']));
}

try {
    // I really hate working with Last.fms system of authentication. It makes me feel angry and sad at the same time.
    // So no, I've not written a reusable function which neatly generates the API signature. I only need it in 2 places so this is faster.

    $time = time();
    $signature = md5('api_key' . $lastfm['apikey'] . 'artist[0]' . $_GET['artist'] . 'methodtrack.scrobblesk' . $_SESSION['lastfm_token'] . 'timestamp[0]' . $time . 'track[0]' . $_GET['track'] . $lastfm['secret']);

    $post_data = [
        'api_key' => $lastfm['apikey'],
        'artist[0]' => $_GET['artist'],
        'method' => 'track.scrobble',
        'sk' => $_SESSION['lastfm_token'],
        'timestamp[0]' => $time,
        'track[0]' => $_GET['track'],

        // Not included in signature
        'api_sig' => $signature,
        'format' => 'json'
    ];

    $ch = curl_init('https://ws.audioscrobbler.com/2.0/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'content-type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['useragent']);
    $output = curl_exec($ch);
    echo $output;
} catch (Exception $e) {
    exit(json_encode(['error' => sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage())]));
}
