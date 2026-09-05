# Mafia Persona Cards — Graphical Character Portraits

> Companion to `todo.mafia.md` (rev 4) and `tasks/mafia/interface-ux.md` §8 (event images).
> Defines the art direction and the prompt deck layout for **persona portrait cards**:
> dozens of unique characters rendered in ONE unified visual style across DIFFERENT
> era settings (USA 1920s bootleggers, Soviet 1970s crime films, cartoon gangster city, …).
> Prompt decks live in `tasks/mafia/personas/<setting>/`.

## 1. Concept

- A **role** is a mechanic (`roles.json`, 16 roles). A **persona** is a face: name, look,
  costume, props. Roles and personas are dealt independently — any seat wears any persona.
- Each setting folder is a complete replacement cast ("skin"): every role from the catalog
  has a dedicated portrait, plus 5 unique civilian variants (games seat up to 6+ civilians).
- AI filler bots reuse the same persona deck for avatars and nicknames (`bot_players.json`
  persona pool can later point at these files), so bots stop being faceless.
- Portraits double as **role-deal reveal cards** (DM night screens) alongside the flat event
  images from `interface-ux.md` §8.

## 2. Directory layout

```
docs/tasks/mafia_persons.md              ← this plan (art direction + workflow)
docs/tasks/mafia/personas/
├── build.php                            ← deck validator + generator for index.json / gallery.html
├── index.json                           ← machine-readable catalog (generated, commit it)
├── gallery.html                         ← self-contained offline review gallery (generated)
├── _shared/style-core.md                ← canonical STYLE CORE + negative prompt (single source)
├── _shared/contact-sheet.md             ← style-lock 2×2 sheet prompt (run first per setting)
├── usa-1920s-prohibition/               ← 1 folder = 1 setting = full cast
│   ├── README.md                        ← cast table + generation notes for this setting
│   ├── civilian-01.md … civilian-05.md  ← 5 unique townsfolk
│   ├── detective.md doctor.md escort.md bodyguard.md witness.md
│   ├── journalist.md elder.md bomzh.md sniper.md
│   ├── mafia-01.md mafia-02.md godfather.md turncoat.md
│   ├── maniac.md bandit.md satanist.md  ← 20 character prompts
│   └── card-back.md                     ← deck back (hidden-role side)
├── soviet-1970s-detective/              ← same 21-file shape
└── toon-gangster-city/                  ← same 21-file shape
```

**File contract:** `1 file = 1 ready-to-paste generation prompt`. Generated or downloaded
images are saved next to their prompt with the same basename (`detective.md` → `detective.png`).
PNG is the target format (square, ≥1024×1024).

## 3. Unified style system

All settings share one rendering DNA — the **STYLE CORE** (canonical copy in
`_shared/style-core.md`, embedded verbatim in every prompt): vintage gouache-and-ink card,
waist-up single figure, chiaroscuro from one warm light, muted desaturated palette with
exactly ONE saturated accent color per card, aged paper grain, thin double art-deco border.
Only the middle of the prompt changes: era costume, props, backdrop. Different epochs,
same deck of cards. The one-accent-color rule makes roles scannable at Telegram thumbnail size.

Per-setting addendum (era layer) lives in each folder's README; the toon setting swaps
proportions (big head, exaggerated features) but keeps the same core technique, lighting,
paper and frame — so the whole library stays visually coherent.

Consistency techniques when batch-generating:

- Fix the seed within a setting once a good anchor image exists; use it as style reference
  (`--sref <url>` in Midjourney, IP-Adapter / LoRA in SDXL/Flux).
- Generate a 2×2 contact sheet first, pick the winner, then lock style and vary subjects.
- Never change the core wording between files — only subject + backdrop sentences differ.

## 4. Recognizability policy (real people, films, cartoons)

Prompts aim for *instantly readable archetypes*: the hoarse commissar in a flat cap,
the Don with a rose boutonniere, the smiling baker with a tommy gun in the bread basket.
Inspiration sources are fictional screen types and historical eras; where a real actor's or
celebrity's likeness would be the shortcut, the prompts describe the characteristic features
instead (silhouette, hat, squint, voice-implying grin). Reasons: most generators reject
named-person requests, and shipped game assets must not carry real-likeness rights risk.
Fictional-character vibes (Godfather-style don, Soviet-film pickpocket) are fair game.

## 5. Sourcing images from the internet

Allowed only under a license compatible with shipping in an open-source module:
public domain (pre-1930 photos, Wikimedia Commons PD mark), CC0, or purchased stock with
commercial rights. For each imported file record the source URL + license in the setting's
README (`Imported:` section). Never hotlink; download into the folder. No celebrity photos.

## 6. Generation workflow

1. Pick a setting folder; run `_shared/contact-sheet.md` once to pick the style anchor and
   lock the seed / style reference for the whole batch (§3).
2. Open the folder's README (cast table lists all 21 files incl. the deck back).
3. Copy a character file's `## Prompt` block into your generator (Midjourney / DALL-E /
   Flux / SDXL); append its `## Negative` if the tool supports it.
4. Generate, curate, save as `<file-basename>.png` next to the prompt (or drop a properly
   licensed downloaded image there instead).
5. Run `php build.php` in the personas folder — validates the deck (cast completeness,
   filename↔role match, style-marker drift, accent uniqueness) and regenerates
   `index.json` + `gallery.html`. Exit code `0` = deck valid.
6. Review progress in `gallery.html` (open in any browser: setting/team tabs, click a card
   → full prompt with one-click copy).
7. Commit prompts, images and regenerated artifacts. Update the status checklist below.

## 7. Deck tooling

- `build.php` is dependency-free PHP ≥8.2, runnable from Windows or WSL.
- It parses each prompt file's H1 (`# <token> — <alias> (role: <role>)`), the `Accent:`
  sentence and both sections; unexpected/missing files are reported per setting.
- `index.json` shape: `{settings: {<setting>: {title, cast_complete, images{present,total},
  personas[{file,image,image_present,role,team,emoji,alias,accent,prompt,negative}]}}}` —
  this is what the future module integration consumes (§8): no runtime parsing of markdown.
- The gallery is fully offline/self-contained (no CDN), embeds the same data as JSON.

## 8. Integration into the mafia module

- Final assets move to package `telegram-bot-module-mafia/resources/images/personas/<setting>/<persona>.png`.
- Sent via `sendPhoto` through the outbound pipeline; path → `file_id` caching already planned
  (`Images/FileIdCache.php`) — persona cards use the same cache keyed by filename hash.
- Room creation wizard gains a "setting" picker (default: random); snapshot stores
  `setting_id`; presenters resolve portraits from it. Missing file ⇒ fallback emoji avatar
  (never blocks a game — art is decorative, not functional).
- Bot players draw nickname+emoji pairs from the same persona names (ru/en aliases in lang packs).

## 9. Roadmap — future settings (one folder each, same 21-file shape)

| Setting | Era/mood idea |
|---|---|
| `postwar-1940s-nyc` | Godfather-era jazz clubs, zoot suits, steam |
| `wild-west-frontier` | Sheriff vs. rustlers, saloon noir |
| `neon-modern-syndicate` | Modern corporate crime, glass towers |
| `retro-future-lunarport` | Sci-fi dome city smuggling ring |

## 10. Status

| Item | Status |
|---|---|
| Art direction + style core defined | ✅ this doc + `_shared/style-core.md` |
| Style-lock contact-sheet prompt | ✅ `_shared/contact-sheet.md` |
| Deck tooling (validator, index.json, gallery) | ✅ `build.php`, run: `php build.php` → exit 0 |
| `usa-1920s-prohibition/` prompts (21) | ✅ prompts · ⬜ images (0/22 incl. card-back) |
| `soviet-1970s-detective/` prompts (21) | ✅ prompts · ⬜ images (0/22) |
| `toon-gangster-city/` prompts (21) | ✅ prompts · ⬜ images (0/22) |
| Images generated & committed | ⬜ |
| Module integration (setting picker, FileIdCache, fallback emoji) | ⬜ Phase 2 of todo.mafia.md |
