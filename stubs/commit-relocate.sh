#!/usr/bin/env bash
set -e
cd /home/bagart/code/telegram-bot-platform/misc/BAGArt/telegram-bot-mafia-module
cat > /tmp/mafia-msg.txt <<'MSG'
chore: relocate into misc/BAGArt per platform modules rule

- path repositories now resolve sibling libs from misc/BAGArt
- telegram-bot-lib moved to suggest (host provides runtime contracts);
  keeps host `composer require` free of the dead daemon-runner chain
- bootstrap/check-host paths adapted to the new depth
- README install section: composer require + path repo, no PSR-4 hacks
MSG
git add -A
git -c user.name=BAGArt -c user.email=baltaev.artur+ask@gmail.com commit -q -F /tmp/mafia-msg.txt
rm /tmp/mafia-msg.txt
git log --oneline | head -3
echo "dirty: $(git status --porcelain | wc -l)"
