{{-- Front kit stylesheet. Looks come from the company's theme/tokens.css custom properties; fallbacks keep a bare front readable. --}}
<style>
    *, *::before, *::after { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body {
        margin: 0;
        background: var(--color-bg, #ffffff);
        color: var(--color-ink, #1b1b1b);
        font-family: var(--font-body, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif);
        font-size: var(--text-base, 1.0625rem);
        line-height: var(--leading, 1.6);
    }
    a { color: var(--color-link, var(--color-accent, #0b57a4)); }
    img { max-width: 100%; height: auto; }
    :focus-visible { outline: 2px solid var(--color-accent, #0b57a4); outline-offset: 2px; }
    .wrap { width: 100%; max-width: var(--max-width, 72rem); margin: 0 auto; padding: 0 var(--space-4, 1rem); }
    .skip { position: absolute; left: -9999px; }
    .skip:focus { left: var(--space-4, 1rem); top: var(--space-2, 0.5rem); z-index: 10; padding: var(--space-2, 0.5rem) var(--space-4, 1rem); background: var(--color-surface, #f2f2f2); }

    .site-header { background: var(--color-surface, #f6f6f6); border-bottom: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); }
    .header-row { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3, 0.75rem) var(--space-6, 1.5rem); padding-top: var(--space-3, 0.75rem); padding-bottom: var(--space-3, 0.75rem); }
    .brand { margin-right: auto; color: inherit; font-family: var(--font-display, inherit); font-size: 1.125rem; font-weight: 700; text-decoration: none; }
    .site-nav ul, .lang-switch ul { display: flex; flex-wrap: wrap; gap: var(--space-2, 0.5rem) var(--space-4, 1rem); margin: 0; padding: 0; list-style: none; }
    .site-nav a, .lang-switch a { color: inherit; text-decoration: none; }
    .site-nav a[aria-current="page"], .lang-switch a[aria-current="true"] { font-weight: 700; text-decoration: underline; text-underline-offset: 0.3em; }
    .lang-switch { font-size: 0.875rem; }
    .call {
        display: inline-block;
        padding: var(--space-2, 0.5rem) var(--space-4, 1rem);
        border-radius: var(--radius, 0);
        background: var(--color-accent, #0b57a4);
        color: var(--color-accent-ink, #ffffff);
        font-weight: 600;
        text-decoration: none;
    }

    main { padding-top: var(--space-8, 2.5rem); padding-bottom: var(--space-8, 2.5rem); }
    .prose { max-width: var(--measure, 68ch); }
    .prose h1 { margin: 0 0 var(--space-4, 1rem); font-family: var(--font-display, inherit); font-size: var(--text-h1, 2.25rem); line-height: 1.15; }
    .prose h2 { margin: var(--space-8, 2.5rem) 0 var(--space-3, 0.75rem); font-family: var(--font-display, inherit); font-size: var(--text-h2, 1.5rem); line-height: 1.25; }
    .prose h3 { margin: var(--space-6, 1.5rem) 0 var(--space-2, 0.5rem); font-size: 1.125rem; }
    .prose p, .prose ul, .prose ol { margin: 0 0 var(--space-4, 1rem); }
    .prose li + li { margin-top: var(--space-1, 0.25rem); }
    .layout-home .prose h1 { font-size: var(--text-display, clamp(2.25rem, 6vw, 3.5rem)); }
    .layout-home .prose h1 + p { font-size: var(--text-lead, 1.25rem); color: var(--color-muted, inherit); }

    .site-footer { padding-top: var(--space-8, 2.5rem); padding-bottom: var(--space-8, 2.5rem); background: var(--color-surface, #f6f6f6); border-top: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); font-size: 0.9375rem; }
    .footer-grid { display: grid; gap: var(--space-6, 1.5rem); grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
    .site-footer h2 { margin: 0 0 var(--space-2, 0.5rem); font-size: 0.8125rem; letter-spacing: 0.08em; text-transform: uppercase; }
    .site-footer ul { margin: 0; padding: 0; list-style: none; }
    .site-footer li + li { margin-top: var(--space-1, 0.25rem); }
    .legal { margin: var(--space-6, 1.5rem) 0 0; color: var(--color-muted, inherit); }

    /* Buttons and header call to action */
    .btn { display: inline-block; padding: var(--space-2, 0.5rem) var(--space-4, 1rem); border: 2px solid var(--color-accent, #0b57a4); border-radius: var(--radius, 0); font-weight: 600; text-decoration: none; }
    .btn-primary { background: var(--color-accent, #0b57a4); color: var(--color-accent-ink, #ffffff); }
    .btn-outline { background: transparent; color: var(--color-accent, #0b57a4); }
    .brand-logo { display: block; height: 2rem; width: auto; }

    /* Hero (frontmatter hero:) */
    .front-hero { display: grid; gap: var(--space-6, 1.5rem); align-items: center; margin-bottom: var(--space-8, 2.5rem); }
    .front-hero__media { margin: 0; }
    .front-hero__image, .front-hero__video { display: block; width: 100%; height: auto; border-radius: var(--radius, 0); }
    .front-hero__caption { margin-top: var(--space-2, 0.5rem); color: var(--color-muted, inherit); font-size: 0.875rem; }
    .layout-home .front-hero__body h1 { font-size: var(--text-display, clamp(2.25rem, 6vw, 3.5rem)); }
    @media (min-width: 56rem) { .layout-home .front-hero { grid-template-columns: 1fr 1fr; } }

    /* Components: front-<name>, front-<name>--<variant>, front-<name>__<part> */
    .front-kicker { margin: 0 0 var(--space-2, 0.5rem); color: var(--color-accent, #0b57a4); font-size: 0.8125rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .front-icon { display: inline-flex; color: var(--color-accent, #0b57a4); margin-bottom: var(--space-2, 0.5rem); }
    .front-icon img { display: block; }
    .front-chips { display: flex; flex-wrap: wrap; gap: var(--space-2, 0.5rem); margin: 0 0 var(--space-2, 0.5rem); padding: 0; list-style: none; }
    .front-chip { display: inline-block; margin: 0; padding: 0.125rem var(--space-2, 0.5rem); border: 1px solid var(--color-line, rgba(0, 0, 0, 0.16)); border-radius: 999px; font-size: 0.8125rem; font-weight: 600; }
    .prose .front-section, .prose .front-cards, .prose .front-steps, .prose .front-flow, .prose .front-faq, .prose .front-cta, .prose .front-proof, .prose .front-timeline, .prose .front-form { max-width: none; margin: var(--space-8, 2.5rem) 0; }
    .prose:has(.front-cards, .front-flow, .front-section--split, .front-steps) { max-width: none; }
    .front-section--muted { padding: var(--space-6, 1.5rem); background: var(--color-surface, #f6f6f6); }
    .front-section--split { display: grid; gap: var(--space-6, 1.5rem); align-items: center; }
    @media (min-width: 56rem) { .front-section--split { grid-template-columns: 1fr 1fr; } }
    .front-section__media { margin: 0; }
    .front-cards__grid { display: grid; gap: var(--space-4, 1rem); grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
    .front-card { display: flex; flex-direction: column; border: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); border-radius: var(--radius, 0); background: var(--color-bg, #ffffff); overflow: hidden; }
    .front-card__body { padding: var(--space-4, 1rem); }
    .front-card__title { margin-top: 0; }
    .front-card__title a { color: inherit; }
    .front-card__image { display: block; width: 100%; height: auto; }
    .front-card__image--second { margin-top: var(--space-4, 1rem); border-radius: var(--radius, 0); }
    .front-stat__value { margin: 0; font-family: var(--font-display, inherit); font-size: var(--text-h2, 1.5rem); font-weight: 700; }
    .front-stat__label { margin: 0; color: var(--color-muted, inherit); }
    .front-faq .front-item { border-bottom: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); }
    .front-item__question { display: flex; justify-content: space-between; gap: var(--space-4, 1rem); padding: var(--space-3, 0.75rem) 0; cursor: pointer; font-weight: 600; list-style: none; }
    .front-item__question::-webkit-details-marker { display: none; }
    .front-item[open] .front-item__toggle { transform: rotate(180deg); }
    .front-cta { padding: var(--space-6, 1.5rem); border-radius: var(--radius, 0); background: var(--color-accent, #0b57a4); color: var(--color-accent-ink, #ffffff); }
    .front-cta a { color: inherit; }
    .front-cta .btn-primary { background: var(--color-accent-ink, #ffffff); border-color: var(--color-accent-ink, #ffffff); color: var(--color-accent, #0b57a4); }
    .front-cta .btn-outline { border-color: var(--color-accent-ink, #ffffff); }
    .front-cta__title { margin-top: 0; }
    .front-cta__actions { display: flex; flex-wrap: wrap; gap: var(--space-2, 0.5rem); }
    .front-quote { margin: var(--space-6, 1.5rem) 0; padding-left: var(--space-4, 1rem); border-left: 3px solid var(--color-accent, #0b57a4); }
    .front-quote__text { margin: 0; font-size: var(--text-lead, 1.25rem); }
    .front-quote__by { margin-top: var(--space-2, 0.5rem); color: var(--color-muted, inherit); }
    .front-quote__by span::before { content: ", "; }
    .front-steps__list { display: grid; gap: var(--space-4, 1rem); grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); margin: 0; padding: 0; list-style: none; }
    .front-step__number { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; margin-bottom: var(--space-2, 0.5rem); border-radius: 999px; background: var(--color-accent, #0b57a4); color: var(--color-accent-ink, #ffffff); font-weight: 700; }
    .front-step__title { margin-top: 0; }
    .front-step__time { margin: 0; color: var(--color-muted, inherit); font-size: 0.875rem; }
    .front-flow__list { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2, 0.5rem); margin: 0; padding: 0; list-style: none; }
    .front-flow__step { flex: 1 1 10rem; padding: var(--space-4, 1rem); border: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); border-radius: var(--radius, 0); background: var(--color-bg, #ffffff); }
    .front-flow__arrow { display: flex; color: var(--color-muted, inherit); }
    @media (max-width: 40rem) { .front-flow__list { flex-direction: column; align-items: stretch; } .front-flow__arrow { justify-content: center; transform: rotate(90deg); } }
    .front-proof__points { display: grid; gap: var(--space-4, 1rem); margin: 0; padding: 0; list-style: none; }
    .front-proof__point { display: flex; gap: var(--space-3, 0.75rem); }
    .front-proof__title { margin-top: 0; }
    .front-timeline__list { margin: 0; padding: 0; list-style: none; border-left: 2px solid var(--color-line, rgba(0, 0, 0, 0.16)); }
    .front-timeline__step { position: relative; padding: 0 0 var(--space-4, 1rem) var(--space-4, 1rem); }
    .front-timeline__stats { display: flex; flex-wrap: wrap; gap: var(--space-6, 1.5rem); }
    .front-timeline__stats dd { margin: 0; }
    .front-form { padding: var(--space-6, 1.5rem); border: 1px solid var(--color-line, rgba(0, 0, 0, 0.12)); border-radius: var(--radius, 0); background: var(--color-surface, #f6f6f6); }
    .front-form__title { margin-top: 0; }
    .front-form__fallback { visibility: hidden; animation: front-form-fallback 0s 8s forwards; }
    .front-form__embed:has(iframe, form) .front-form__fallback { display: none; }
    @keyframes front-form-fallback { to { visibility: visible; } }
    .front-forms { max-width: var(--measure, 68ch); }
    .legal-links ul { display: flex; flex-wrap: wrap; gap: var(--space-4, 1rem); margin: var(--space-2, 0.5rem) 0 0; padding: 0; list-style: none; }
</style>
