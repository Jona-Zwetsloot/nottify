<?php
require_once 'config.php';

if (!isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) || !$lastfm['enabled']) {
    exitMessage('Last.fm is disabled', 'The instance owner has disabled the Last.fm integration.');
}
if (isset($_GET['disconnect']) && $_GET['disconnect'] == 'true') {
    unset($_SESSION['lastfm_token']);
    unset($_SESSION['lastfm_user']);
    unset($_SESSION['lastfm_subscribers']);
    exitMessage('Success', 'Your Last.fm connection has been deleted.');
} else if (!empty($_GET['token'])) {
    try {
        $signature = md5('api_key' . $lastfm['apikey'] . 'methodauth.getSessiontoken' . $_GET['token'] . $lastfm['secret']);

        $ch = curl_init('https://ws.audioscrobbler.com/2.0/?method=auth.getSession&api_key=' . rawurlencode($lastfm['apikey']) . '&token=' . rawurlencode($_GET['token']) . '&api_sig=' . rawurlencode($signature) . '&format=json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $config['useragent']);
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        if (isset($result['session']['key'])) {
            $_SESSION['lastfm_token'] = $result['session']['key'];
            $_SESSION['lastfm_user'] = $result['session']['name'];
            $_SESSION['lastfm_subscribers'] = $result['session']['subscriber'];
        }
        exit;
        exitMessage('Success', 'Your Last.fm account has been added.', ['href' => './', 'text' => 'Back to nottify']);
    } catch (Exception $e) {
        exitMessage('Error', sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
    }
} else if (!empty($_GET['error'])) {
    if ($_GET['error'] == 'access_denied') {
        exitMessage('Access denied', 'Changed your mind? No problem. Your Last.fm account has not been connected to nottify.');
    } else {
        exitMessage('Error', 'Something happened (' + $_GET['error'] + ')');
    }
} else {
    http_response_code(303);
    header('Location: https://www.last.fm/api/auth/?api_key=' . rawurlencode($lastfm['apikey']) . (empty($lastfm['redirect_uri']) ? '' : '&cb=' . rawurlencode($lastfm['redirect_uri'])));
    exit;
}