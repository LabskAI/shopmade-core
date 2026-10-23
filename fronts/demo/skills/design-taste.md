---
name: design-taste
description: Design judgement for the front — read the brief first, one accent, sane type, dense pages, honest copy, no AI tells; adapted from Taste Skill (MIT) to what a front build can change.
---

# Design taste

A front build changes three things: `theme/tokens.css`, the markdown pages and `facts.yaml`. Layout, navigation, footer and the stylesheet are the kit's. Within that, these rules stop the front from looking like every other AI-made site.

## Read the brief before changing anything

- State one line first: "Reading this as: a `<kind of business>` front for `<audience>`, `<tone>`, so `<direction>`." Then build to that line.
- The audience picks the look, not your taste. A cleaning crew's customers want to find the phone number and trust the people; a design studio's visitors want to be impressed. Build for the first unless the brief says otherwise.
- Existing brand assets (logo, colors, photos the owner provided, `brand-voice`, `design-direction`) are the starting point, not optional input.
- Ambiguous brief: ask one question, not a list. Otherwise decide and say what you decided.

## Type (tokens: --font-display, --font-body, --text-*, --leading, --measure)

- Hierarchy comes from weight and size steps, not from a huge `--text-display`. One `h1` per page.
- Sans is the safe default for a service business. Reach for a serif display face only when the owner's brand already uses one or the brief asks for an editorial feel, and say why.
- Emphasize a word with bold or italic of the same family. Never mix a second family into a headline for "interest".
- Body at `--text-base` 17px or more, `--leading` around 1.6, `--measure` 60 to 70 characters. Do not widen paragraphs.
- Sentence case for headings. No Title Case On Every Heading, no all-caps labels.

## Color (tokens: --color-bg, --color-surface, --color-ink, --color-muted, --color-line, --color-accent, --color-link)

- One accent, used for the call button and links on every page. Pick it once and keep it; no second accent "for the footer".
- Ink is off-black, never `#000000`. Keep contrast at WCAG AA or better on every token pair.
- Keep saturation moderate. No purple or blue glow gradients, no neon.
- Avoid the reflex palette for "premium" service brands: cream or bone background plus brass, clay or ochre accent plus espresso text. If the owner's brand is genuinely warm and crafted, keep it and say why. Otherwise rotate: deep green plus bone, off-white plus off-black plus one bright accent, cobalt plus cream, terracotta plus slate.
- One gray family per front. Do not mix warm and cool grays between background, surface and line.

## Pages and density

- Each section: a headline of eight words or fewer, a paragraph of twenty-five words or fewer, then one list, one link or one call to action. More needs a reason.
- A list longer than five items gets grouped under sub-headings or moved to its own page. Do not ship a wall of bullets.
- Do not reach for three identical items by default. Say what is different about each service, city or step.
- A testimonial is three lines at most, with a name and a role or city, in real typographic quotes. No dashes inside the quote as decoration.
- No section numbers or eyebrow labels ("01 / Services", "Brand · No. 01"), no version or "beta" tags.
- Every page keeps the kit's structure: the owner's phone and cities stay reachable from any section.

## Copy

- Concrete verbs and nouns. Never "elevate", "seamless", "unleash", "next-gen", "revolutionize", "delve", "tapestry", "in the world of".
- No invented numbers, ratings, years in business, team sizes or client counts. A number comes from `facts.yaml` or from the owner, or it is not written. `no-claims` and `facts.never_write` win over everything here.
- No placeholder names, lorem ipsum, or "Jane Doe". Write the real draft copy or leave the section out.
- No exclamation marks. Active voice. Plain words the crew already uses (job, customer, visit).
- Before proposing, re-read every visible string in every language asked for. Rewrite anything that is grammatically off, has an unclear referent, or sounds like a model trying to be clever. Boring and clear beats cute.

## Finish the work

- Deliver every page and every language the brief names. No "the other pages follow the same pattern", no partial drafts, no TODOs left in markdown.
- A redesign goes in this order: tokens for type, then color, then page structure and density, then copy. Small reviewable changes over a rewrite.
- Run `front:lint` before you propose. A warning about never_write, currency or cities is a blocker, not a nit.

---

Adapted for the Shopmade front kit from [Taste Skill](https://github.com/Leonxlnx/taste-skill) (`taste-skill`, `redesign-skill`, `output-skill`; commit b482f7a, 2026-10-07), MIT License, Copyright (c) 2026 Leonxlnx. Rules that only apply to component code, animation, dark mode or asset generation were left out because a front build cannot change those.
