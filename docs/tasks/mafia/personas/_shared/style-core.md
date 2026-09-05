# STYLE CORE — canonical block (embed verbatim in every persona prompt)

Single source of truth for the unified visual style across all settings
(`../../mafia_persons.md` §3). Copy the blocks below unchanged; only the
subject and backdrop sentences may differ per character.

## Opening (identity)

```text
Vintage gouache-and-ink game-card portrait of a single character, waist-up,
centered, facing the viewer.
```

## Closing (technique — never reword)

```text
Dramatic chiaroscuro lighting from a single warm source, deep shadows, muted
desaturated period palette with exactly one saturated accent color, aged
textured paper background, subtle film grain, thin double art-deco border
frame around the card, painterly visible brushwork, no photorealism, no text,
square 1:1 game card.
```

## Shared negative prompt

```text
photo, photorealistic, 3d render, anime chibi, modern clothes, smartphone,
readable text, letters, numbers, watermark, logo, signature, cropped frame,
extra fingers, deformed hands, multiple people, duplicate face, blurry,
oversaturated neon colors
```

## Rules

- Exactly ONE saturated accent color per card (the hat, the scarf, the rose…).
  Choose it in the subject sentence ("accent: his red flat cap").
- Era changes only costume/props/backdrop. Lighting, palette logic, paper,
  grain, frame stay identical in every setting.
- Toon setting keeps this core but adds "retro cartoon proportions, big head,
  exaggerated features" right after the opening line.
- If a generator needs a fixed seed/style reference for batch consistency:
  lock it once per setting (see master doc §3), then vary subjects only.
