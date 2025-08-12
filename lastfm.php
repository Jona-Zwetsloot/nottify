<?php
require_once 'config.php';

if (!isset($lastfm, $lastfm['enabled'], $lastfm['apikey'], $lastfm['secret']) || !$lastfm['enabled']) {
    exitMessage(text('error'), text('lastfm_disabled'));
}
if (isset($_GET['disconnect']) && $_GET['disconnect'] == 'true') {
    unset($_SESSION['lastfm_token']);
    unset($_SESSION['lastfm_user']);
    unset($_SESSION['lastfm_subscribers']);
    exitMessage(text('success'), str_replace('<service>', 'Last.fm', text('account_deleted')), ['href' => './', 'text' => text('back_to_nottify')]);
} else if (!empty($_GET['token'])) {
    $signature = md5('api_key' . $lastfm['apikey'] . 'methodauth.getSessiontoken' . $_GET['token'] . $lastfm['secret']);
    $url = 'https://ws.audioscrobbler.com/2.0/?method=auth.getSession&api_key=' . rawurlencode($lastfm['apikey']) . '&token=' . rawurlencode($_GET['token']) . '&api_sig=' . rawurlencode($signature) . '&format=json';
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $config['useragent']);
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
        if (isset($result['session']['key'])) {
            $_SESSION['lastfm_token'] = $result['session']['key'];
            $_SESSION['lastfm_user'] = $result['session']['name'];
            $_SESSION['lastfm_subscribers'] = $result['session']['subscriber'];
            exitMessage(text('success'), str_replace('<service>', 'Last.fm', text('account_added')), ['href' => './', 'text' => text('back_to_nottify')]);
        } else {
            exitMessage(text('unexpected_response'), $output);
        }
    } catch (Exception $e) {
        exitMessage(text('curl_error'), $e->getCode() . ', ' . $e->getMessage());
    }
} else if (!empty($_GET['error'])) {
    if ($_GET['error'] == 'access_denied') {
        exitMessage(text('access_denied'), str_replace('<service>', 'Last.fm', text('access_denied')));
    } else {
        exitMessage(text('error'), text('unexpected_response') . ' ' . $_GET['error']);
    }
} else {
    http_response_code(303);
    header('Location: https://www.last.fm/api/auth/?api_key=' . rawurlencode($lastfm['apikey']) . (empty($lastfm['redirect_uri']) ? '' : '&cb=' . rawurlencode($lastfm['redirect_uri'])));
    exit;
}
