<?php

/**
 * Stand-in for the published rating lists. PHP's built-in web server runs
 * this for every request; it serves whatever the tests last put in the feed
 * folder (see Env::feed), with the status and date they asked for.
 */

$dir  = getenv('ISORA_FEED_DIR');
$name = basename((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '.csv');
$file = $dir . '/' . $name . '.csv';

if (!$dir || !preg_match('/^[a-z]+$/', $name) || !is_file($file)) {
    http_response_code(404);
    echo 'Not found';

    return;
}

$meta = json_decode((string) @file_get_contents($dir . '/' . $name . '.json'), true) ?: [];

http_response_code((int) ($meta['status'] ?? 200));
header('Content-Type: text/csv');

if (!empty($meta['last_modified'])) {
    header('Last-Modified: ' . $meta['last_modified']);
}

readfile($file);
