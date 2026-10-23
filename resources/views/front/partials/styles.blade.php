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
</style>
