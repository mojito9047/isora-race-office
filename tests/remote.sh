#!/usr/bin/env bash
#
# Runs the full test set on another machine over SSH, for when this computer
# cannot run it itself (Windows, or no database server). The project's files
# and a freshly built package are sent over, and tests/run.sh is run there.
# Everything goes through one SSH connection.
#
#   ISORA_TEST_SSH="-i ~/.ssh/mykey root@test-server" bash tests/remote.sh
#   ISORA_TEST_SSH="..." bash tests/remote.sh Fleetsheet     only tests whose name contains "Fleetsheet"
#
# The other machine needs what tests/run.sh needs (see the top of that file).

set -euo pipefail

cd "$(dirname "$0")/.."

: "${ISORA_TEST_SSH:?Set ISORA_TEST_SSH to the ssh options and host, for example: -i ~/.ssh/mykey root@test-server}"
FILTER="${1:-}"
REMOTE_DIR="${ISORA_TEST_REMOTE_DIR:-isora-race-office-tests}"

python build.py 2>/dev/null || python3 build.py

echo "== Quick checks on this computer"
php tests/static.php

echo
echo "== Full tests on the other machine"
# shellcheck disable=SC2086
(git ls-files -co --exclude-standard; echo dist/pkg_isorairc.zip) | tar czf - -T - | ssh $ISORA_TEST_SSH \
    "rm -rf '$REMOTE_DIR' && mkdir -p '$REMOTE_DIR' && cd '$REMOTE_DIR' && tar xzf - --no-same-owner && php tests/static.php && bash tests/run.sh '$FILTER'"
