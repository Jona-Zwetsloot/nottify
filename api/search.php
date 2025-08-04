<?php
// Search library. Returns HTML.
require_once '../config.php';

if (empty($_GET['q'])) {
    exit;
}

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

function searchObject($obj, $search)
{
    $search = strtolower($search);
    foreach ($obj as $key => $value) {
        if (is_array($value)) {
            if (searchObject($value, $search)) {
                return true;
            }
        } else if (!empty($value) && is_string((string)$value) && str_contains(strtolower($value), $search)) {
            return true;
        }
    }
    return false;
}

$searchTracks = [];
$searchArtists = [];
$tempArtistArray = [];
$i = 1;
$_GET['q'] = strtolower($_GET['q']);
$searches = explode(' ', $_GET['q']);
foreach ($tracks as $track => $_) {
    $priority = 0;
    foreach ($searches as $search) {
        $search = strtolower($search);

        if (isset($tracks[$track]['meta'], $tracks[$track]['meta']['artist']) && $tracks[$track]['meta']['artist'] && str_contains(strtolower($tracks[$track]['meta']['artist']), $search)) {
            foreach (explode(', ', $tracks[$track]['meta']['artist']) as $artist) {
                if (str_contains(strtolower($artist), $search)) {
                    if (in_array($artist, $tempArtistArray)) {
                        if (str_contains(strtolower($artist), $_GET['q'])) {
                            for ($i = 0; $i < count($searchArtists); $i++) {
                                if ($searchArtists[$i]['name'] == $artist) {
                                    $searchArtists[$i]['priority'] += 10;
                                    break;
                                }
                            }
                        }
                    } else {
                        array_push($tempArtistArray, $artist);
                        array_push($searchArtists, [
                            'name' => $artist,
                            'priority' => str_contains(strtolower($artist), $_GET['q']) ? 100 : 3,
                        ]);
                    }
                }
            }
        }

        if (isset($tracks[$track]['meta'], $tracks[$track]['meta']['title']) && str_contains(strtolower($tracks[$track]['meta']['title']), $_GET['q'])) {
            $priority += 4;
        } else if (isset($tracks[$track]['meta'], $tracks[$track]['meta']['title']) && str_contains(strtolower($tracks[$track]['meta']['title']), $search)) {
            $priority += 3;
        } else if (isset($tracks[$track]['meta'], $tracks[$track]['meta']['artist']) && str_contains(strtolower($tracks[$track]['meta']['artist']), $search)) {
            $priority += 2;
        } else if (searchObject($tracks[$track], $search)) {
            $priority += 1;
        }
    }
    if ($priority > 0) {
        array_push($searchTracks, [
            'id' => $track,
            'number' => $i,
            'priority' => $priority,
        ]);
        $i++;
    }
}
usort($searchTracks, function ($a, $b) {
    return $b['priority'] <=> $a['priority'];
});
$searchTracks = array_slice($searchTracks, 0, 24);

$searchAlbums = [];
$i = 1;
foreach ($albums as $album => $_) {
    $priority = 0;
    if (isset($albums[$album]['name']) && str_contains(strtolower($albums[$album]['name']), $_GET['q'])) {
        $priority += 10;
    } else if (searchObject($albums[$album], $_GET['q'])) {
        $priority += 3;
    }
    if ($priority > 0) {
        array_push($searchAlbums, [
            'id' => $album,
            'priority' => $priority,
        ]);
        $i++;
    }
}
usort($searchAlbums, function ($a, $b) {
    return $b['priority'] <=> $a['priority'];
});
usort($searchArtists, function ($a, $b) {
    return $b['priority'] <=> $a['priority'];
});

$searchAlbums = array_slice($searchAlbums, 0, 24);
$searchArtists = array_slice($searchArtists, 0, 24);

echo '<div>';
echo '</div>';

if (count($searchArtists) > 0 && (count($searchTracks) == 0 || $searchArtists[0]['priority'] > $searchTracks[0]['priority']) && (count($searchAlbums) == 0 || $searchArtists[0]['priority'] > $searchAlbums[0]['priority'])) {
    $title = $searchArtists[0]['name'];
    $text = 'Artist';
    $artist = getArtist($searchArtists[0]['name']);
    $image = $artist['picture'];
    $click = 'openArtist(\'' . filter_var(str_replace('\'', '\\\'', $searchArtists[0]['name'])) . '\')';
} else if (count($searchAlbums) > 0 && (count($searchTracks) > 0 || $searchAlbums[0]['priority'] > $searchTracks[0]['priority'])) {
    $title = $albums[$searchAlbums[0]['id']]['name'];
    $text = 'Album';
    $image = isset($albums[$searchAlbums[0]['id']]['picture']) ? ($albums[$searchAlbums[0]['id']]['picture']['url'] . '?v=' . $albums[$searchAlbums[0]['id']]['picture']['version']) : 'svg/placeholder.svg';
    $click = 'openAlbum(\'' . filter_var(str_replace('\'', '\\\'', $searchAlbums[0]['id'])) . '\', true)';
} else if (count($searchTracks) > 0) {
    $title = $tracks[$searchTracks[0]['id']]['meta']['title'];
    $text = isset($tracks[$searchTracks[0]['id']]['meta']['artist']) ? $tracks[$searchTracks[0]['id']]['meta']['artist'] : text('unknown_artist');
    $image = isset($tracks[$searchTracks[0]['id']]['pictures'][0]) ? ($tracks[$searchTracks[0]['id']]['pictures'][0]['url'] . '?v=' . $tracks[$searchTracks[0]['id']]['pictures'][0]['version']) : 'svg/placeholder.svg';
    $click = 'player.play(\'' . filter_var(str_replace('\'', '\\\'', $searchTracks[0]['id'])) . '\')';
}

if (isset($title)) {
    echo '<div id="search-flex"><div id="top-result"><h2>Top result</h2><div onclick="' . $click . '"><img src="' . filter_var($image, FILTER_SANITIZE_SPECIAL_CHARS) . '"><h1>' . filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS) . '</h1><p>' . filter_var($text, FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></div><div id="search-results"><h2>Tracks</h2>';
    if (count($searchTracks) > 0) {
        $i = 0;
        foreach ($searchTracks as $track) {
            $i++;
            if ($i > 4) {
                break;
            }
            $id = $track['id'];
            echo '<div class="album no-album-but-actually-track" data-id="' . filter_var($id, FILTER_SANITIZE_SPECIAL_CHARS) . '"><img loading="lazy" src="' . filter_var(isset($tracks[$id]['pictures'][0]) ? ($tracks[$id]['pictures'][0]['url'] . '?v=' . $tracks[$id]['pictures'][0]['version']) : 'svg/placeholder.svg', FILTER_SANITIZE_SPECIAL_CHARS) . '"><div><h3>' . filter_var(isset($tracks[$id]['meta'], $tracks[$id]['meta']['title']) ? $tracks[$id]['meta']['title'] : text('unknown_title'), FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var(isset($tracks[$id]['meta'], $tracks[$id]['meta']['artist']) ? $tracks[$id]['meta']['artist'] : text('unknown_artist'), FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div></div>';
        }
    }
    echo '</div></div>';
} else {
    exitMessage(text('no_results'), text('no_results_found'));
}

if (count($searchTracks) > 0) {
    echo '<div class="section"><div><h2>Tracks</h2></div><button><img src="svg/grid.svg"></button><button class="small-button"><img src="svg/back.svg" style="transform: translateX(2px)"></button><button class="small-button"><img src="svg/for.svg"></button></div><div class="carrousel"><div>';
    foreach ($searchTracks as $track) {
        echo '<div onclick="player.play(\'' . filter_var(str_replace('\'', '\\\'', $track['id']), FILTER_SANITIZE_SPECIAL_CHARS) . '\');"><img loading="lazy" src="' . filter_var(isset($tracks[$track['id']]['pictures'][0]) ? ($tracks[$track['id']]['pictures'][0]['url'] . '?v=' . $tracks[$track['id']]['pictures'][0]['version']) : 'svg/placeholder.svg', FILTER_SANITIZE_SPECIAL_CHARS) . '"><h3>' . filter_var(isset($tracks[$track['id']]['meta'], $tracks[$track['id']]['meta']['title']) ? $tracks[$track['id']]['meta']['title'] : text('unknown_title'), FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>' . filter_var(isset($tracks[$track['id']]['meta'], $tracks[$track['id']]['meta']['artist']) ? $tracks[$track['id']]['meta']['artist'] : text('unknown_artist'), FILTER_SANITIZE_SPECIAL_CHARS) . '</p></div>';
    }
    echo '</div></div>';
}

if (count($searchAlbums) > 0) {
    echo '<div class="section"><div><h2>Albums</h2></div><button><img src="svg/grid.svg"></button><button class="small-button"><img src="svg/back.svg" style="transform: translateX(2px)"></button><button class="small-button"><img src="svg/for.svg"></button></div><div class="carrousel"><div>';
    foreach ($searchAlbums as $album) {
        echo '<div onclick="openAlbum(\'' . filter_var(str_replace('\'', '\\\'', $album['id']), FILTER_SANITIZE_SPECIAL_CHARS) . '\', true);"><img loading="lazy" src="' . filter_var(isset($albums[$album['id']]['picture']) ? ($albums[$album['id']]['picture']['url'] . '?v=' . $albums[$album['id']]['picture']['version']) : 'svg/placeholder.svg', FILTER_SANITIZE_SPECIAL_CHARS) . '"><h3>' . filter_var(isset($albums[$album['id']]['name']) ? $albums[$album['id']]['name'] : text('unknown_title'), FILTER_SANITIZE_SPECIAL_CHARS) . '</h3><p>Album</p></div>';
    }
    echo '</div></div>';
}
