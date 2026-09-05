/**
 * Mafia stub chunk build (menu module task 18 / §14.1): content-hashed
 * single-file bundle under `public/vendor/menu-modules/mafia/` exposing the
 * §14.1 handshake (`__TG_MENU_CHUNK__` + `TgMenu.mount`). No framework, no
 * external runtime deps — plain DOM against the frozen TgMenuBridge surface.
 *
 * Run from the HOST repo root (vite resolves from the host node_modules — no
 * module-local npm install, same convention as telegram-bot-menu-module):
 *
 *   npx vite build --config misc/BAGArt/telegram-game-mafia/vite.mafia-chunk.config.mjs
 * or from the module root:
 *   npm run build
 */
import { defineConfig } from 'vite';

const moduleRoot = new URL('.', import.meta.url).pathname;

export default defineConfig({
    root: moduleRoot,
    publicDir: false,
    build: {
        outDir: 'public/vendor/menu-modules/mafia',
        emptyOutDir: true,
        target: 'es2022',
        minify: true,
        rollupOptions: {
            input: `${moduleRoot}resources/chunk/main.ts`,
            output: {
                // §14.1: content-hashed filename minted by this build; the
                // manifest (ChunkAsset) references the hashed name verbatim.
                format: 'iife',
                entryFileNames: 'app.[hash].js',
                assetFileNames: '[name].[hash][extname]',
            },
        },
    },
});
