<?php
// Circumvent CORS problems which causes images to not load
// Used to load favicons from the radio stations list provided by radio-browser

require_once 'config.php';
error_reporting(0);

function fallback()
{
    global $savePath;
    $imageContent = file_get_contents('svg/placeholder.svg', true);

    if ($imageContent === false) {
        return;
    }

    if (!file_exists($savePath)) {
        write_file($savePath, $imageContent);
    }

    // Set headers and output the image
    header('Content-Type: image/svg+xml');
    header('Cache-Control: max-age=31536000'); // Cache for 1 year
    echo $imageContent;
    exit;
}

// Get the image URL from the query string
$imageUrl = $_GET['url'];
if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false || (!str_starts_with($imageUrl, 'https://') && !str_starts_with($imageUrl, 'http://'))) {
    fallback();
}

if (!file_exists('cache')) {
    mkdir('cache', 0777, true);
}
if (!file_exists('cache/images')) {
    mkdir('cache/images', 0777, true);
}

$savePath = 'cache/images/' . preg_replace($config['folder_regex'], '', $imageUrl);

if (file_exists($savePath)) {
    $imageContent = file_get_contents($savePath, true);
} else {
    // Make request with browser user agent, as this is a proxy to circumvent CORS
    $options = [
        'http' => [
            'header' => 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT']
        ]
    ];

    // Create a stream context with the options
    $context = stream_context_create($options);

    // Get the image content
    $imageContent = file_get_contents($imageUrl, false, $context);
}

if ($imageContent === false) {
    fallback();
}

// Get the MIME type of the image
if (extension_loaded('fileinfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageContent);
} else {
    exit(json_encode(['error' => 'Please install the fileinfo PHP extension']));
}

// Validate MIME type (only allow images)
if (!str_starts_with($mimeType, 'image/')) {
    fallback();
}

if (!file_exists($savePath)) {
    write_file($savePath, $imageContent);
}

// Set headers and output the image
header('Content-Type: ' . $mimeType);
header('Cache-Control: max-age=31536000'); // Cache for 1 year
echo $imageContent;
