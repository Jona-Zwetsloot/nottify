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
        if (array_key_exists('curl_verify_ssl_certificates', $config) && !$config['curl_verify_ssl_certificates']) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        $output = curl_exec($ch);
        if ($output === false) {
            exitMessage('Curl error', curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status < 200 || $status >= 300) {
            exitMessage('Invalid status code (' . $status . ')', $output);
        }

        $result = json_decode($output, true);
        if (isset($result['session']['key'])) {
            $_SESSION['lastfm_token'] = $result['session']['key'];
            $_SESSION['lastfm_user'] = $result['session']['name'];
            $_SESSION['lastfm_subscribers'] = $result['session']['subscriber'];
            exitMessage('Success', 'Your Last.fm account has been added.', ['href' => './', 'text' => 'Back to nottify']);
        } else {
            exitMessage('Could not retrieve session key', $output);
        }
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
