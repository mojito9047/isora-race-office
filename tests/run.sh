#!/usr/bin/env bash
#
# Runs the full test set against a throwaway Joomla site.
#
# Each run downloads Joomla (kept for next time), makes a new empty database
# and site, installs the extension package into it, runs the tests and then
# removes the site and the database again. Nothing else on the machine is touched.
#
#   bash tests/run.sh             run everything
#   bash tests/run.sh Fleetsheet  run only the tests whose name contains "Fleetsheet"
#
# Needs: Linux, PHP 8.1+ (with mysqli, curl, zip, dom, mbstring, gd), a MySQL or
# MariaDB server, curl and unzip. Build the package first:  python build.py
#
# Settings (environment variables):
#   PACKAGE          package to test              (default dist/pkg_isorairc.zip)
#   JOOMLA_VERSION   Joomla version to test on    (default 5.4.10)
#   MYSQL_ADMIN      command that runs SQL as a database administrator
#                    (default "mysql", which works for root on most servers)
#   DB_HOST          database host for Joomla     (default localhost)
#   SITE_PORT, FEED_PORT   local ports to use     (default 8089, 8090)
#   KEEP=1           leave the site and database in place afterwards, to look at

set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$HERE")"

PACKAGE="${PACKAGE:-$ROOT/dist/pkg_isorairc.zip}"
JOOMLA_VERSION="${JOOMLA_VERSION:-5.4.10}"
MYSQL_ADMIN="${MYSQL_ADMIN:-mysql}"
DB_HOST="${DB_HOST:-localhost}"
SITE_PORT="${SITE_PORT:-8089}"
FEED_PORT="${FEED_PORT:-8090}"
CACHE="${ISORA_TEST_CACHE:-$HOME/.cache/isora-tests}"
FILTER="${1:-}"

[ -f "$PACKAGE" ] || { echo "Package not found: $PACKAGE (run: python build.py)"; exit 2; }

WORK="$(mktemp -d "${TMPDIR:-/tmp}/isora-tests.XXXXXX")"
SITE="$WORK/site"
FEEDS="$WORK/feeds"
LOG="$WORK/log"
ID="$(date +%s)$$"
DB_NAME="isoratest_$ID"
DB_USER="isoratest_$ID"
DB_PASS="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"
PIDS=""

export ISORA_TEST_ADMIN_USER="testadmin"
export ISORA_TEST_ADMIN_PASS="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')aA1!"

cleanup() {
    status=$?
    for pid in $PIDS; do kill "$pid" 2>/dev/null || true; done

    if [ "${KEEP:-0}" = "1" ]; then
        echo "Kept: site $SITE, database $DB_NAME (remove both by hand when finished)."
    else
        $MYSQL_ADMIN -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; DROP USER IF EXISTS '$DB_USER'@'localhost'; DROP USER IF EXISTS '$DB_USER'@'%';" 2>/dev/null || true
        rm -rf "$WORK"
    fi

    exit $status
}
trap cleanup EXIT

step() { echo; echo "== $*"; }

# Run a set-up command quietly; show its output only if it fails.
quiet() {
    if ! "$@" >"$LOG" 2>&1; then
        cat "$LOG"
        echo "FAILED: $*"
        exit 1
    fi
}

joomla() { php "$SITE/cli/joomla.php" "$@"; }

step "Joomla $JOOMLA_VERSION"
mkdir -p "$CACHE" "$SITE" "$FEEDS"
ZIP="$CACHE/Joomla_$JOOMLA_VERSION.zip"

if [ ! -s "$ZIP" ]; then
    echo "Downloading Joomla $JOOMLA_VERSION ..."
    curl -fsSL -o "$ZIP.part" "https://github.com/joomla/joomla-cms/releases/download/$JOOMLA_VERSION/Joomla_$JOOMLA_VERSION-Stable-Full_Package.zip"
    mv "$ZIP.part" "$ZIP"
fi

quiet unzip -q "$ZIP" -d "$SITE"

step "Database $DB_NAME"
$MYSQL_ADMIN -e "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
    CREATE USER '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
    GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
    GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';"

step "Install Joomla"
quiet php "$SITE/installation/joomla.php" install --no-interaction \
    --site-name="ISORA Race Office tests" \
    --admin-user="Test Administrator" --admin-username="$ISORA_TEST_ADMIN_USER" \
    --admin-password="$ISORA_TEST_ADMIN_PASS" --admin-email="admin@example.invalid" \
    --db-type=mysqli --db-host="$DB_HOST" --db-user="$DB_USER" --db-pass="$DB_PASS" \
    --db-name="$DB_NAME" --db-prefix="tst_" --db-encryption=0
rm -rf "$SITE/installation"

# Irish time, friendly addresses, and every PHP warning shown so the tests can catch them.
# The site's address is given outright because Joomla cannot work it out under PHP's built-in web server.
quiet joomla config:set offset=Europe/Dublin sef=true sef_rewrite=true error_reporting=maximum mailonline=false     live_site="http://127.0.0.1:$SITE_PORT"

step "Install the extension (fresh install)"
quiet joomla extension:install --path="$PACKAGE"

step "Install it again (update over an existing install)"
quiet joomla extension:install --path="$PACKAGE"

step "Start the test site and the stand-in rating feeds"
php -S "127.0.0.1:$SITE_PORT" -t "$SITE" "$HERE/lib/site-router.php" >"$WORK/site.log" 2>&1 &
PIDS="$PIDS $!"
ISORA_FEED_DIR="$FEEDS" php -S "127.0.0.1:$FEED_PORT" "$HERE/lib/feed-router.php" >"$WORK/feeds.log" 2>&1 &
PIDS="$PIDS $!"

for attempt in $(seq 1 50); do
    curl -fs -o /dev/null "http://127.0.0.1:$SITE_PORT/" && break
    [ "$attempt" = 50 ] && { echo "The test site did not start:"; tail -20 "$WORK/site.log"; exit 1; }
    sleep 0.2
done

run_suite() {
    php "$HERE/run.php" --joomla="$SITE" --site-url="http://127.0.0.1:$SITE_PORT" \
        --feed-url="http://127.0.0.1:$FEED_PORT" --feed-dir="$FEEDS" --suite="$1" --filter="$FILTER"
}

step "Tests"
result=0
run_suite main || result=1

if [ -z "$FILTER" ]; then
    step "Remove the extension, then check nothing is left behind"
    package_id="$($MYSQL_ADMIN -N -e "SELECT extension_id FROM \`$DB_NAME\`.tst_extensions WHERE type = 'package' AND element = 'pkg_isorairc'")"
    quiet joomla extension:remove "$package_id" --no-interaction
    run_suite removed || result=1
fi

echo
if [ "$result" = 0 ]; then echo "ALL TESTS PASSED"; else echo "SOME TESTS FAILED"; fi
exit $result
