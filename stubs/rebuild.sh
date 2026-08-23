#!/usr/bin/env bash
set -e
cd /home/bagart/code/telegram-bot-platform/misc/BAGArt/telegram-bot-mafia-module
rm -rf vendor composer.lock
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction 2>&1 | grep -E "Symlinking|Installing bagart|Problem" || true
php -d xdebug.mode=off vendor/bin/pest 2>&1 | tail -3
