# Shopmade design system

version: 1
tailwind: 4
reference: resources/design/component-sheet.html (visual only; this file wins)
scope: the app shell (`layouts.app`, `layouts.portal`, plugins). Company fronts under `fronts/<company>/` have their own tokens (`theme/tokens.css`, see `docs/front/README.md`); marketing pages keep `layouts.marketing`.
copy: `resources/DESIGN_SYSTEM.md` is the machine-read copy of this file; keep them identical.

Warm ground, ink, one near-mono red. Zero radius everywhere. Rules divide, shadows float. Everything sits flush left, including labels inside wide buttons. Light theme only.

## The contract

1. **Tokens, never hexes.** `bg-bg`, `text-ink`, `border-line`, `bg-ok-100`. No `#hex`, no `bg-red-500`, no Tailwind default palette outside `theme.css`.
2. **Zero radius.** Every radius token is 0. Never add `rounded-*`.
3. **Component first.** Compose from `<x-*>` components; utilities only when nothing fits. Never fork a component; add a variant.
4. **One status map.** Any record state is `<x-status :state="…" />`. The map lives in `status.blade.php` and nowhere else; packages map their states onto it with `Hooks::status()`.
5. **Rules divide, shadows float.** 2px `border-line` between major regions. `shadow-*` only on dropdowns, modals, drawers, toasts. Cards never float.

## Tokens

### Roles

| Class | Value | Use |
|---|---|---|
| `bg-bg` | #f3f2f2 | Page ground |
| `bg-surface` | #eae9e9 | Cards, inputs, table bodies |
| `text-ink` | #201e1d | All text |
| `border-line` | rgba(32,30,29,0.4) | The 2px structural rule |
| `border-line-soft` | rgba(32,30,29,0.16) | 1px table row rules only |
| `bg-accent` / `text-accent` | #ec3013 | Primary action, destructive, overdue |

Muted ink is opacity on `text-ink`: `text-ink/70` (labels), `text-ink/55` (hints, counts), `text-ink/45` (nav group headers).

### Ramps

`accent-100…900`, `neutral-100…900`, and `ok` / `warn` / `info` at steps 100, 200, 500, 600, 700, 800. Use as `bg-accent-100`, `text-ok-800`, `border-warn-600`, etc.

- accent: fff2ef · ffe0d9 · ffc4b8 · ff9783 · ff563c · dd2b0f · ae1800 · 7c1405 · 4d170e
- neutral: f8f4f4 · eae7e7 · d7d3d3 · bab6b6 · 9b9797 · 7d7979 · 605d5d · 444141 · 2d2b2b
- ok (hue 150), warn (hue 70), info (hue 252): oklch L/C per step — 100 = 0.966/0.017 · 200 = 0.923/0.043 · 500 = 0.685/0.14 · 600 = 0.585/0.14 · 700 = 0.487/0.13 · 800 = 0.39/0.11

Light steps tint fills and hovers. Dark steps (700–800) are text on tinted fills. Body-size red text is `text-accent-700`, never `text-accent`. Links: `text-accent-700 hover:text-accent`.

### Type

Font: `font-sans` (Archivo 400/600/800). `font-narrow` (Archivo Narrow) is loaded but has no role in v1; do not use it. The wordmark uses `font-wordmark` (also Archivo) so it stays Archivo on marketing pages that keep a different body face.

| Recipe | Classes |
|---|---|
| Page title | `text-title` (32px / 800 / 1.12 / −0.015em) |
| Card title | `text-card-title` (17px / 800) |
| Stat value | `text-stat tabular-nums` (32px / 800) |
| Body | `text-body` (15px / 400) |
| Control | `text-control` (14px / 400) — buttons, inputs, table cells, nav |
| Label | `text-label text-ink/70` (12px / 400) |
| Micro caps | `text-micro` (11px / 600 / 0.08em / uppercase) |
| Kicker | `text-kicker text-accent` (10px / 600 / 0.14em / uppercase) |

`text-[32px]` and other arbitrary values are forbidden except on `<x-wordmark>`. Add `tabular-nums` to every money and count column.

### Space, rules, elevation

- 4px base. Steps in use: `2 3 4 5 6 8` (8 12 16 20 24 32px).
- `border-2 border-line` on every major boundary. `border-b border-line-soft` on table rows only.
- `shadow-sm` `shadow-md` `shadow-lg`: dropdown md, toast md, modal lg. Nothing else.
- Focus: `focus-visible:outline-2 outline-accent outline-offset-2` is global. Do not restyle.

### Density

`.ds-field-mode` on `<body>` (set by the layout when `$audience === 'field'`) raises every control to min-height 44px and body type to 16px. Never a per-component prop.

## Components

All in `resources/views/components/`. Every root accepts `{{ $attributes }}`.

### x-button
Actions. Props: `variant` primary|secondary|ghost|danger (primary) · `size` default|sm|icon (default) · `type` (button) · `href` renders `<a>`.
Primary and danger are an accent fill; secondary is a 2px line border; ghost has no border and accent-700 text. Labels flush left. `icon` is square and needs `aria-label`.
```blade
<x-button href="{{ route('jobs.create') }}"><i data-lucide="plus" class="size-4"></i>New job</x-button>
<x-button variant="secondary" size="sm">Filter</x-button>
<x-button variant="danger" type="submit">Delete job</x-button>
```

### x-wordmark
The product wordmark: SHOP over MADE with a rule between, Archivo 800, uppercase. Props: `size` sm|md|lg (sm) · `inverse` (false). Never redraw it; never add a symbol. SVGs for email and PDF live in `public/brand/`.
```blade
<x-wordmark />            {{-- sidebar --}}
<x-wordmark size="md" />  {{-- login --}}
<x-wordmark inverse />    {{-- on ink or accent grounds --}}
```

### x-status
The only status chip. Props: `state` (required). Micro caps, `px-2 py-1`. Unknown state renders as draft and logs a warning.
```blade
<x-status :state="$plugin->status->value" />
```

### x-field
Wraps any control. Props: `label` · `for` · `error` · `hint`. Label 12px/70% above, error 12px accent-700 below, hint 12px/55%.
```blade
<x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
  <x-select id="customer_id" name="customer_id" :options="$customers->pluck('name','id')" placeholder="Choose…" />
</x-field>
```

### x-input
Props: `type` (text) · `error` (bool). Surface fill, 2px line border, `h-10`, 14px. Error: accent border.
```blade
<x-input name="email" type="email" :error="$errors->has('email')" />
```

### x-select
Native select skinned like input. Props: `options` (value ⇒ label) · `selected` · `placeholder` · `error`.
```blade
<x-select name="assigned_to" :options="$staff->pluck('name','id')" :selected="old('assigned_to')" placeholder="Unassigned" />
```

### x-textarea
Props: `rows` (3) · `error`.
```blade
<x-textarea name="notes" rows="4">{{ old('notes') }}</x-textarea>
```

### x-checkbox
Props: `label` · `checked`. 20px square, 2px line border, accent fill when checked, 44px hit area in field mode.
```blade
<x-checkbox name="notify" label="Send confirmation to customer" :checked="old('notify')" />
```

### x-page-header
First element in every `@section('content')`. Props: `title` · `kicker` · slot `actions`. 2px rule below.
```blade
<x-page-header title="Jobs" kicker="Work">
  <x-slot:actions><x-button href="{{ route('jobs.create') }}">New job</x-button></x-slot:actions>
</x-page-header>
```

### x-stat
One cell of a stat grid; parent wraps cells in `grid gap-0.5 bg-line border-2 border-line`. Props: `label` · `value` · `delta` · `direction` up|down|flat · `tone` ok|bad|neutral. Direction picks the arrow; tone picks the colour (ok-700 or accent-700).
```blade
<x-stat label="Open jobs" :value="$openJobs" delta="+3 this week" direction="up" tone="ok" />
```

### x-card
Props: `title` · `kicker` · slot `actions` · default slot. Surface, 2px line border, header row with 2px rule under it. No shadow.
```blade
<x-card title="Details">
  <x-slot:actions><x-button variant="secondary" size="sm">Edit</x-button></x-slot:actions>
  …
</x-card>
```

### x-empty
Centered inside a card or table body. Props: `title` · `body` · slot `action`.
```blade
<x-empty title="No quotes yet" body="Quotes for this job will appear here once you create one.">
  <x-slot:action><x-button variant="secondary">Create quote</x-button></x-slot:action>
</x-empty>
```

### x-table
Props: `columns` array of `['label' => , 'align' => 'left'|'right']` · default slot of `<tr>`s · slot `empty`. Header micro caps over a 2px rule; rows 1px line-soft; right-aligned columns get tabular-nums; cells `px-4 py-3`. Renders `empty` when the default slot is blank.
```blade
<x-table :columns="[['label'=>'Job'],['label'=>'Customer'],['label'=>'Status'],['label'=>'Total','align'=>'right']]">
  @foreach($jobs as $job)<tr>…</tr>@endforeach
  <x-slot:empty><x-empty title="No jobs match" body="Clear the filter or the search." /></x-slot:empty>
</x-table>
```

### x-toolbar
Sits above a table. Props: `title` · `count` · slots `left`, `right`. Pagination text style: `1–5 of 128 · Prev · Next`.
```blade
<x-toolbar title="Jobs" :count="$jobs->total()">
  <x-slot:right><x-input type="search" name="q" placeholder="Search customer" /><x-button>New job</x-button></x-slot:right>
</x-toolbar>
```

### x-tabs
Underline tabs, links with `?tab=`, no JS. Props: `tabs` (slug ⇒ label) · `active`. Active: 2px accent bottom rule, 800 weight.
```blade
<x-tabs :tabs="['details'=>'Details','quotes'=>'Quotes','history'=>'History']" :active="request('tab','details')" />
```

### x-dropdown
Alpine menu. Props: `label` or `icon` (default `ellipsis`, secondary icon button). Default slot of `<a>` / `<button>` items; add `class="danger"` for accent-700. Closes on outside click and Escape.
```blade
<x-dropdown>
  <a href="{{ route('jobs.edit',$job) }}">Edit job</a>
  <button type="button" @click="$dispatch('open-modal','delete-job')" class="danger">Delete job</button>
</x-dropdown>
```

### x-modal
Props: `name` (required) · `title` · default slot · slot `footer`. Opens on `$dispatch('open-modal', name)`, closes on `close-modal`, Escape, backdrop. Danger action last in the footer.
```blade
<x-modal name="delete-job" title="Delete job #{{ $job->id }}?">
  This cannot be undone.
  <x-slot:footer>
    <x-button variant="secondary" @click="$dispatch('close-modal','delete-job')">Cancel</x-button>
    <form method="post" action="{{ route('jobs.destroy',$job) }}">@csrf @method('delete')<x-button variant="danger" type="submit">Delete job</x-button></form>
  </x-slot:footer>
</x-modal>
```

### x-toast-host
No props. Mounted once by the layout. Listens for `$dispatch('toast', { tone: 'ok'|'warn'|'info'|'bad', message })`. Bottom-right (bottom-center under md), 2px left edge in the tone colour, surface, shadow-md, auto-dismiss 5s, ✕ to close. `session('status')` becomes a toast automatically.
```blade
<x-button @click="$dispatch('toast', { tone: 'ok', message: 'Job saved.' })">Save</x-button>
```

### x-alert
Inline, never floating. Props: `tone` ok|warn|info|bad · `title` · default slot. tone-100 background, 2px left edge tone-600 (accent for bad), title 15/800 tone-800.
```blade
<x-alert tone="warn" title="Crew unassigned">This job is scheduled for tomorrow with nobody assigned.</x-alert>
```

### Status vocabulary

Core states, grouped by color: `draft`; `info`, `open`, `scheduled` (info); `pending`, `running` (warn); `active`, `complete`, `passed`, `set`, `approved` (ok); `paid` (ok-600 on bg); `failed`, `missing` (accent-100); `overdue` (accent on bg); `cancelled`, `closed`, `disabled`, `removed`, `expired` (neutral-300).

A package registers its own states in its service provider: `$hooks->status('on_site', like: 'running', label: 'On site')`.

## Composition rules

A feature screen:

```blade
@extends('layouts.app')
@section('content')
  <x-page-header title="…" kicker="…">…</x-page-header>
  {{-- then cards, stat grids, toolbar + table --}}
@endsection
```

- Forms: `<x-field>` around every control; controls are `<x-input>`, `<x-select>`, `<x-textarea>`, `<x-checkbox>`.
- Every list has an `<x-empty>` in the table's `empty` slot.
- Every destructive action goes through `<x-modal>`, danger button last.
- Feedback goes through `$dispatch('toast', …)` or `->with('status', …)`.
- Status is always `<x-status>`.
- Row actions are an `<x-dropdown>` in the last column.
- Icons are `<i data-lucide="name" class="size-4"></i>`. No inline SVG, no emoji.

## Do / Don't

Do: let the grid show · keep everything flush left · `ds-field-mode` for crew-facing pages · `tabular-nums` on money and counts · add a variant, not a component.

Don't: round a corner · use red for anything but primary, destructive, overdue · shadow a card · show staff-only states to clients · add a dependency for something Alpine does in five lines.

## How to extend

- New token → `theme.css` `@theme`
- New package state → `Hooks::status()` in the package; a new color group → `status.blade.php` `$map`
- New variant → the component's variant match
- New component → `components/`, only once a pattern appears on three screens
- New density → `theme.css` `@layer components`

**Feature modules may use these components and may not edit them, and may not create their own components.**

## Deferred to v2

combobox · drawer · dark mode · customer portal
