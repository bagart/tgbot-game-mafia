#!/usr/bin/env bash
set -e
cd /home/bagart/code/telegram-bot-module-mafia
cat > /tmp/mafia-msg.txt <<'MSG'
chore: remove cleanup helper after use
MSG
git add -A
git -c user.name=BAGArt -c user.email=baltaev.artur+ask@gmail.com commit -q -F /tmp/mafia-msg.txt
rm /tmp/mafia-msg.txt
git log --oneline | head -4
echo "dirty files: $(git status --porcelain | wc -l)"
