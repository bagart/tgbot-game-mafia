# Setting: Toon Gangster City

Rubber-hose-adjacent cartoon gangsters — big heads, exaggerated features,
impossible props — but rendered with the SAME vintage gouache card technique,
chiaroscuro lighting, aged paper and art-deco frame as every other setting.
Think golden-age animation shorts transplanted into noir lighting. Family-safe
menace; comedy in the silhouettes.

**Setting addendum (already embedded in every prompt below):** right after the
style-core opening line each prompt inserts "retro cartoon proportions, oversized
head, exaggerated rubbery features"; backdrops are cartoon city blocks, carnival
bulbs, velvet club ropes. Negative prompt adds `uncanny realism`.
Style core: `../_shared/style-core.md`.

## Cast

| File | Persona | Role |
|---|---|---|
| civilian-01.md | Frankie, hot-dog cart vendor | civilian |
| civilian-02.md | Missus Beakley, bird-watching lady | civilian |
| civilian-03.md | Bottle-cap Benny, milk delivery kid | civilian |
| civilian-04.md | Petunia, flower girl | civilian |
| civilian-05.md | Grandpa Chessnut, park chess master | civilian |
| detective.md | Inspector Bulldog O'Grady | detective |
| doctor.md | Doc Wobbles, nervous medic | doctor |
| escort.md | Belle Canto, torch diva | escort |
| bodyguard.md | Tiny the Gentle Giant | bodyguard |
| witness.md | Owl-eyed Otto, night porter | witness |
| journalist.md | Paparazzi Pip, freckled reporter | journalist |
| elder.md | Granny Rollingpin | elder |
| bomzh.md | Professor Pigeonfeather | bomzh |
| sniper.md | Corky the Midway Markswoman | sniper |
| mafia-01.md | Tommy-gun Timmy, weasel triggerman | mafia |
| mafia-02.md | Abacus Al, accountant mobster | mafia |
| godfather.md | Don Piccolo | godfather |
| turncoat.md | Baker Bruno | turncoat |
| maniac.md | Marcello the Mime, knife juggler | maniac |
| bandit.md | Raccoon Rocky, sack robber | bandit |
| satanist.md | Madame Marionette, puppeteer | satanist |
| card-back.md | Banana tommy & pie sunburst | deck back |

## Generation notes

- Keep "retro cartoon proportions" phrase intact when editing subjects — it is the
  toon differentiator; everything else is shared style core.
- Start with `_shared/contact-sheet.md` to lock the style anchor/seed.
- Optional night variant: re-light any portrait with "cold blue moonlight from one window"
  instead of the warm source; nothing else changes.
- Save results as `<basename>.png` next to their prompt files.
- After changing prompts or dropping images, run `php build.php` in `..` — it validates
  the deck and regenerates `index.json` + `gallery.html`.

## Imported images

(none yet — licensed/public-domain downloads go here with source + license.)
