<?php

declare(strict_types=1);

/**
 * Mafia persona deck builder & validator.
 *
 * Scans docs/tasks/mafia/personas/<setting>/*.md prompt files, validates deck
 * completeness and style-marker consistency, then regenerates:
 *   - index.json    (machine-readable catalog for module integration)
 *   - gallery.html  (self-contained offline review gallery)
 *
 * Usage: php build.php [--json]
 * Exit codes: 0 = deck valid, 1 = validation issues found.
 */

namespace BAGArt\DocsTools;

final class PersonaDeckBuilder
{
    private const EXPECTED_ROLES = [
        'civilian-01', 'civilian-02', 'civilian-03', 'civilian-04', 'civilian-05',
        'detective', 'doctor', 'escort', 'bodyguard', 'witness',
        'journalist', 'elder', 'bomzh', 'sniper',
        'mafia-01', 'mafia-02', 'godfather', 'turncoat',
        'maniac', 'bandit', 'satanist',
        'card-back',
    ];

    private const ROLE_EMOJI = [
        'civilian' => '🙂', 'detective' => '🔍', 'doctor' => '💉', 'escort' => '💋',
        'bodyguard' => '🛡️', 'witness' => '👁️', 'journalist' => '🎤', 'elder' => '🧓',
        'bomzh' => '🧣', 'sniper' => '🎯', 'mafia' => '🔪', 'godfather' => '🎩',
        'turncoat' => '🐺', 'maniac' => '🔫', 'bandit' => '🃏', 'satanist' => '😈',
        'card-back' => '🎴',
    ];

    private const ROLE_TEAM = [
        'civilian' => 'town', 'detective' => 'town', 'doctor' => 'town', 'escort' => 'town',
        'bodyguard' => 'town', 'witness' => 'town', 'journalist' => 'town', 'elder' => 'town',
        'bomzh' => 'town', 'sniper' => 'town',
        'mafia' => 'mafia', 'godfather' => 'mafia', 'turncoat' => 'mafia',
        'maniac' => 'solo', 'bandit' => 'solo', 'satanist' => 'solo',
    ];

    private const STYLE_MARKERS = [
        'gouache-and-ink game-card portrait',
        'square 1:1 game card.',
    ];

    /** @var array<string, list<string>> */
    private array $issues = [];

    public function __construct(
        private readonly string $rootDir,
    ) {
    }

    public function build(): array
    {
        $settings = [];

        foreach ($this->settingDirs() as $dir) {
            $settings[$dir] = $this->scanSetting($this->rootDir . '/' . $dir);
            foreach ($settings[$dir]['issues'] as $issue) {
                $this->issues[$dir][] = $issue;
            }
        }

        return [
            'generated_at' => gmdate('c'),
            'style_core' => '_shared/style-core.md',
            'expected_cast' => self::EXPECTED_ROLES,
            'settings' => $settings,
            'issues' => $this->issues,
        ];
    }

    /** @return list<string> */
    private function settingDirs(): array
    {
        $dirs = [];
        foreach (scandir($this->rootDir) ?: [] as $entry) {
            $path = $this->rootDir . '/' . $entry;
            if ($entry[0] !== '_' && $entry[0] !== '.' && is_dir($path) && is_file($path . '/README.md')) {
                $dirs[] = $entry;
            }
        }
        sort($dirs);

        return $dirs;
    }

    /** @return array{title: string, cast_complete: bool, images: array{present: int, total: int}, personas: list<array<string, mixed>>, issues: list<string>} */
    private function scanSetting(string $dir): array
    {
        $name = basename($dir);
        $issues = [];
        $parsed = [];

        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $base = basename($file, '.md');
            if ($base === 'README') {
                continue;
            }
            if (!in_array($base, self::EXPECTED_ROLES, true)) {
                $issues[] = "unexpected prompt file: {$base}.md (not part of the expected cast)";
                continue;
            }
            $parsed[$base] = $this->parsePrompt($file);
        }

        foreach (self::EXPECTED_ROLES as $role) {
            if (!isset($parsed[$role])) {
                $issues[] = "missing prompt file: {$role}.md";
            }
        }

        $accents = [];
        $personas = [];
        foreach (self::EXPECTED_ROLES as $role) {
            $p = $parsed[$role] ?? null;
            if ($p === null) {
                continue;
            }
            array_push($issues, ...$p['issues']);

            $accentKey = mb_strtolower($p['accent']);
            if ($accentKey !== '') {
                $accents[$accentKey][] = $role;
            }

            $imageFile = $role . '.png';
            $personas[] = [
                'file' => "{$name}/{$role}.md",
                'image' => "{$name}/{$imageFile}",
                'image_present' => is_file($dir . '/' . $imageFile),
                'role' => $p['role'],
                'team' => self::ROLE_TEAM[$p['role']] ?? 'unknown',
                'emoji' => self::ROLE_EMOJI[$p['role']] ?? '🎭',
                'alias' => $p['alias'],
                'accent' => $p['accent'],
                'prompt' => $p['prompt'],
                'negative' => $p['negative'],
            ];
        }

        foreach ($accents as $accent => $roles) {
            if (count($roles) > 1) {
                $issues[] = 'duplicate accent color "' . $accent . '": ' . implode(', ', $roles);
            }
        }

        $present = count(array_filter($personas, static fn (array $p): bool => (bool) $p['image_present']));

        return [
            'title' => $this->readmeTitle($dir . '/README.md'),
            'cast_complete' => count($personas) === count(self::EXPECTED_ROLES),
            'images' => ['present' => $present, 'total' => count(self::EXPECTED_ROLES)],
            'personas' => $personas,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{role: string, alias: string, accent: string, prompt: string, negative: string, issues: list<string>}
     */
    private function parsePrompt(string $file): array
    {
        $content = str_replace("\r\n", "\n", (string) file_get_contents($file));
        $base = basename($file, '.md');
        $issues = [];

        if (!preg_match('/^#\s+(.+?)\s+—\s+(.+?)\s+\(role:\s*([a-z0-9-]+)\)\s*$/mu', $content, $m)) {
            $issues[] = "{$base}.md: cannot parse H1 (expected `# <token> — <alias> (role: <role>)`)";

            return ['role' => 'unknown', 'alias' => $base, 'accent' => '', 'prompt' => '', 'negative' => '', 'issues' => $issues];
        }

        [, , $aliasFull, $role] = $m;
        $shortAlias = trim(explode(',', $aliasFull, 2)[0]);

        if (!str_starts_with($base, $role)) {
            $issues[] = "{$base}.md: role token \"{$role}\" does not match filename";
        }

        foreach (['## Prompt', '## Negative'] as $section) {
            if (!str_contains($content, $section)) {
                $issues[] = "{$base}.md: missing section {$section}";
            }
        }

        $flat = (string) preg_replace('/\s+/u', ' ', $content);

        foreach (self::STYLE_MARKERS as $marker) {
            if ($base === 'card-back' && !str_contains($marker, 'square 1:1')) {
                continue;
            }
            if (!str_contains($flat, $marker)) {
                $issues[] = "{$base}.md: style-core marker drift — \"{$marker}\" not found";
            }
        }

        $prompt = preg_match('/##\s*Prompt\s*\n(.*?)\n##\s/s', $content, $pm) ? trim($pm[1]) : '';
        $negative = preg_match('/##\s*Negative\s*\n(.*)/s', $content, $nm) ? trim($nm[1]) : '';

        $accent = '';
        if ($base !== 'card-back') {
            if (preg_match('/Accent:\s*([^.]+)\./iu', $prompt, $am)) {
                $accent = trim($am[1]);
            } else {
                $issues[] = "{$base}.md: no `Accent:` sentence in the prompt";
            }
        }

        return ['role' => $role, 'alias' => $shortAlias, 'accent' => $accent, 'prompt' => $prompt, 'negative' => $negative, 'issues' => $issues];
    }

    private function readmeTitle(string $readmePath): string
    {
        $first = strtok((string) file_get_contents($readmePath), "\n") ?: '';
        $title = trim((string) preg_replace('/^#\s+/', '', $first));

        return preg_replace('/^Setting:\s*/i', '', $title) ?? $title;
    }

    public function renderGallery(array $deck): string
    {
        $dataJson = json_encode($deck, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mafia Persona Decks</title>
<style>
  :root { --bg:#14120f; --card:#1e1a15; --line:#3a3226; --ink:#d8cdb8; --dim:#8a7f6a; --gold:#c9a24b; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:var(--bg); color:var(--ink); font:16px/1.5 Georgia,'Times New Roman',serif; padding:32px; }
  h1 { font-weight:normal; letter-spacing:.04em; } h1 span { color:var(--gold); }
  .sub { color:var(--dim); margin:6px 0 20px; font-style:italic; }
  .tabs,.chips { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
  .tab,.chip { cursor:pointer; border:1px solid var(--line); background:transparent; color:var(--ink);
    padding:6px 14px; border-radius:999px; font:inherit; font-size:.85rem; }
  .tab.active,.chip.active { background:var(--gold); color:#14120f; border-color:var(--gold); }
  .stats { color:var(--dim); font-size:.85rem; margin-bottom:18px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
  .tile { background:var(--card); border:1px solid var(--line); border-radius:10px; overflow:hidden;
    cursor:pointer; transition:transform .12s ease, border-color .12s ease; }
  .tile:hover { transform:translateY(-3px); border-color:var(--gold); }
  .ph { aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
    border-bottom:1px dashed var(--line); color:var(--dim); font-size:.8rem; text-align:center; padding:12px; }
  .ph b { font-size:2.4rem; margin-bottom:6px; filter:grayscale(.4); }
  .tile img { width:100%; aspect-ratio:1; object-fit:cover; display:block; border-bottom:1px solid var(--line); }
  .meta { padding:10px 12px; display:flex; align-items:center; gap:8px; }
  .dot { width:12px; height:12px; border-radius:50%; border:1px solid var(--line); flex:none; background:var(--gold); }
  .alias { font-size:.95rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .roleTag { margin-left:auto; color:var(--dim); font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; flex:none; }
  dialog { background:var(--card); color:var(--ink); border:1px solid var(--gold); border-radius:12px;
    max-width:720px; width:92vw; padding:24px; }
  dialog::backdrop { background:rgba(0,0,0,.7); }
  dialog h2 { font-weight:normal; color:var(--gold); margin-bottom:4px; }
  dialog .r { color:var(--dim); font-size:.85rem; margin-bottom:14px; }
  dialog pre { white-space:pre-wrap; font:13px/1.55 ui-monospace,Consolas,monospace; background:#14120f;
    border:1px solid var(--line); border-radius:8px; padding:14px; max-height:46vh; overflow:auto; }
  .btn { margin-top:14px; background:var(--gold); color:#14120f; border:0; padding:9px 20px;
    border-radius:8px; font:inherit; cursor:pointer; }
  .btn.close { background:transparent; color:var(--ink); border:1px solid var(--line); }
</style>
</head>
<body>
<h1>&#127917; Mafia <span>Persona Decks</span></h1>
<p class="sub">One visual style, many eras. Click a card to read &amp; copy its generation prompt.</p>
<div class="tabs" id="tabs"></div>
<div class="chips" id="chips"></div>
<div class="stats" id="stats"></div>
<div class="grid" id="grid"></div>
<dialog id="dlg">
  <h2 id="dAlias"></h2>
  <div class="r" id="dRole"></div>
  <pre id="dPrompt"></pre>
  <button class="btn" id="dCopy">Copy prompt</button>
  <button class="btn close" onclick="document.getElementById('dlg').close()">Close</button>
</dialog>
<script>
const DATA = __DATA__;
const state = { setting: '__ALL__', team: '__ALL__' };
let current = null;
const tabsEl = document.getElementById('tabs'), chipsEl = document.getElementById('chips'),
      gridEl = document.getElementById('grid'), statsEl = document.getElementById('stats'),
      dlg = document.getElementById('dlg');

function paint() {
  tabsEl.querySelectorAll('.tab').forEach(t =>
    t.classList.toggle('active', t.dataset.val === state.setting));
  chipsEl.querySelectorAll('.chip').forEach(t =>
    t.classList.toggle('active', t.dataset.val === state.team));
  gridEl.innerHTML = '';
  let total = 0, have = 0;
  const settings = Object.entries(DATA.settings).filter(([n]) =>
    state.setting === '__ALL__' || n === state.setting);
  for (const [name, s] of settings) {
    for (const p of s.personas) {
      if (state.team !== '__ALL__' && p.team !== state.team) continue;
      total++; if (p.image_present) have++;
      gridEl.appendChild(tile(p));
    }
  }
  statsEl.textContent = `${total} cards · ${have} images ready · ${total - have} awaiting generation`;
}
function tile(p) {
  const d = document.createElement('div'); d.className = 'tile';
  if (p.image_present) {
    const img = document.createElement('img'); img.loading = 'lazy'; img.src = p.image;
    img.onerror = () => img.replaceWith(placeholder(p)); d.appendChild(img);
  } else { d.appendChild(placeholder(p)); }
  const m = document.createElement('div'); m.className = 'meta';
  const dot = document.createElement('span'); dot.className = 'dot'; dot.title = 'Accent: ' + p.accent;
  const a = document.createElement('span'); a.className = 'alias'; a.textContent = p.alias;
  const r = document.createElement('span'); r.className = 'roleTag'; r.textContent = p.role.replace(/-\d+$/, '');
  m.append(dot, a, r); d.appendChild(m);
  d.onclick = () => openCard(p);
  return d;
}
function placeholder(p) {
  const ph = document.createElement('div'); ph.className = 'ph';
  ph.innerHTML = `<b>${p.emoji}</b>${p.image_present ? '' : 'not generated yet'}`;
  return ph;
}
function fullText(p) { return p.prompt + '\n\nNegative prompt:\n' + p.negative; }
function openCard(p) {
  current = p;
  document.getElementById('dAlias').textContent = p.alias + ' (' + p.role + ')';
  document.getElementById('dRole').textContent =
    (DATA.settings[p.file.split('/')[0]]?.title || '') + ' · accent: ' + (p.accent || '?') +
    ' · file: ' + p.file;
  document.getElementById('dPrompt').textContent = fullText(p);
  dlg.showModal();
}
document.getElementById('dCopy').onclick = async () => {
  if (!current) return;
  await navigator.clipboard.writeText(fullText(current));
  const b = document.getElementById('dCopy'); b.textContent = 'Copied ✓';
  setTimeout(() => b.textContent = 'Copy prompt', 1200);
};

function btn(label, key, val, parent, cls) {
  const b = document.createElement('button');
  b.className = cls; b.textContent = label; b.dataset.key = key; b.dataset.val = val;
  b.onclick = () => { state[key] = val; paint(); };
  parent.appendChild(b);
}
btn('All settings', 'setting', '__ALL__', tabsEl, 'tab');
Object.keys(DATA.settings).forEach(n =>
  btn(DATA.settings[n].title || n, 'setting', n, tabsEl, 'tab'));
btn('All teams', 'team', '__ALL__', chipsEl, 'chip');
['town', 'mafia', 'solo'].forEach(t => btn(t, 'team', t, chipsEl, 'chip'));
paint();
</script>
</body>
</html>
HTML;

        return str_replace('__DATA__', $dataJson, $html);
    }
}

$root = __DIR__;
$jsonOnly = in_array('--json', $argv, true);

$builder = new PersonaDeckBuilder($root);
$deck = $builder->build();

file_put_contents(
    $root . '/index.json',
    json_encode($deck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
);
file_put_contents($root . '/gallery.html', $builder->renderGallery($deck));

$totalIssues = array_sum(array_map('count', $deck['issues']));

if ($jsonOnly) {
    echo json_encode(
        ['ok' => $totalIssues === 0, 'issues' => $deck['issues'], 'images' => array_map(static fn (array $s): array => $s['images'], $deck['settings'])],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    ) . "\n";
} else {
    echo "Persona deck report\n===================\n";
    foreach ($deck['settings'] as $name => $s) {
        printf(
            "%-28s cast %s · images %d/%d\n",
            $name,
            $s['cast_complete'] ? 'complete' : 'INCOMPLETE',
            $s['images']['present'],
            $s['images']['total'],
        );
        foreach ($s['issues'] as $issue) {
            echo "  ✗ {$issue}\n";
        }
    }
    echo $totalIssues === 0
        ? "\nOK — index.json + gallery.html regenerated, no issues.\n"
        : "\n{$totalIssues} issue(s) found — see above.\n";
}

exit($totalIssues === 0 ? 0 : 1);
