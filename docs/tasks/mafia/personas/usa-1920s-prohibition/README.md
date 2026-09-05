# Setting: USA 1920s Prohibition

Speakeasy noir: bootleggers, tommy guns, flapper dresses, art-deco hotel lobbies,
brick back alleys, rain and neon. The reference cast for the whole persona system.

**Era addendum (already embedded in every prompt below):** 1920s American city,
Prohibition era; pinstripe suits, fedoras, newsboy caps, cloche hats, fur stoles,
trench coats; props like tommy guns, violin cases, whiskey glasses, rotary phones,
police badges; backdrops of brick walls, bar glow, rain-streaked windows, cigar smoke.
Style core: `../_shared/style-core.md` (embedded verbatim in each prompt).

## Cast

| File | Persona | Role |
|---|---|---|
| civilian-01.md | "Scoop", corner newsboy | civilian |
| civilian-02.md | Trixie, speakeasy waitress | civilian |
| civilian-03.md | Sal, night-shift dockworker | civilian |
| civilian-04.md | Miss Pearl, church seamstress | civilian |
| civilian-05.md | "Two-Blade Tony", street barber | civilian |
| detective.md | Lt. Stone, police detective | detective |
| doctor.md | Doc Halsey, private physician | doctor |
| escort.md | Velvet Lena, cabaret singer | escort |
| bodyguard.md | Iron Mike, ex-boxer bodyguard | bodyguard |
| witness.md | Eddie the Sleepless, hotel clerk | witness |
| journalist.md | Jimmy Flash, tabby reporter | journalist |
| elder.md | Judge Whitmore, retired judge | elder |
| bomzh.md | Boxcar Bill, railroad hobo | bomzh |
| sniper.md | Annie of the Midway, sharpshooter | sniper |
| mafia-01.md | "Lefty" Malone, triggerman | mafia |
| mafia-02.md | Green-Visor Vic, mob accountant | mafia |
| godfather.md | Don Salvatore "The Orchid" | godfather |
| turncoat.md | Smiling Sammy, friendly fixer | turncoat |
| maniac.md | Blade Billy, vaudeville knife act | maniac |
| bandit.md | Casper, lone bank robber | bandit |
| satanist.md | Madame Vesper, séance medium | satanist |
| card-back.md | Tommy gun & fedora sunburst | deck back |

## Generation notes

- Generate a contact sheet first (`../_shared/contact-sheet.md`); lock seed/style-reference
  for the batch (master doc §3).
- Accent colors are pre-assigned per character — keep them when re-rolling.
- Optional night variant: re-light any portrait with "cold blue moonlight from one window"
  instead of the warm source; nothing else changes.
- Save every result as `<basename>.png` next to its prompt file.
- After changing prompts or dropping images, run `php build.php` in `..` (the personas
  folder) — it validates the deck and regenerates `index.json` + `gallery.html`.

## Imported images

(none yet — if you download licensed/public-domain portraits instead of generating,
list `file → source URL → license` here.)
