<?php
// Proxy audio stream, is used for radio streaming. Returns audio stream.
require_once '../config.php';

$audioUrl = $_GET['url'];
$ch = curl_init($audioUrl);

// Make request with browser user agent, as this is a proxy to circumvent CORS
curl_setopt($ch, CURLOPT_USERAGENT, empty($_SERVER['HTTP_USER_AGENT']) ? $config['useragent'] : $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_BUFFERSIZE, 8192);

header("Content-Type: audio/mpeg");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

if (array_key_exists('curl_use_default_cacert', $config) && !$config['curl_use_default_cacert']) {
    curl_setopt($ch, CURLOPT_CAINFO, str_replace('\\', '/', dirname(__FILE__)) . '/resources/cacert.pem');
}
if (array_key_exists('curl_verify_ssl_certificates', $config) && !$config['curl_verify_ssl_certificates']) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
}
curl_exec($ch);
if ($output === false) {
    exitMessage('cURL error', curl_error($ch));
}
curl_close($ch);
