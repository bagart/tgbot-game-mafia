#!/usr/bin/env bash
set -e
cd /home/bagart/code/telegram-bot-module-mafia
f=src/MafiaModule.php
echo "file: $(file -b "$f")"
printf 'CR count via od: '
od -c "$f" | grep -c '\\r' || true
printf 'CR count via tr: '
tr -dc '\r' < "$f" | wc -c
total=0
for p in src tests resources database; do
  n=$(find $p -type f \( -name '*.php' -o -name '*.json' \) -exec sh -c "tr -dc '\r' < \"\$1\" | wc -c | grep -q '^..*\$' && ! tr -dc '\r' < \"\$1\" | wc -c | grep -q '^0\$'" _ {} \; -print 2>/dev/null | wc -l)
  total=$((total + n))
done
echo "files with real CR: $total"
