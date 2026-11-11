#!/bin/sh
DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
nohup php "$DIR/fallback.php" "$1" "$2" "$3" >/dev/null 2>&1 &
