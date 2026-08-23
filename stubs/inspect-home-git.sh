#!/usr/bin/env bash
set -e
H=/home/bagart
echo "== home git age"
stat -c "birth=%w mod=%y" "$H/.git"
ls "$H/.git"
echo "== reflog"
git -C "$H" reflog 2>&1 | head -3 || true
echo "== index size"
stat -c %s "$H/.git/index" 2>/dev/null || echo "no index"
