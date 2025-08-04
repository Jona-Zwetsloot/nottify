<?php
// Get profile info and stats. Returns HTML.
require_once '../config.php';

$spotifyEnabled = isset($spotify) && $spotify['enabled'] && !empty($_SESSION['spotify_token']);
if ($spotifyEnabled) {
    $spotifyProfile = requestURL('https://api.spotify.com/v1/me', 'spotify');
}
$profilePicture = (isset($spotifyProfile, $spotifyProfile['images'], $spotifyProfile['images'][0], $spotifyProfile['images'][0]['url']) ? $spotifyProfile['images'][0]['url'] : null);

$content = file_get_contents('../library/data.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid data.json']));
}
$data = json_decode($content, true);
$content = file_get_contents('../library/tracks.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid tracks.json']));
}
$tracks = json_decode($content, true);
$content = file_get_contents('../library/albums.json', true);
if (!json_validate($content)) {
    exit(json_encode(['error' => 'Invalid albums.json']));
}
$albums = json_decode($content, true);

echo '<div id="profile-background"></div>';

echo '<div id="profile-info">' . ($profilePicture == null ? '' : '<img class="picture" src="' . $profilePicture . '">') . '<h1>' . (isset($spotifyProfile, $spotifyProfile['display_name']) ? filter_var($spotifyProfile['display_name'], FILTER_SANITIZE_SPECIAL_CHARS) : text('profile')) . '</h1></div>';

$trackCount = [];
$artistCount = [];
$albumCount = [];
$totalDuration = 0;
if (!isset($data['listened_tracks'])) {
    $data['listened_tracks'] = [];
}
$hourDistribution = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
$monthDistribution = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
$range = 'lifetime';
if (isset($_GET['range']) && ($_GET['range'] == 'day' || $_GET['range'] == 'week' || $_GET['range'] == 'four-weeks' || $_GET['range'] == 'six-months' || $_GET['range'] == 'year' || $_GET['range'] == 'lifetime')) {
    $range = $_GET['range'];
}
$i = 0;
$time = time();
foreach ($data['listened_tracks'] as $track) {
    if (
        ($range == 'day' && date('d-m-Y', $time) != date('d-m-Y', $track['time'])) ||
        ($range == 'week' && date('W-Y', $time) != date('W-Y', $track['time'])) ||
        ($range == 'four-weeks' && $track['time'] < $time - 60 * 60 * 24 * 7 * 4) ||
        ($range == 'six-months' && $track['time'] < $time - 60 * 60 * 24 * 30.5 * 6) ||
        ($range == 'year' && $track['time'] < $time - 60 * 60 * 24 * 365.25)
    ) {
        break;
    }
    $i++;
    $hourDistribution[$track['hour']]++;
    $monthDistribution[$track['month']]++;
    if (array_key_exists($track['track'], $tracks)) {

        if (!array_key_exists($track['track'], $trackCount)) {
            $trackCount[$track['track']] = 0;
        }
        $trackCount[$track['track']]++;

        if (isset($tracks[$track['track']]['meta'], $tracks[$track['track']]['meta']['artist'])) {
            $artists = explode(', ', $tracks[$track['track']]['meta']['artist']);
            foreach ($artists as $artist) {
                if (!array_key_exists($artist, $artistCount)) {
                    $artistCount[$artist] = 0;
                }
                $artistCount[$artist]++;
            }
        }

        if (!empty($track['duration'])) {
            $totalDuration += $track['duration'];
        }

        if (!empty($track['album'])) {
            if (!array_key_exists($track['album'], $albumCount)) {
                $albumCount[$track['album']] = 0;
            }
            $albumCount[$track['album']]++;
        }
    }
}
write_file('../library/data.json', json_encode($data));
arsort($trackCount);
arsort($artistCount);

echo '<div class="stats"><div><div><h3>' . $i . '</h3><p>' . text('streams') . '</p></div><div><h3>' . floor($totalDuration / 60) . '</h3><p>' . text('minutes_streamed') . '</p></div><div><h3>' . floor($totalDuration / 60 / 60) . '</h3><p>' . text('hours_streamed') . '</p></div><div><h3>' . count($artistCount) . '</h3><p>' . text('different_artists') . '</p></div><div><h3>' . count($trackCount) . '</h3><p>' . text('different_tracks') . '</p></div><div><h3>' . count($albumCount) . '</h3><p>' . text('different_albums') . '</p></div></div><select class="button"><option' . ($range == 'day' ? ' selected' : '') . ' value="day">' . text('today') . '</option><option' . ($range == 'week' ? ' selected' : '') . ' value="week">' . text('week') . '</option><option' . ($range == 'four-weeks' ? ' selected' : '') . ' value="four-weeks">' . text('4_weeks') . '</option><option' . ($range == 'six-months' ? ' selected' : '') . ' value="six-months">' . text('6 months') . '</option><option' . ($range == 'year' ? ' selected' : '') . ' value="year">' . text('1_year') . '</option><option' . ($range == 'lifetime' ? ' selected' : '') . ' value="lifetime">' . text('lifetime') . '</option></select></div>';

if (!empty($trackCount)) {
    echo '<div class="section"><div><h1>' . text('top_tracks') . '</h1><h3>' . str_replace('<count>', count($data['listened_tracks']), text('top_tracks_description')) . '</h3></div><button><img src="svg/grid.svg"></button><button class="small-button"><img src="svg/back.svg" style="transform: translateX(2px)"></button><button class="small-button"><img src="svg/for.svg"></button></div><div class="carrousel"><div>';

    $i = 1;
    foreach ($trackCount as $key => $value) {
        echo '<div onclick="player.play(\'' . filter_var(str_replace('\'', '\\\'', $key), FILTER_SANITIZE_SPECIAL_CHARS) . '\');"><img loading="lazy" src="' . filter_var(isset($tracks[$key]['pictures'][0], $tracks[$key]['pictures'][0]['url']) ? $tracks[$key]['pictures'][0]['url'] : 'svg/placeholder.svg', FILTER_SANITIZE_SPECIAL_CHARS) . '"><h3>' . $i . '. ' . filter_var($tracks[$key]['meta']['title'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . $value . ' streams</p></div>';
        $i++;
    }
    echo '</div></div>';
}


if (!empty($artistCount)) {
    echo '<div class="section"><div><h1>' . text('top_artists') . '</h1><h3>' . str_replace('<count>', count($data['listened_tracks']), text('top_artists_description')) . '</h3></div><button><img src="svg/grid.svg"></button><button class="small-button"><img src="svg/back.svg" style="transform: translateX(3px)"></button><button class="small-button"><img src="svg/for.svg"></button></div><div class="carrousel carrousel-rounded"><div>';
    $i = 1;
    foreach ($artistCount as $key => $value) {
        $artist = ['picture' => 'svg/placeholder.svg'];
        $artistFile = '../cache/artists/' . preg_replace($config['folder_regex'], '', $key) . '.json';

        if (file_exists($artistFile)) {
            $content = file_get_contents($artistFile, true);
            if (!json_validate($content)) {
                exit(json_encode(['error' => 'Invalid artist JSON']));
            }
            $artist = json_decode($content, true);
        }
        echo '<div onclick="openArtist(\'' . filter_var(str_replace('\'', '\\\'', $key), FILTER_SANITIZE_SPECIAL_CHARS) . '\')"><img loading="lazy" src="' . filter_var($artist['picture'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><h3>' . $i . '. ' . filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . $value . ' streams</p></div>';
        $i++;
    }
    echo '</div></div>';
}

if (max($hourDistribution) != 0) {
    echo '<div class="section"><div><h1>' . text('listening_clocks') . '</h1><h3>' . text('listening_clocks_description') . '</h3></div></div><div id="clocks">';
    echo '<div class="clock" style="--rotation-offset: 15;"><div class="inner">';
    for ($i = 0; $i < 24; $i++) {
        echo '<div style="--child: ' . $i . ';">' . ($i % 6 == 0 ? '<span class="pos-' . ($i == 0 ? 'top' : ($i == 6 ? 'right' : ($i == 12 ? 'bottom' : 'left'))) . '">' . $i . '</span>' : '') . '</div>';
    }
    echo '</div><div class="outer">';
    $i = 0;
    foreach ($hourDistribution as $streamsInHour) {
        echo '<div style="--child: ' . $i . ';--fill-percentage: ' . ($streamsInHour / max($hourDistribution) * 100) . '%;' . ($streamsInHour == 0 ? 'visibility: hidden;' : '') . '"><div data-title="' . $i . ':00 - ' . ($i + 1) . ':00: ' . $streamsInHour . ' streams"></div></div>';
        $i++;
    }
    echo '</div></div>';

    if (max($monthDistribution) != 0) {

        function intToMonth($int)
        {
            switch ($int) {
                case 0:
                    return text('january');
                case 1:
                    return text('february');
                case 2:
                    return text('march');
                case 3:
                    return text('april');
                case 4:
                    return text('may');
                case 5:
                    return text('june');
                case 6:
                    return text('july');
                case 7:
                    return text('august');
                case 8:
                    return text('september');
                case 9:
                    return text('october');
                case 10:
                    return text('november');
                case 11:
                    return text('december');
            }
        }
        echo '<div class="clock" style="--rotation-offset: 30;"><div class="inner">';
        for ($i = 0; $i < 12; $i++) {
            echo '<div style="--child: ' . $i . ';">' . ($i % 3 == 0 ?
                ('<span class="pos-' . ($i == 0 ? 'top' : ($i == 3 ? 'right' : ($i == 6 ? 'bottom' : 'left'))) . '">' . ($i == 0 ? 'jan' : ($i == 3 ? 'apr' : ($i == 6 ? 'jun' : 'okt'))) . '</span>')
                : '') . '</div>';
        }
        echo '</div><div class="outer">';
        $i = 0;
        foreach ($monthDistribution as $streamsInMonth) {
            echo '<div style="--child: ' . $i . ';--fill-percentage: ' . ($streamsInMonth / max($monthDistribution) * 100) . '%;' . ($streamsInMonth == 0 ? 'visibility: hidden;' : '') . '"><div data-title="' . intToMonth($i) . ': ' . $streamsInMonth . ' streams"></div></div>';
            $i++;
        }
        echo '</div></div>';
    }
    echo '</div>';
}

if (!empty($trackCount)) {
    echo '<div class="section"><div><h1>' . text('recent_tracks') . '</h1><h3>' . text('recent_tracks_description') . '</h3></div></div>';
    $i = 0;
    foreach ($data['listened_tracks'] as $track) {
        $i++;
        if (
            $i > 10 ||
            ($range == 'day' && date('d-m-Y', $time) != date('d-m-Y', $track['time'])) ||
            ($range == 'week' && date('W-Y', $time) != date('W-Y', $track['time'])) ||
            ($range == 'four-weeks' && $track['time'] < $time - 60 * 60 * 24 * 7 * 4) ||
            ($range == 'six-months' && $track['time'] < $time - 60 * 60 * 24 * 30.5 * 6) ||
            ($range == 'year' && $track['time'] < $time - 60 * 60 * 24 * 365.25)
        ) {
            break;
        }
        $id = $track['track'];
        echo '<div class="album no-album-but-actually-track" data-id="' . filter_var($id, FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var(isset($tracks[$id]['pictures'][0]) ? ($tracks[$id]['pictures'][0]['url'] . '?v=' . $tracks[$id]['pictures'][0]['version']) : 'svg/placeholder.svg', FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var(isset($tracks[$id]['meta'], $tracks[$id]['meta']['title']) ? $tracks[$id]['meta']['title'] : text('unknown_title'), FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var(isset($tracks[$id]['meta'], $tracks[$id]['meta']['artist']) ? $tracks[$id]['meta']['artist'] : text('unknown_artist'), FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></div>';
    }
}

echo '<br><h3>' . text('connection') . '</h3><div class="button-flex-container">';

if (isset($spotify) && $spotify['enabled']) {
    $connect = empty($_SESSION['spotify_token']);
    echo '<a href="spotify.php' . ($connect ? '' : '?disconnect=true') . '" class="button button-flex"><img src="svg/spotify.svg"><p>' . text(($connect ? 'connect' : 'disconnect') . '_spotify') . '</p></a>';
}
if (isset($lastfm, $lastfm['enabled'], $lastfm['apikey']) && $lastfm['enabled']) {
    $connect = empty($_SESSION['lastfm_token']);
    echo '<a href="lastfm.php' . ($connect ? '' : '?disconnect=true') . '" class="button button-flex"><img src="svg/lastfm.svg"><p>' . text(($connect ? 'connect' : 'disconnect') . '_lastfm') . '</p></a>';
}

echo '</div>';
