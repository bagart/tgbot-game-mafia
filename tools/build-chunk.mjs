#!/usr/bin/env node
/**
 * Mafia chunk build driver (menu module task 18). Runs vite from the host
 * node_modules (no module-local npm install — the same convention as
 * telegram-bot-menu-module), then records the minted content-hashed
 * filename into resources/chunk/build-manifest.json, which ChunkAsset.php
 * reads so `UiEntry::Chunk(url)` always references the hashed name verbatim
 * (§14.1: publish never generates or renames).
 *
 * Vite resolution order:
 *   1. $MAFIA_VITE_BIN (explicit override)
 *   2. <host>/node_modules/vite/bin/vite.js  (host = 4 levels up)
 *   3. local node_modules/vite/bin/vite.js (standalone checkout with own install)
 */
import { spawnSync } from 'node:child_process';
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const moduleRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(moduleRoot, 'public/vendor/menu-modules/mafia');
const configPath = join(moduleRoot, 'vite.mafia-chunk.config.mjs');

function findViteBin() {
    if (process.env.MAFIA_VITE_BIN !== undefined) {
        return process.env.MAFIA_VITE_BIN;
    }

    const candidates = [
        resolve(moduleRoot, '../../../node_modules/vite/bin/vite.js'),
        join(moduleRoot, 'node_modules/vite/bin/vite.js'),
    ];

    for (const candidate of candidates) {
        if (existsSync(candidate)) {
            return candidate;
        }
    }

    console.error('[mafia-chunk] vite not found — run `npm install` in the host repo or set MAFIA_VITE_BIN');

    process.exit(1);
}

const result = spawnSync(process.execPath, [findViteBin(), 'build', '--config', configPath], {
    stdio: 'inherit',
    cwd: moduleRoot,
});

if (result.status !== 0) {
    process.exit(result.status ?? 1);
}

const bundles = readdirSync(outDir).filter((name) => /^app\.[A-Za-z0-9_-]+\.js$/.test(name));

if (bundles.length !== 1) {
    console.error(`[mafia-chunk] expected exactly one app.<hash>.js in ${outDir}, got: ${bundles.join(', ')}`);

    process.exit(1);
}

const file = bundles[0];
const bytes = readFileSync(join(outDir, file));

writeFileSync(
    join(moduleRoot, 'resources/chunk/build-manifest.json'),
    `${JSON.stringify({ file, bytes: bytes.length }, null, 4)}\n`,
);

console.log(`[mafia-chunk] built ${file} (${bytes.length} bytes)`);
