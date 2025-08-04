<?php
// Get artist info. Returns HTML.
require_once '../config.php';

if (empty($_GET['q'])) {
    exit;
}

$artistName = empty($_GET['q']) ? '' : $_GET['q'];
$artist = getArtist($artistName);

echo '<img class="picture" src="' . filter_var($artist['picture'], FILTER_SANITIZE_SPECIAL_CHARS) . '">';
if (empty($artist['banner'])) {
    echo '<div id="banner"></div>';
} else {
    echo '<img id="banner" src="' . filter_var($artist['banner'], FILTER_SANITIZE_SPECIAL_CHARS) . '">';
}
echo '<h1>' . filter_var($artist['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h1>';
echo '<div id="description-and-info-wrapper">';
if (!empty($artist['description']) && $artist['description'] != '<div id="description"><p>?</p></div>') {
    echo $artist['description'];
}
if (count($artist['facts']) != 0) {
    echo '<div id="detailed-artist-info">';
    foreach ($artist['facts'] as $key => $value) {
        echo '<div><p>' . filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS) . '</p><p>' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div>';
    }
    echo '</div>';
}
echo '</div>';

if (count($artist['socials']) != 0) {
    echo '<br><div id="socials">';
    foreach ($artist['socials'] as $key => $value) {
        echo '<a href="' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '"><img src="svg/' . $key . '.svg"></a>';
    }
    echo '</div>';
}
echo '<br>';
echo '<h3 id="in-library"></h3>';

if (count($artist['popular']) != 0) {
    echo '<br><h3 id="popular-tracks"></h3><div class="other-tracks">';
    foreach ($artist['popular'] as $track) {
        if (str_starts_with($track['url'], 'spotify:')) {
            echo '<div class="tile spotify-content" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var($track['image'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><div><div class="play" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></div></div>';
        } else {
            echo '<a target="_blank" href="' . filter_var($track['url'], FILTER_SANITIZE_SPECIAL_CHARS) . '" class="tile search-result">' . (empty($track['image']) ? '' : '<img loading="lazy" src="api/image-proxy?url=' . filter_var(rawurlencode($track['image']), FILTER_SANITIZE_SPECIAL_CHARS) . '">') . '<div><h3 title="' . filter_var($track['full_name'], FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></a>';
        }
    }
    echo '</div>';
}

if (count($artist['albums']) != 0) {
    echo '<br><h3 id="more-from-artist"></h3><div class="other-tracks">';
    foreach ($artist['albums'] as $track) {
        if (str_starts_with($track['url'], 'spotify:')) {
            echo '<div class="tile spotify-content" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var($track['image'], FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div><div><div class="play" data-id="' . filter_var($track['id'], FILTER_SANITIZE_SPECIAL_CHARS) . '"></div></div></div>';
        } else {
            echo '<a target="_blank" href="' . filter_var($track['url'], FILTER_SANITIZE_SPECIAL_CHARS) . '" class="tile search-result">' . (empty($track['image']) ? '' : '<img loading="lazy" src="api/image-proxy?url=' . filter_var(rawurlencode($track['image']), FILTER_SANITIZE_SPECIAL_CHARS) . '">') . '<div><h3 title="' . filter_var($track['full_name'], FILTER_SANITIZE_SPECIAL_CHARS) . '">' . filter_var($track['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var($track['date'], FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></a>';
        }
    }
    echo '</div>';
}
