<?php
// API SETUP

// Spotify requires you to create a free API account, see https://developer.spotify.com/dashboard/create
// To actually use it to play stuff, Spotify requires you to have an active premium subscription
// Your Spotify library will still be added to nottify if you connect a Spotify Free account, but tracks will be unplayable
$spotify = [
    'enabled' => true,
    'client_id' => null,
    'client_secret' => null,

    // IMPORTANT: Set "redirect_uri" to the spotify page in this directory. Also enter the same redirect uri when creating a Spotify API client.
    // For example, if you host on localhost in the root directory it should be http://127.0.0.1:80/spotify, and in a subdirectory http://127.0.0.1:80/some-subdirectory-here/spotify
    'redirect_uri' => null,
];

// Genius requires you to create a free API account, see https://genius.com/api-clients/new
$genius = [
    'enabled' => true,
    'apikey' => null,
];

// Last.fm requires you to create a free API account, see https://www.last.fm/api/account/create
$lastfm = [
    'enabled' => true,
    'apikey' => null,
    'secret' => null,

    // Leave empty to use Last.fm redirect url you've used when creating your API client
    // The redirect page is lastfm.php in this directory
    'redirect_uri' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . preg_replace('/\/[^\/]*$/', '', $_SERVER['REQUEST_URI']) . '/lastfm.php',
];


// GENERAL SETTINGS

$config = [
    // Set the useragent for your nottify instance
    'useragent' => 'nottify audio player/1.0.1 ( https://github.com/Jona-Zwetsloot/nottify )',

    // Which theme should the client use? 'auto', 'light' or 'dark'.
    'theme' => 'auto',

    // Which language should the client use? 'auto', 'nl' or 'en'.
    'language' => 'auto',

    // Should CURL verify SSL certificates? Always set to true, or otherwise your computer may be at risk!
    'curl_verify_ssl_certificates' => true,

    // Should audio be normalized? Requires 'calculate_gain' to be set to true.
    'normalize_audio' => true,

    // Should the normalization gain be calculated? Slows down track uploading significantly.
    // Tracks uploaded while this is set to false will never have normalization, even if later set to true.
    'calculate_gain' => true,

    // Should Last.fm scrobble nottify tracks? Spotify content will not be scrobbled.
    // Note that the Last.fm API must also be enabled in the API settings above, otherwise scrobbling won't work
    'lastfm_scrobble' => true,

    // Should radio tracks delivered by the radio-browser API be visible on the home tab?
    'radio_browser' => true,

    // Should radio clicks be sent to the radio-browser API for their stats?
    'track_radio_clicks' => true,

    // Track total tracks listened, distribution over time and more for some nice graphs on the profile page
    'track_listening' => true,

    // Which provider should deliver artist profile info? Array can contain 'genius', 'spotify' and/or 'musicbrainz'
    // Note that the APIs must also be enabled in the API settings above, otherwise they will be turned off
    /*  ✅ Fully delivered 🟰 Partly delivered ❌ Not delivered

                        GENIUS  SPOTIFY  MUSICBRAINZ
        Image           ✅      ✅      ❌
        Banner          ✅      ❌      ❌
        Description     ✅      ❌      ❌
        Short facts     ❌      ❌      ✅
        Socials         🟰*     ❌      ✅
        Albums          🟰**    ✅      🟰***
        Followers       🟰****  ✅      ❌
        Genres          ❌      ✅      ✅
        Genius IQ       ✅      ❌      ❌

        NOT CACHED
        Duration (s)    3        13      6

        CACHED
        Duration (s)    <1       <1      <1

        * Only Facebook, Instagram, Twitter and Genius
        ** Not playable
        *** Without album images, not playable
        **** Genius follower count, not Spotify follower count
    */
    'artist_info_providers' => [
        'genius',
        'spotify',
        'musicbrainz',
    ],

    // Are users permitted to upload new tracks?
    'uploads_enabled' => true,

    // Are users permitted to delete tracks?
    'delete_enabled' => true,

    // Are users permitted to change metadata?
    'change_metadata_enabled' => true,

    // Should artist page requests to artists who aren't in the library be blocked to prevent API abuse?
    'prevent_api_abuse' => true,

    // Should uploaded ZIP files be unpacked? This is convenient, but requires JSZip to be loaded.
    // Unpacking is done client-side, so the server won't be at risk for ZIP bombs. 
    'zip_support' => true,

    // Control which characters should be removed from file and foldernames.
    // Do not allow dots and slashes, as this poses a security risk for your file system.
    'folder_regex' => '/[^A-Za-z0-9_ \(\)-]/',
];

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    header('Content-type: application/json');
    exit(json_encode(['error' => 'This is a backend PHP file. It\'s not accessible from the client-side.']));
}

// Import some important code
require_once 'include.php';
