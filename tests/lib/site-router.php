<?php

/**
 * Lets PHP's built-in web server run the Joomla test site with friendly
 * addresses, doing the job that .htaccess does on a real server: a request
 * for a file that exists is served as it is, anything else goes to Joomla.
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = $_SERVER['DOCUMENT_ROOT'];

if ($path !== '/' && is_file($root . $path)) {
    return false;
}

$admin = strncmp($path, '/administrator', 14) === 0;
$index = ($admin ? '/administrator' : '') . '/index.php';

$_SERVER['SCRIPT_NAME']     = $index;
$_SERVER['PHP_SELF']        = $index;
$_SERVER['SCRIPT_FILENAME'] = $root . $index;

chdir(dirname($root . $index));

require $root . $index;
