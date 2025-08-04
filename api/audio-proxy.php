<?php
// Proxy audio stream, is used for radio streaming. Returns audio stream.

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

curl_exec($ch);
if ($output === false) {
    exitMessage('Curl error', curl_error($ch));
}
curl_close($ch);
