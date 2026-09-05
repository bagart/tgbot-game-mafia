<?php

/**
 * Mafia docs-refactor verification gate (temporary; deleted with _refactor/ at Phase L).
 *
 * Invariants checked:
 *  1. index.md registry links resolve to real files.
 *  2. Depends entries resolve (ranges/wildcards expanded); graph is acyclic.
 *  3. Declared execution order is dependency-consistent (whitelisted exceptions only).
 *  4. Task statuses match between index.md and task files.
 *  5. Matrix target cells use canonical registry IDs (padded form).
 *  6. Every legacy feature ID from todo.mafia.md is routed by the matrix (range-aware).
 *  7. Deletion-list legacy sources still exist before Phase L.
 *
 * Usage: php _refactor/verify.php   (exit 0 = PASS, 1 = FAIL)
 */

declare(strict_types=1);

$failures = [];
$ok = static function (string $msg): void { echo "  ok  {$msg}\n"; };
$fail = static function (string $msg) use (&$failures): void {
    $failures[] = $msg;
    echo "FAIL  {$msg}\n";
};

$base = dirname(__DIR__);
$index = (string) file_get_contents($base . '/index.md');
$matrix = (string) file_get_contents(__DIR__ . '/migration-matrix.md');
$todo = (string) file_get_contents($base . '/todo.mafia.md');

// 1) Registry ----------------------------------------------------------------
echo "1) registry links\n";
preg_match_all('/^\| ([A-Z]+-\d+) \| \[([^\]]+)\]\(([^)]+)\) \|([^|]*)\| (TODO|WIP|DONE) \|/m', $index, $rows, PREG_SET_ORDER);
$registry = [];
foreach ($rows as $r) {
    $depsCell = trim($r[4]);
    $registry[$r[1]] = [
        'link' => $r[3],
        'deps' => ($depsCell === '' || $depsCell === '—') ? [] : array_map('trim', explode(',', $depsCell)),
        'status' => $r[5],
    ];
}
if ($registry === []) {
    $fail('index.md registry parsed empty — table format drifted');
}
$linkErrors = [];
foreach ($registry as $id => $info) {
    if (!is_file($base . '/' . $info['link'])) {
        $linkErrors[] = "{$id}: {$info['link']}";
    }
}
$linkErrors === []
    ? $ok(count($registry) . ' tasks; links resolve')
    : $fail('broken links: ' . implode('; ', $linkErrors));

// 2) Dependencies ------------------------------------------------------------
echo "2) dependencies\n";
$expand = static function (array $cells) use ($registry): array {
    $out = [];
    foreach ($cells as $cell) {
        $cell = trim($cell, ". \t");
        if ($cell === '' || $cell === '—' || str_starts_with($cell, 'all ')) {
            continue;
        }
        // split compound cells like "R-03, G-12" / "M-01..M-06" / "API-01 (blocks API-14)"
        foreach (preg_split('/,\s*/', $cell) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_ends_with($part, '*')) {                       // G-*
                $prefix = rtrim($part, '*');
                foreach (array_keys($registry) as $id) {
                    if (str_starts_with($id, $prefix)) {
                        $out[] = $id;
                    }
                }
            } elseif (preg_match('/^([A-Z]+-)(\d+)\.\.([A-Z]+-)?(\d+)/', $part, $m)) {
                $prefix = $m[1];
                foreach (range((int) $m[2], (int) $m[4]) as $n) {
                    $out[] = $prefix . sprintf('%02d', $n);
                }
            } elseif (preg_match('/^([A-Z]+-\d+)\s*\(/', $part, $m)) { // "API-01 (blocks API-14)"
                $out[] = $m[1];
            } elseif (preg_match('/^[A-Z]+-\d+$/', $part)) {
                $out[] = $part;
            } else {
                $failures[] = "unparseable dep cell '{$part}'";
            }
        }
    }
    return array_values(array_unique($out));
};

$graph = [];
foreach ($registry as $id => $info) {
    $graph[$id] = [];
    foreach ($expand($info['deps']) as $dep) {
        if (!isset($registry[$dep])) {
            $fail("{$id}: unknown dependency '{$dep}'");
            continue;
        }
        $graph[$id][] = $dep;
    }
}

$color = [];
$cycles = [];
$dfs = static function (string $u, array $path) use (&$dfs, &$color, &$cycles, $graph): void {
    $color[$u] = 1;
    foreach ($graph[$u] as $v) {
        if (($color[$v] ?? 0) === 1) {
            $slice = array_slice($path, max(0, array_search($v, $path, true)));
            $cycles[] = implode(' -> ', [...$slice, $v]);
        } elseif (($color[$v] ?? 0) === 0) {
            $dfs($v, [...$path, $v]);
        }
    }
    $color[$u] = 2;
};
foreach (array_keys($graph) as $id) {
    if (($color[$id] ?? 0) === 0) {
        $dfs($id, [$id]);
    }
}
$cycles === [] ? $ok('deps resolve; graph acyclic') : $fail('cycle: ' . implode('; ', $cycles));

// 3) Execution order ---------------------------------------------------------
echo "3) execution-order consistency\n";
preg_match('/## Execution order\s*\n\s*\n(.+?)\n\n/s', $index, $om);
$orderLine = $om[1] ?? '';
$phases = ['Foundation', 'API contract', 'Roles', 'Game engine', 'Telegram client',
    'Generic client', 'Social', 'Mobile', 'Progression', 'Media', 'Platform/Scale', 'Delivery/Cleanup'];
$missingPhases = array_filter($phases, static fn ($p) => !str_contains($orderLine, $p));
$missingPhases === [] ? $ok('order line lists all 12 phases')
    : $fail('order line missing phases: ' . implode(', ', $missingPhases));

$phaseRank = ['F-' => 0, 'API-' => 1, 'R-' => 2, 'G-' => 3, 'TG-' => 4, 'C-' => 5,
    'S-' => 6, 'M-' => 7, 'P-' => 8, 'MEDIA-' => 9, 'OPS-' => 10, 'D-' => 11];
$rankOf = static function (string $id) use ($phaseRank): int {
    foreach ($phaseRank as $prefix => $rank) {
        if (str_starts_with($id, $prefix)) {
            return $rank;
        }
    }
    return PHP_INT_MAX;
};
$exceptions = ['G-12->R-06']; // documented single cross exception
$violations = [];
foreach ($graph as $id => $deps) {
    foreach ($deps as $dep) {
        if ($rankOf($dep) > $rankOf($id) && !in_array("{$dep}->{$id}", $exceptions, true)) {
            $violations[] = "{$dep} -> {$id}";
        }
    }
}
$violations === [] ? $ok('dependencies follow declared order (whitelist: ' . implode(', ', $exceptions) . ')')
    : $fail('order violations: ' . implode('; ', $violations));

// 4) Status sync -------------------------------------------------------------
echo "4) status sync index <-> files\n";
$drift = [];
foreach ($registry as $id => $info) {
    $path = $base . '/' . $info['link'];
    if (!is_file($path)) {
        continue;          // broken link already reported in step 1
    }
    $head = (string) file_get_contents($path, false, null, 0, 600);
    if (!preg_match('/^Status:\s*(\S+)/mi', $head, $sm)) {
        $drift[] = "{$id}: no Status line";
    } elseif (strtoupper($sm[1]) !== $info['status']) {
        $drift[] = "{$id}: index={$info['status']} file={$sm[1]}";
    }
}
$drift === [] ? $ok('statuses in sync') : $fail(implode('; ', $drift));

// 5) Matrix targets canonical ------------------------------------------------
echo "5) matrix target IDs canonical\n";
$targetErrors = [];
foreach (explode("\n", $matrix) as $line) {
    if (!str_starts_with($line, '|') || str_contains($line, '---')) {
        continue;
    }
    $cells = array_map('trim', explode('|', $line));
    array_shift($cells);   // leading empty
    array_pop($cells);     // trailing empty
    if (count($cells) < 2 || !str_contains($cells[0], '§')) {
        continue;          // only matrix body rows carry § in the first cell
    }
    $targetCell = count($cells) >= 4 && preg_match('/^(todo|done|done-in-index)$/i', end($cells))
        ? $cells[count($cells) - 2]
        : end($cells);
    // wildcards: "C-* screens"
    if (preg_match_all('/\b([A-Z]+)-\*/', $targetCell, $wm)) {
        foreach ($wm[1] as $prefix) {
            $has = (bool) array_filter(array_keys($registry), static fn ($id) => str_starts_with($id, $prefix . '-'));
            $has ? null : $targetErrors[] = "wildcard '{$prefix}-*' matches no phase";
        }
    }
    foreach (preg_match_all('/\b([A-Z]+)-(\d+)\b/', $targetCell, $im, PREG_SET_ORDER) ? $im : [] as $m) {
        [$full, $group, $num] = $m;
        // Legacy feature groups never exist as registry IDs — any citation is deliberate.
        // OPS overlaps both worlds: unpadded singles are legacy citations, padded are tasks.
        $isLegacyGroup = in_array($group, ['PLAT', 'CORE', 'RUN', 'GRP', 'ROOM', 'WEB', 'BOT',
            'I18N', 'DISC', 'RAT', 'IMG', 'ADV', 'ONB', 'MOD'], true);
        if ($isLegacyGroup || ($group === 'OPS' && (int) $num < 10 && !str_starts_with($num, '0'))) {
            continue;
        }
        $canonical = $group . '-' . sprintf('%02d', (int) $num);
        if (!isset($registry[$canonical])) {
            $targetErrors[] = "row '{$cells[0]}': target '{$full}' has no task '{$canonical}'";
        }
    }
}
$targetErrors === [] ? $ok('targets resolve to registry IDs') : $fail(implode('; ', array_slice($targetErrors, 0, 10)));

// 6) Legacy feature coverage -------------------------------------------------
echo "6) legacy feature coverage\n";
$groups = 'PLAT|CORE|RUN|GRP|ROOM|WEB|BOT|I18N|DISC|RAT|IMG|ADV|ONB|MOD|OPS';
preg_match_all('/\b(' . $groups . ')-(\d+)\b/', $todo, $gm, PREG_SET_ORDER);
$legacy = [];
foreach ($gm as [$full, $g, $n]) {
    $legacy["{$g}-" . sprintf('%02d', (int) $n)] = true;
}
$routed = [];
foreach (explode("\n", $matrix) as $line) {
    preg_match_all('/\b(' . $groups . ')-(\d+)\.\.(\d+)\b/', $line, $rm, PREG_SET_ORDER);
    foreach ($rm as [$full, $g, $a, $b]) {
        foreach (range((int) $a, (int) $b) as $n) {
            $routed["{$g}-" . sprintf('%02d', $n)] = true;
        }
    }
    preg_match_all('/\b(' . $groups . ')-\d+\b/', $line, $im);
    foreach ($im[0] as $full) {
        $routed[preg_replace_callback('/-(\d+)$/', static fn ($x) => '-' . sprintf('%02d', $x[1]), $full)] = true;
    }
}
$uncovered = array_diff(array_keys($legacy), array_keys($routed));
$uncovered === [] ? $ok(count($legacy) . ' legacy feature IDs routed by matrix')
    : $fail('uncovered legacy IDs: ' . implode(', ', $uncovered));

// 7) Pre-deletion sanity -----------------------------------------------------
echo "7) deletion-list sources present pre-L\n";
$sources = ['todo.mafia.md', 'interface-ux.md', 'ui-patterns.md', 'competitive-analysis.md',
    'playability.md', 'mafia_persons.md'];
$missingSources = array_filter($sources, static fn ($f) => !is_file($base . '/' . $f));
$missingSources === [] ? $ok('all legacy sources intact') : $fail('missing pre-L: ' . implode(', ', $missingSources));

printf("\nVERIFY: %s (%d tasks)\n", $failures === [] ? 'PASS' : 'FAIL', count($registry));
exit($failures === [] ? 0 : 1);
