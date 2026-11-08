---
name: page-speed
description: Keep every page at Lighthouse 100 and readable by search engines and AI agents — sscss classes, no extra scripts, sized images, honest headings.
---

# Page speed and readability

Every Shopmade site must score 100 in all four Lighthouse categories on phones and desktops. The kit does most of it; these rules keep edits from undoing it.

## Styling

- Layout and spacing on components come from [sscss](https://sscss.herton.work) classes in `class="…"`: `:::section class="py9"`, `:::cards class="m-c2"`. The page carries only the rules it uses. Scale: spacing `0–10` (0 to 8rem), type `t0–t9`, `m-` from 768px, `l-` from 1024px.
- Brand looks go in `theme/tokens.css` (colors, type, radius) and `theme/components.css` (the `front-*` classes). Keep components.css short; it is inlined on every page.
- Never fade content in with opacity. Motion is the kit's `an-rise` (transform only), already on sections, cards and steps.

## Scripts

- No script is the default. The menu, the FAQ and the forms work without one.
- If something must react on the page, it uses [Minim](https://minim.herton.work) and goes in a package, not in `assets/`.

## Images

- Photos as WebP or AVIF, under about 200 KB, at the size they are shown. Art and icons as SVG.
- The hero is the only image that loads first; everything else lazy-loads by itself.
- Every image gets real `alt` text, or `alt=""` when it is decoration.

## Text for people, search and agents

- One `# H1` per page, then `##` sections, then `###`. Never skip a level for looks.
- `title` under 60 characters, `description` under 155, both specific to the page.
- Link text says where it goes ("See our services"), never "click here".
- Facts (phone, hours, cities, services) live in facts.yaml; pages refer to them so llms.txt and the JSON-LD stay right.
