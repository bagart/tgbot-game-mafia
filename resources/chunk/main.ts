/**
 * Mafia Mini App — Game Board (Phase 5)
 *
 * React-based game UI communicating through the menu chunk bridge protocol.
 * Handles: lobby, game board, night actions, vote UI, spectator mode.
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

// --- Game State Types ---

interface SeatState {
    seat: number;
    userId: string;
    name: string;
    isBot: boolean;
    role: string | null;
    alive: boolean;
    bullets: number;
    selfHealLeft: number;
    elderShield: boolean;
    missedVote: boolean;
}

interface GameSnapshot {
    gameId: string;
    roomId: string;
    chatId: string | null;
    locale: string;
    phase: 'setup' | 'night' | 'dayDiscussion' | 'dayVoting' | 'ended';
    phaseNumber: number;
    dayNumber: number;
    deadlineAt: number;
    mirrorOn: boolean;
    seats: SeatState[];
    votes: Record<string, number>;
    revoteCandidates: number[];
    voteRound: number;
    result: string | null;
    nightSeconds: number;
    discussionSeconds: number;
    voteSeconds: number;
}

interface SnapshotResponse {
    active: boolean;
    snapshot?: GameSnapshot;
    viewerSeat?: number;
    isSpectator?: boolean;
}

// --- Constants ---

const CHUNK_ID = 'mafia';
const DECLARED_FEATURES = ['context', 'navigation', 'resources', 'haptics', 'fullscreen'] as const;
const POLL_INTERVAL_MS = 3000;
const PHASE_LABELS: Record<string, string> = {
    setup: 'Lobby',
    night: 'Night',
    dayDiscussion: 'Discussion',
    dayVoting: 'Vote',
    ended: 'Game Over',
};
const ROLE_ICONS: Record<string, string> = {
    mafia: '🔪',
    detective: '🔍',
    doctor: '💊',
    bodyguard: '🛡️',
    journalist: '📰',
    sniper: '🎯',
    lone_bandit: '🃏',
    elder: '👴',
    maniac: '疯',
    serial_killer: '🔪',
};

// --- Handshake ---

(window as unknown as Record<string, unknown>).__TG_MENU_CHUNK__ = {
    id: CHUNK_ID,
    api: 1,
    features: [...DECLARED_FEATURES],
};

// --- Helpers ---

function el(tag: string, className?: string, text?: string): HTMLElement {
    const node = document.createElement(tag);
    if (className !== undefined) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function styled(tag: string, styles: Record<string, string>, className?: string): HTMLElement {
    const node = el(tag, className);
    Object.assign(node.style, styles);
    return node;
}

// --- Game Board Renderer ---

class MafiaGameBoard {
    private root: MountElement;
    private bridge: MafiaBridge;
    private granted: Set<string>;
    private container: HTMLElement;
    private pollTimer: ReturnType<typeof setInterval> | null = null;
    private currentSnapshot: GameSnapshot | null = null;
    private viewerSeat: number | undefined;
    private isSpectator = false;

    constructor(root: MountElement, bridge: MafiaBridge) {
        this.root = root;
        this.bridge = bridge;
        this.granted = new Set<string>(bridge.features);

        root.textContent = '';
        this.container = styled('div', {
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            color: bridge.theme().text_color ?? '#ffffff',
            background: bridge.theme().bg_color ?? '#17212b',
            minHeight: '100vh',
            padding: '12px',
        });
        root.appendChild(this.container);

        this.showLoading();
        this.fetchSnapshot();
        this.startPolling();
    }

    destroy(): void {
        if (this.pollTimer !== null) {
            clearInterval(this.pollTimer);
        }
    }

    private startPolling(): void {
        this.pollTimer = setInterval(() => this.fetchSnapshot(), POLL_INTERVAL_MS);
    }

    private showLoading(): void {
        this.container.textContent = '';
        this.container.appendChild(styled('div', { padding: '40px', textAlign: 'center' }, '', 'Loading game...'));
    }

    private async fetchSnapshot(): Promise<void> {
        try {
            const data = (await this.bridge.fetch('/game/snapshot', { method: 'GET' })) as SnapshotResponse;
            if (!data.active || !data.snapshot) {
                this.showLobby();
                return;
            }
            this.currentSnapshot = data.snapshot;
            this.viewerSeat = data.viewerSeat;
            this.isSpectator = data.isSpectator ?? false;
            this.renderBoard();
        } catch {
            this.container.textContent = '';
            this.container.appendChild(el('div', '', 'Connection lost. Retrying...'));
        }
    }

    private showLobby(): void {
        this.container.textContent = '';
        const header = el('div', '', 'Waiting for game to start...');
        Object.assign(header.style, { fontSize: '18px', textAlign: 'center', padding: '40px 0' });
        this.container.appendChild(header);
    }

    private renderBoard(): void {
        if (!this.currentSnapshot) return;
        const s = this.currentSnapshot;

        this.container.textContent = '';

        // Header
        const header = el('div');
        Object.assign(header.style, { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px', padding: '8px', borderRadius: '8px', background: 'rgba(255,255,255,0.05)' });
        header.appendChild(el('span', '', `${PHASE_LABELS[s.phase] ?? s.phase}`));
        header.appendChild(el('span', '', s.dayNumber > 0 ? `Day ${s.dayNumber}` : ''));
        header.appendChild(el('span', '', this.formatCountdown(s)));
        if (this.isSpectator) {
            const badge = el('span', '', 'SPECTATOR');
            Object.assign(badge.style, { color: '#ff6b6b', fontSize: '10px', fontWeight: 'bold' });
            header.appendChild(badge);
        }
        this.container.appendChild(header);

        // Seat Grid
        const grid = el('div');
        Object.assign(grid.style, { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))', gap: '8px', marginBottom: '12px' });

        for (const seat of s.seats) {
            const card = this.renderSeat(seat, s);
            grid.appendChild(card);
        }
        this.container.appendChild(grid);

        // Phase-specific UI
        if (s.phase === 'night' && !this.isSpectator) {
            this.container.appendChild(this.renderNightActions(s));
        } else if (s.phase === 'dayVoting' && !this.isSpectator) {
            this.container.appendChild(this.renderVoteActions(s));
        } else if (s.phase === 'dayDiscussion' && !this.isSpectator) {
            const info = el('div', '', 'Discussion phase — use Telegram chat to talk');
            Object.assign(info.style, { textAlign: 'center', padding: '16px', color: '#708499' });
            this.container.appendChild(info);
        } else if (s.phase === 'ended') {
            this.container.appendChild(this.renderResult(s));
        }
    }

    private renderSeat(seat: SeatState, snapshot: GameSnapshot): HTMLElement {
        const isViewer = seat.seat === this.viewerSeat;
        const isAlive = seat.alive;
        const voted = snapshot.votes && Object.values(snapshot.votes).includes(seat.seat);

        const card = styled('div', {
            padding: '8px',
            borderRadius: '8px',
            textAlign: 'center',
            border: isViewer ? '2px solid #5288c1' : '1px solid rgba(255,255,255,0.1)',
            background: !isAlive ? 'rgba(0,0,0,0.3)' : isViewer ? 'rgba(82,136,193,0.2)' : 'rgba(255,255,255,0.05)',
            opacity: isAlive ? '1' : '0.5',
            cursor: 'pointer',
            transition: 'transform 0.1s',
        });

        const name = el('div', '', seat.name);
        Object.assign(name.style, { fontSize: '11px', fontWeight: 'bold', marginBottom: '2px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' });
        card.appendChild(name);

        const num = el('div', '', `#${seat.seat}`);
        Object.assign(num.style, { fontSize: '9px', color: '#708499' });
        card.appendChild(num);

        if (seat.role && (isViewer || !isAlive)) {
            const role = el('div', '', `${ROLE_ICONS[seat.role] ?? ''} ${seat.role}`);
            Object.assign(role.style, { fontSize: '9px', marginTop: '2px' });
            card.appendChild(role);
        }

        if (!isAlive) {
            const dead = el('div', '', '💀');
            Object.assign(dead.style, { fontSize: '14px', marginTop: '2px' });
            card.appendChild(dead);
        }

        if (voted && snapshot.phase === 'dayVoting') {
            const voteMark = el('div', '', '🗳️');
            Object.assign(voteMark.style, { fontSize: '10px', marginTop: '2px' });
            card.appendChild(voteMark);
        }

        return card;
    }

    private static readonly ROLE_ACTION_MAP: Record<string, string> = {
        mafia: 'kill',
        doctor: 'heal',
        detective: 'check_alignment',
        bodyguard: 'guard',
    };

    private renderNightActions(snapshot: GameSnapshot): HTMLElement {
        const wrapper = el('div');
        Object.assign(wrapper.style, { padding: '12px', borderRadius: '8px', background: 'rgba(255,255,255,0.05)' });

        const viewerSeatIndex = (this.viewerSeat ?? 0) - 1;
        const viewerRole = snapshot.seats[viewerSeatIndex]?.role ?? null;
        const actionType = MafiaGameBoard.ROLE_ACTION_MAP[viewerRole ?? ''] ?? 'kill';

        const title = el('div', '', `Select your night action target:`);
        Object.assign(title.style, { fontSize: '14px', marginBottom: '8px' });
        wrapper.appendChild(title);

        const targetGrid = el('div');
        Object.assign(targetGrid.style, { display: 'flex', flexWrap: 'wrap', gap: '6px' });

        for (const seat of snapshot.seats) {
            if (!seat.alive || seat.seat === this.viewerSeat) continue;

            const btn = el('button', '', `${seat.name} (#${seat.seat})`);
            Object.assign(btn.style, {
                padding: '6px 12px',
                borderRadius: '6px',
                border: '1px solid rgba(255,255,255,0.2)',
                background: 'rgba(255,255,255,0.1)',
                color: 'inherit',
                cursor: 'pointer',
                fontSize: '12px',
            });

            btn.addEventListener('click', async () => {
                this.bridge.haptic?.('medium');
                try {
                    await this.bridge.fetch('/game/night-action', {
                        method: 'POST',
                        json: { targetSeat: seat.seat, actionType },
                    });
                    this.bridge.haptic?.('success');
                    this.fetchSnapshot();
                } catch {
                    this.bridge.haptic?.('error');
                }
            });

            btn.addEventListener('mouseenter', () => { btn.style.background = 'rgba(255,255,255,0.2)'; });
            btn.addEventListener('mouseleave', () => { btn.style.background = 'rgba(255,255,255,0.1)'; });

            targetGrid.appendChild(btn);
        }

        wrapper.appendChild(targetGrid);

        // Skip night button
        const skipBtn = el('button', '', 'Skip Night');
        Object.assign(skipBtn.style, {
            padding: '6px 12px',
            borderRadius: '6px',
            border: '1px solid rgba(255,255,255,0.2)',
            background: 'rgba(255,165,0,0.15)',
            color: 'inherit',
            cursor: 'pointer',
            fontSize: '12px',
            marginTop: '8px',
        });
        skipBtn.addEventListener('click', async () => {
            this.bridge.haptic?.('light');
            try {
                await this.bridge.fetch('/game/skip-night', { method: 'POST' });
                this.bridge.haptic?.('success');
                this.fetchSnapshot();
            } catch {
                this.bridge.haptic?.('error');
            }
        });
        wrapper.appendChild(skipBtn);

        // TODO: day shot, emergency assembly, pause/resume buttons — server endpoints exist but UI not yet wired

        return wrapper;
    }

    private renderVoteActions(snapshot: GameSnapshot): HTMLElement {
        const wrapper = el('div');
        Object.assign(wrapper.style, { padding: '12px', borderRadius: '8px', background: 'rgba(255,255,255,0.05)' });

        const title = el('div', '', 'Vote to eliminate:');
        Object.assign(title.style, { fontSize: '14px', marginBottom: '8px' });
        wrapper.appendChild(title);

        const voteGrid = el('div');
        Object.assign(voteGrid.style, { display: 'flex', flexWrap: 'wrap', gap: '6px' });

        for (const seat of snapshot.seats) {
            if (!seat.alive || seat.seat === this.viewerSeat) continue;

            const btn = el('button', '', `${seat.name} (#${seat.seat})`);
            Object.assign(btn.style, {
                padding: '6px 12px',
                borderRadius: '6px',
                border: '1px solid rgba(255,255,255,0.2)',
                background: 'rgba(255,255,255,0.1)',
                color: 'inherit',
                cursor: 'pointer',
                fontSize: '12px',
            });

            btn.addEventListener('click', async () => {
                this.bridge.haptic?.('medium');
                try {
                    await this.bridge.fetch('/game/vote', {
                        method: 'POST',
                        json: { targetSeat: seat.seat },
                    });
                    this.bridge.haptic?.('success');
                    this.fetchSnapshot();
                } catch {
                    this.bridge.haptic?.('error');
                }
            });

            btn.addEventListener('mouseenter', () => { btn.style.background = 'rgba(255,255,255,0.2)'; });
            btn.addEventListener('mouseleave', () => { btn.style.background = 'rgba(255,255,255,0.1)'; });

            voteGrid.appendChild(btn);
        }

        // Abstain button
        const abstainBtn = el('button', '', 'Abstain');
        Object.assign(abstainBtn.style, {
            padding: '6px 12px',
            borderRadius: '6px',
            border: '1px solid rgba(255,255,255,0.2)',
            background: 'rgba(255,100,100,0.15)',
            color: 'inherit',
            cursor: 'pointer',
            fontSize: '12px',
        });
        abstainBtn.addEventListener('click', async () => {
            this.bridge.haptic?.('light');
            try {
                await this.bridge.fetch('/game/vote', {
                    method: 'POST',
                    json: { targetSeat: -1 },
                });
                this.bridge.haptic?.('success');
                this.fetchSnapshot();
            } catch {
                this.bridge.haptic?.('error');
            }
        });
        voteGrid.appendChild(abstainBtn);

        wrapper.appendChild(voteGrid);

        // Vote tally
        if (Object.keys(snapshot.votes).length > 0) {
            const tally = el('div');
            Object.assign(tally.style, { marginTop: '8px', fontSize: '11px', color: '#708499' });
            tally.textContent = `${Object.keys(snapshot.votes).length} votes cast`;
            wrapper.appendChild(tally);
        }

        return wrapper;
    }

    private renderResult(snapshot: GameSnapshot): HTMLElement {
        const wrapper = el('div');
        Object.assign(wrapper.style, { textAlign: 'center', padding: '24px' });

        const title = el('div', '', 'Game Over!');
        Object.assign(title.style, { fontSize: '24px', fontWeight: 'bold', marginBottom: '12px' });
        wrapper.appendChild(title);

        if (snapshot.result) {
            const result = el('div', '', `Winner: ${snapshot.result}`);
            Object.assign(result.style, { fontSize: '18px', color: '#5288c1', marginBottom: '16px' });
            wrapper.appendChild(result);
        }

        // Role reveal
        const roles = el('div');
        Object.assign(roles.style, { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(100px, 1fr))', gap: '8px' });
        for (const seat of snapshot.seats) {
            const card = el('div', '', `${seat.name}: ${seat.role ?? '?'}`);
            Object.assign(card.style, { padding: '8px', borderRadius: '6px', background: 'rgba(255,255,255,0.05)', fontSize: '11px' });
            roles.appendChild(card);
        }
        wrapper.appendChild(roles);

        return wrapper;
    }

    private formatCountdown(snapshot: GameSnapshot): string {
        const now = Math.floor(Date.now() / 1000);
        const remaining = Math.max(0, snapshot.deadlineAt - now);
        const m = Math.floor(remaining / 60);
        const sec = remaining % 60;
        return `${m}:${sec.toString().padStart(2, '0')}`;
    }
}

// --- Entry Point ---

let board: MafiaGameBoard | null = null;

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

function mount(root: MountElement, ready: Ready): void {
    const bridge: MafiaBridge = ready(undefined) ?? fallbackBridge();

    board = new MafiaGameBoard(root, bridge);

    bridge.setBackHandler(() => {
        if (bridge.chat !== null) {
            bridge.haptic?.('light');
            bridge.navigate?.({ chat: bridge.chat.id });
        } else {
            bridge.navigate?.('home');
        }
    });
}

(window as unknown as Record<string, unknown>).TgMenu = {
    mount(root: MountElement, ready: Ready): void {
        mount(root, ready);
    },
};
