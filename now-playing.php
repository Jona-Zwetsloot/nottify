<?php
require_once 'config.php';

if (!isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) || !$lastfm['enabled']) {
    exitMessage('Last.fm is disabled', 'The instance owner has disabled the Last.fm integration.');
}
if (!isset($_SESSION['lastfm_user'], $_GET['q'])) {
    exitMessage('No Last.fm connection', 'You\'ve not yet connected your Last.fm account.');
}

function getDateString($time)
{
    $seconds = (time() - $time);
    if ($seconds < 120) {
        // 120 seconds
        $time = $seconds;
        $afk = 's';
    } else if ($seconds < 3600) {
        // 60 minutes
        $time = round($seconds / 60);
        $afk = 'min';
    } else if ($seconds < 86400) {
        // 24 hours
        $time = round($seconds / 60 / 60);
        $afk = 'h';
    } else if ($seconds < 2592000) {
        // 30 days
        $time = round($seconds / 60 / 60 / 24);
        $afk = 'd';
    } else if ($seconds < 31557600) {
        // 1 year
        $time = round($seconds / 60 / 60 / 24 / 30.43);
        $afk = 'mo';
    } else {
        // >1 year
        $time = round($seconds / 60 / 60 / 24 / 365.25);
        $afk = 'y';
    }
    return $time . ' ' . $afk;
}

$result = requestURL('https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=' . rawurlencode($_GET['q']) . '&limit=1&api_key=' . rawurlencode($lastfm['apikey']) . '&format=json', 'lastfm', false);

if (empty($result) || !empty($result['error'])) {
    if (isset($result, $result['error']) && $result['error'] == 17) {
        // Error 17 means user has disabled recent track listing on their profile
        // When developing this I actually got this error for my own profile, because I actually had disabled it
        exit('<p>This person has track listing disabled.</p>');
    } else {
        exit('<p>Unexpected response from Last.fm' . (isset($result, $result['error']) ? ' (' . filter_var($result['error'], FILTER_SANITIZE_SPECIAL_CHARS) . ')' : '') . '</p>');
    }
}

if (!empty($result['recenttracks']) && !empty($result['recenttracks']['track']) && !empty($result['recenttracks']['track'][0])) {
    $track = $result['recenttracks']['track'][0];
    $isPlaying = isset($track['@attr']) && isset($track['@attr']['nowplaying']) && $track['@attr']['nowplaying'] == 'true';
    $isPlaying = true;
    echo '<h3>' . text($isPlaying ? 'now_playing' : 'recently_played') . '</h3>';
    $artists = '<a title="' . filter_var($track['artist']['#text'], FILTER_SANITIZE_SPECIAL_CHARS) . '" href="' . filter_var(preg_replace('/\/_\/.*/', '', $track['url']), FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['artist']['#text'], FILTER_SANITIZE_SPECIAL_CHARS) . '</a>';

    // I like progress bars. Last.fm does not give the users progress. I don't care. I want my progress bar!!!
    // So just fake it. Assume the track is 3 min long, and the users progress is somewhere around 0-2 min.

    // "You really think someone would do that? Just go on the internet and tell lies?"
    // Yes. Never trust the developer.

    if (empty($track['duration'])) {
        $track['duration'] = 1000 * 60 * 3; // assume 3 min
    }
    if (empty($track['progress'])) {
        $track['progress'] = mt_rand(0, 120) * 1000; // assume somewhere between 0-2 min
    }
    $progressWidth = round($track['progress'] / $track['duration'] * 100);
    echo '<div class="music-track"><div class="progress" data-step="' . ($isPlaying ? (1000 / $track['duration'] * 100) : 0) . '" data-width="' . ($isPlaying ? $progressWidth : 0) . '"></div><img src="image-proxy?url=' . filter_var(rawurlencode($track['image'][2]['#text']), FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3 title="' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><a href="' . filter_var($track['url'], FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</a></h3><p>' . $artists . '</p></div><p>' . ($isPlaying ? 'now' : (isset($track['date'], $track['date']['uts']) ? getDateString($track['date']['uts']) : '')) . '</p></div>';
}
