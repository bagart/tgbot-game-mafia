#!/usr/bin/env bash
set -e
cd /home/bagart/code/telegram-bot-platform
COMPOSER_ALLOW_SUPERUSER=1 composer require bagart/telegram-bot-module-mafia:@dev --no-interaction 2>&1 | grep -vE "PHP Deprecated|Deprecation Notice|Xdebug" | tail -8
echo "=== verify"
php -d xdebug.mode=off misc/BAGArt/telegram-bot-mafia-module/stubs/check-host.php 2>&1 | tail -4
