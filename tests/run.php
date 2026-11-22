<?php

/**
 * Runs the tests that need a Joomla site. Normally started by tests/run.sh,
 * which makes the throwaway site first.
 *
 *   php tests/run.php --joomla=/path/to/site --site-url=http://127.0.0.1:8089 \
 *       --feed-url=http://127.0.0.1:8090 --feed-dir=/path/to/feeds [--suite=main|removed] [--filter=text]
 *
 * The administrator's sign-in comes from the ISORA_TEST_ADMIN_USER and
 * ISORA_TEST_ADMIN_PASS environment variables.
 */

use IsoraTests\Env;
use IsoraTests\Runner;
use Joomla\CMS\Factory;

$options = getopt('', ['joomla:', 'site-url:', 'feed-url:', 'feed-dir:', 'suite::', 'filter::']);

foreach (['joomla', 'site-url', 'feed-url', 'feed-dir'] as $needed) {
    if (empty($options[$needed])) {
        fwrite(STDERR, "Missing --$needed. See the top of tests/run.php.\n");
        exit(2);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start Joomla the way its own command-line tool does.
const _JEXEC = 1;

$site = parse_url($options['site-url']);
$_SERVER['HTTP_HOST']   = $site['host'] . (isset($site['port']) ? ':' . $site['port'] : '');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

define('JPATH_BASE', rtrim($options['joomla'], '/\\'));

require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
Factory::$application = $container->get(\Joomla\Console\Application::class);

// The tests wipe the extension's tables, so they only ever run on a database made for them.
$database = (string) Factory::getApplication()->get('db');

if (strncmp($database, 'isoratest_', 10) !== 0) {
    fwrite(STDERR, "Refusing to run: the site's database is \"$database\". The tests empty the extension's tables, so they\n"
        . "only run on a database whose name starts with \"isoratest_\" (tests/run.sh makes one).\n");
    exit(2);
}

require __DIR__ . '/lib/framework.php';
require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Browser.php';
require __DIR__ . '/lib/SiteTest.php';

Env::$siteUrl   = rtrim($options['site-url'], '/');
Env::$feedUrl   = rtrim($options['feed-url'], '/');
Env::$feedDir   = rtrim($options['feed-dir'], '/\\');
Env::$adminUser = (string) getenv('ISORA_TEST_ADMIN_USER');
Env::$adminPass = (string) getenv('ISORA_TEST_ADMIN_PASS');

$suite = ($options['suite'] ?? 'main') ?: 'main';
$files = glob(__DIR__ . '/' . ($suite === 'removed' ? 'removed' : 'cases') . '/*Test.php');
sort($files);

// Let PHP find the classes of Joomla's own extensions (menus, user plugins) by name.
require_once JPATH_LIBRARIES . '/namespacemap.php';
(new \JNamespacePsr4Map())->load();

if ($suite !== 'removed') {
    \JLoader::registerNamespace('Isora\\Component\\Isorairc\\Administrator', JPATH_ADMINISTRATOR . '/components/com_isorairc/src');
    \JLoader::registerNamespace('Isora\\Component\\Isorairc\\Site', JPATH_SITE . '/components/com_isorairc/src');
    Env::createFormTables();
    Env::deleteUsers();

    // The extension's two public pages, on the menu as the admin guide sets them up.
    Env::deleteMenuItems();
    Env::menuItem('Rating lookup', 'rating-lookup', 'ratings');
    Env::menuItem('Race entries', 'race-entries', 'entries');
}

$classes = [];

foreach ($files as $file) {
    require $file;
    $classes[] = 'IsoraTests\\Cases\\' . basename($file, '.php');
}

$failed = 1;

try {
    $failed = Runner::run($classes, (string) ($options['filter'] ?? ''));
} finally {
    if ($suite !== 'removed') {
        Env::deleteUsers();
        Env::deleteMenuItems();
    }
}

exit($failed ? 1 : 0);
