<?php
// Get Last.fm friend list. Returns HTML.
require_once '../config.php';

if (!isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) || !$lastfm['enabled']) {
    exit('Last.fm is disabled. The instance owner has disabled the Last.fm integration.');
}
if (!isset($_SESSION['lastfm_user'])) {
    exit('<div class="empty"><a href="lastfm.php" class="button button-flex"><img src="svg/lastfm.svg"><p>' . text('connect_lastfm') . '</p></a></div>');
}

$result = requestURL('https://ws.audioscrobbler.com/2.0/?method=user.getfriends&user=' . rawurlencode($_SESSION['lastfm_user']) . '&api_key=' . rawurlencode($lastfm['apikey']) . '&format=json', 'lastfm');

$hasFriends = false;
if (isset($result['friends'], $result['friends']['user'])) {
    foreach ($result['friends']['user'] as $friend) {
        $hasFriends = true;
        $image = 'svg/placeholder.svg';
        if (isset($friend['image'], $friend['image'][3]) && !empty($friend['image'][3]['#text'])) {
            $image = 'api/image-proxy?url=' . filter_var(rawurlencode($friend['image'][3]['#text']), FILTER_SANITIZE_SPECIAL_CHARS);
        }
        $items = [];
        if (!empty($friend['realname'])) {
            array_push($items, $friend['realname']);
        }
        if (!empty($friend['country']) && strtolower($friend['country']) != 'none') {
            array_push($items, $friend['country']);
        }
        echo '<button><img loading="lazy" src="' . $image . '"><div><h3>' . filter_var($friend['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . implode(' // ', $items) . '</p></div><div class="lastfm-playing"></div></button>';
    }
}

if (!$hasFriends) {
    echo '<div class="empty"><img src="svg/empty.svg"><p>' . text('no_friends') . '</p></div>';
}