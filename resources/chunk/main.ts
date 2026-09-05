/**
 * Mafia stub chunk (menu module task 18 / RFC §14.1 / A13): proves the chunk
 * protocol transport-only — no game logic lives here. Uses every declared
 * bridge capability and degrades to in-chunk text when a feature token is
 * not granted (never crashes).
 *
 * Bridge acquisition (§14.1 v2.4 clarification): `mount(el, ready)` calls
 * `ready(...)` and the frozen host bridge is the RETURN VALUE of ready().
 * Handshake globals are transient slots — set synchronously, the loader
 * captures and clears them.
 */

interface ThemeParams {
    readonly [key: string]: string;
}

interface ResourceItem {
    readonly id: string;
    readonly label: string;
    readonly hint?: string;
}

interface MafiaBridge {
    readonly version: 1;
    readonly features: readonly string[];
    readonly session: { readonly botId: string; readonly userId: number; readonly locale: string };
    readonly chat: { readonly id: number; readonly title: string } | null;
    fetch(path: string, init?: { method?: string; json?: unknown }): Promise<unknown>;
    searchResources?(domain: string, query?: { q?: string }): Promise<{ items: readonly ResourceItem[] }>;
    navigate?(to: 'home' | { chat: number }): void;
    close?(): void;
    haptic?(style: 'light' | 'medium' | 'heavy' | 'success' | 'error'): void;
    setBackHandler(handler: (() => void) | null): void;
    theme(): ThemeParams;
    requestFullscreen?(): Promise<void>;
    exitFullscreen?(): Promise<void>;
}

type Ready = (bridge: unknown) => MafiaBridge | undefined;

interface MountElement extends HTMLElement {
    readonly ownerDocument: Document;
}

const CHUNK_ID = 'mafia';
const DECLARED_FEATURES = ['context', 'navigation', 'resources', 'haptics', 'fullscreen'] as const;

// §14.1 rule 1: synchronous handshake slots.
(window as unknown as Record<string, unknown>).__TG_MENU_CHUNK__ = {
    id: CHUNK_ID,
    api: 1,
    features: [...DECLARED_FEATURES],
};

function el(tag: string, className?: string, text?: string): HTMLElement {
    const node = document.createElement(tag);

    if (className !== undefined) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

function mount(root: MountElement, ready: Ready): void {
    const bridge: MafiaBridge = ready(undefined) ?? fallbackBridge();
    const granted = new Set<string>(bridge.features);
    const theme = bridge.theme();

    root.textContent = '';
    root.setAttribute('data-mafia-stub', 'board');

    const style = (name: string, value: string): string => `--mafia-${name}:${value};`;

    const shell = el('div', 'mafia-stub');
    shell.setAttribute('style',
        style('bg', theme.bg_color ?? '#17212b')
        + style('fg', theme.text_color ?? '#ffffff')
        + style('accent', theme.button_color ?? '#5288c1')
        + style('hint', theme.hint_color ?? '#708499'),
    );

    const header = el('div', 'mafia-stub__header', 'Mafia — stub game screen');
    const board = el('div', 'mafia-stub__board', 'Town square (placeholder board)');
    const status = el('div', 'mafia-stub__status', `Signed in as user ${bridge.session.userId} · bot ${bridge.session.botId}`);
    shell.appendChild(header);
    shell.appendChild(board);
    shell.appendChild(status);

    const degraded = DECLARED_FEATURES.filter((token) => !granted.has(token));

    if (degraded.length > 0) {
        shell.appendChild(el(
            'div',
            'mafia-stub__degraded',
            `Running degraded — features not granted by this menu: ${degraded.join(', ')}`,
        ));
    }

    if (bridge.chat !== null) {
        shell.appendChild(el(
            'div',
            'mafia-stub__chat',
            `Chat scope: ${bridge.chat.title} (${bridge.chat.id})`,
        ));
    } else {
        shell.appendChild(el('div', 'mafia-stub__chat', 'No chat scope — bot-level surface'));
    }

    // D41 resources path: one searchResources('chat') call, rendered as a
    // plain list. Skipped entirely when the token was not granted.
    if (granted.has('resources') && typeof bridge.searchResources === 'function') {
        const list = el('ul', 'mafia-stub__chats');

        bridge
            .searchResources('chat', { q: '' })
            .then((page) => {
                for (const item of page.items) {
                    list.appendChild(el('li', 'mafia-stub__chat-item', item.label));
                }

                if (page.items.length === 0) {
                    list.appendChild(el('li', 'mafia-stub__chat-item', 'No chats visible'));
                }
            })
            .catch(() => {
                list.appendChild(el('li', 'mafia-stub__chat-item', 'Chat search failed'));
            });

        shell.appendChild(el('div', 'mafia-stub__chats-title', 'Your chats'));
        shell.appendChild(list);
    } else {
        shell.appendChild(el('div', 'mafia-stub__chats-title', 'Chat search unavailable (no resources feature)'));
    }

    // session/join over bridge.fetch — the only HTTP path a chunk has.
    const joinStatus = el('div', 'mafia-stub__join-status', 'Joining…');
    shell.appendChild(joinStatus);

    bridge.fetch('/session/join', { method: 'POST', json: {} })
        .then((data) => {
            const payload = data as { message?: string };
            joinStatus.textContent = payload.message ?? 'Joined the stub table';
            bridge.haptic?.('success');
        })
        .catch((error: unknown) => {
            joinStatus.textContent = `Join failed: ${error instanceof Error ? error.message : 'unknown error'}`;
            bridge.haptic?.('error');
        });

    // Fullscreen + landscape lock (v2.4 token): degrade to text when absent.
    let fullscreenActive = false;
    const fullscreenSupported = granted.has('fullscreen')
        && typeof bridge.requestFullscreen === 'function'
        && typeof bridge.exitFullscreen === 'function';

    if (fullscreenSupported) {
        const toggle = el('button', 'mafia-stub__fullscreen', 'Toggle fullscreen');

        toggle.addEventListener('click', () => {
            bridge.haptic?.('medium');

            if (fullscreenActive) {
                void bridge.exitFullscreen?.().then(() => {
                    fullscreenActive = false;
                    toggle.textContent = 'Toggle fullscreen';
                });
            } else {
                void bridge.requestFullscreen?.().then(() => {
                    fullscreenActive = true;
                    toggle.textContent = 'Exit fullscreen';
                });
            }
        });

        shell.appendChild(toggle);
    } else {
        shell.appendChild(el(
            'div',
            'mafia-stub__fullscreen-degraded',
            'Fullscreen unavailable on this menu version',
        ));
    }

    // Back handler ownership: while mounted we own the handler; back first
    // leaves fullscreen, only then navigates home.
    bridge.setBackHandler(() => {
        if (fullscreenActive) {
            void bridge.exitFullscreen?.().then(() => {
                fullscreenActive = false;
            });

            return;
        }

        bridge.haptic?.('light');
        bridge.navigate?.('home');
    });

    root.appendChild(shell);
}

/**
 * Last-resort bridge when an older host hands back nothing from ready():
 * keeps the stub alive with visibly degraded copy instead of crashing.
 */
function fallbackBridge(): MafiaBridge {
    return {
        version: 1,
        features: [],
        session: { botId: 'unknown', userId: 0, locale: 'en' },
        chat: null,
        fetch: () => Promise.reject(new Error('bridge unavailable')),
        setBackHandler: () => undefined,
        theme: () => ({}),
    };
}

(window as unknown as Record<string, unknown>).TgMenu = {
    mount(root: MountElement, ready: Ready): void {
        mount(root, ready);
    },
};
