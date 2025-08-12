<?php
// Import Spotify library
require_once 'config.php';
$_SESSION['spotify_progress'] = 0;

if (!isset($spotify, $spotify['enabled'], $spotify['client_id'], $spotify['client_secret'], $spotify['redirect_uri']) || !$spotify['enabled'] || empty($_SESSION['spotify_token'])) {
    exitMessage(text('error'), text('spotify_disabled'));
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo text('importing_spotify_library'); ?></title>
    <link rel="stylesheet" href="resources/stylesheet.css">
    <meta name="author" content="Jona Zwetsloot">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg" href="svg/nottify.svg">
</head>

<body>
    <div id="center-message">
        <h3><?php echo text('importing_spotify_library'); ?></h3>
        <p><?php echo text('importing_spotify_library_description'); ?></p>
        <div id="import-progress">
            <div><a href="./"><?php echo text('back_to_nottify'); ?></a></div>
        </div>
        <div id="import-text"><img src="svg/placeholder.svg">
            <p><?php echo text('loading'); ?></p>
            <p>0%</p>
        </div>
    </div>
    <script src="resources/request-spotify-data.js"></script>
</body>

</html>