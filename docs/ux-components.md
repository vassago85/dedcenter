# DeadCenter UX Components

Quick reference for the Blade primitives that every screen in this app should be built from. If the pattern you need isn't here, stop and either (a) extend an existing primitive or (b) add a new one to `resources/views/components/` — do not inline a one-off.

All of these are governed by the binding [DeadCenter UX Standard](../.cursor/rules/deadcenter-ux-standard.mdc). Read that first.

## Typography tokens

Defined in `resources/css/app.css` under `@theme`. Use these utilities instead of ad-hoc `text-*` combinations.

| Utility | Use for |
|---|---|
| `text-page-title` | H1 on every internal page |
| `text-section-title` | Sub-section headings within a page |
| `text-card-title` | Panel / card titles |
| `text-eyebrow` | Small uppercase kicker above titles |
| `text-label` | Form labels, small caps chips, stat-card labels |
| `text-meta` | Secondary metadata, table support text |
| `text-body` | Default body copy |
| `text-body-strong` | Emphasised body copy |
| `text-table-support` | Explicit support text inside tables |

## Spacing + container

- `dc-page` — standard 80rem page container. Variants: `dc-page-narrow` (56rem), `dc-page-wide` (96rem).
- `dc-section-stack` — applies `--spacing-section` between direct children.
- `dc-block-stack` — applies `--spacing-block` between direct children.
- `.dc-skeleton` — placeholder block with shimmer animation.

## Page-level primitives

### `<x-page-shell>`
Standard page wrapper. Use once per page.
```blade
<x-page-shell width="default|narrow|wide" :stack="true">
    <x-app-page-header ... />
    <section>...</section>
    <section>...</section>
</x-page-shell>
```

### `<x-app-page-header>`
The H1 block at the top of every internal page.
```blade
<x-app-page-header
    eyebrow="Organization · Match"
    title="Winter Classic"
    subtitle="Nine-stage NRL22, 48 registered"
    :crumbs="[['label' => 'Matches', 'href' => route('org.matches.index', $org)], ['label' => 'Winter Classic']]">
    <x-slot:status>
        <x-status-badge tone="emerald" label="Registration Open" />
    </x-slot:status>
    <x-slot:actions>
        <flux:button variant="primary" icon="users">View registrations</flux:button>
    </x-slot:actions>
</x-app-page-header>
```

### `<x-section-header>`
Break a page into scannable sub-sections without promoting each to a panel.
```blade
<x-section-header title="Recent results" description="Last 5 matches">
    <x-slot:actions>
        <a href="{{ route('events') }}" class="text-meta text-accent">View all →</a>
    </x-slot:actions>
</x-section-header>
```

## Content primitives

### `<x-panel>`
Generic card/panel. Use sparingly — only when grouped content genuinely benefits from visual separation.
```blade
<x-panel title="Upcoming" subtitle="Next 14 days" :padding="false">
    <x-slot:actions><x-action-menu>...</x-action-menu></x-slot:actions>
    ...table...
    <x-slot:footer>...</x-slot:footer>
</x-panel>
```
Tones: `default`, `muted`, `accent`, `warning`.

### `<x-stat-card>`
Single stat tile. Only use when the number drives a decision.
```blade
<x-stat-card
    label="Pending approvals"
    :value="$pendingCount"
    color="amber"
    helper="3 this week"
    trend="up" trendValue="+12%"
    :href="route('admin.organizations')" />
```

### `<x-status-badge>`
Token-mapped status pill. Feed it the enum's `color()` output directly.
```blade
<x-status-badge :tone="$match->status->color()" :label="$match->status->label()" />
<x-status-badge tone="emerald" label="Live" icon="activity" pulse />
```

### `<x-empty-state>`
Every list/index must have one when empty.
```blade
<x-empty-state
    title="No matches yet"
    description="Create a match to start accepting registrations."
    size="md">
    <x-slot:icon><x-icon name="crosshair" class="h-5 w-5" /></x-slot:icon>
    <x-slot:actions>
        <flux:button variant="primary" href="{{ route('org.matches.create', $org) }}">Create match</flux:button>
    </x-slot:actions>
</x-empty-state>
```

### `<x-skeleton>`
Loading placeholders.
```blade
<x-skeleton variant="line" width="w-48" />
<x-skeleton variant="block" height="h-32" />
<x-skeleton variant="card" :rows="4" />
<tbody>
    <x-skeleton variant="row" :columns="5" />
</tbody>
```

## List / table primitives

### `<x-data-table>`
Standard platform data table. Never inline `<table>` without this.
```blade
<x-data-table :count="$matches->count()" :dense="false">
    <x-slot:toolbar>
        <x-filter-bar placeholder="Search matches…" wire:model.live.debounce.250ms="search">
            <x-slot:filters>
                <flux:select wire:model.live="status">...</flux:select>
            </x-slot:filters>
            <x-slot:actions>
                <flux:button icon="download">Export</flux:button>
            </x-slot:actions>
        </x-filter-bar>
    </x-slot:toolbar>

    <x-slot:columns>
        <th>Match</th>
        <th class="hidden md:table-cell">Date</th>
        <th>Status</th>
        <th class="w-0"></th>
    </x-slot:columns>

    <x-slot:rows>
        @foreach($matches as $match)
            <tr>
                <td class="font-semibold text-primary">{{ $match->name }}</td>
                <td class="hidden md:table-cell">{{ $match->date?->format('d M Y') }}</td>
                <td><x-status-badge :tone="$match->status->color()" :label="$match->status->label()" /></td>
                <td class="text-right">
                    <x-action-menu>
                        <x-action-menu.item :href="route('org.matches.hub', [$org, $match])" icon="eye">Open match hub</x-action-menu.item>
                        <x-action-menu.item :href="route('org.matches.edit', [$org, $match])" icon="pencil">Edit</x-action-menu.item>
                    </x-action-menu>
                </td>
            </tr>
        @endforeach
    </x-slot:rows>

    <x-slot:empty>
        <x-empty-state title="No matches" description="Create one to get started.">
            ...
        </x-empty-state>
    </x-slot:empty>

    <x-slot:footer>{{ $matches->links() }}</x-slot:footer>
</x-data-table>
```

### `<x-filter-bar>`
Search + filter chips + trailing actions, used in `x-data-table`'s `toolbar` slot.

### `<x-action-menu>` + `.item` / `.divider` / `.header`
Kebab overflow menu for secondary row/page actions.
```blade
<x-action-menu align="right" icon="more-horizontal">
    <x-action-menu.header>Actions</x-action-menu.header>
    <x-action-menu.item :href="route('…')" icon="pencil">Edit</x-action-menu.item>
    <x-action-menu.item :href="route('…')" icon="download">Export standings</x-action-menu.item>
    <x-action-menu.divider />
    <x-action-menu.item tone="danger" icon="trash-2" method="DELETE" :action="route('…')">
        Delete
    </x-action-menu.item>
</x-action-menu>
```

## Shell primitives

### `<x-app-context-bar>`
Mode/context banner under the top bar. Already wired in the authenticated layout (`components/layouts/app.blade.php`) — you generally don't call it directly.

## Reports — dark token system

All exportable reports (PDFs), the transactional email, and the public HTML
`/reports/royal-flush/...` page share a single dark palette so output feels like
one product.

### Sources of truth

- `resources/views/exports/partials/pdf-styles-dark.blade.php` — canonical dark
  token block for every PDF. Uses hardcoded hex values because DomPDF (the
  fallback renderer) doesn't resolve CSS custom properties. Gotenberg
  (Chromium) is the primary renderer; DomPDF is only the fallback.
- `resources/views/exports/partials/pdf-header.blade.php` /
  `pdf-footer.blade.php` — shared header + footer, included into every PDF.
- `resources/css/app.css` (`--lp-*` custom properties) — native dark palette
  used by the public Royal Flush HTML report.
- `resources/views/emails/shooter-match-report.blade.php` — email client uses
  inline hex values matching the app-native navy + red; no CSS variables
  (email clients strip them).

### Palette anchors (PDF / email)

| Token | Hex | Use |
|---|---|---|
| `page-bg` | `#071327` | Body, page canvas, header strip |
| `surface-1` | `#0c1a33` | Primary card/table surface |
| `surface-2` | `#1d2d4a` | Elevated rows, striped alternates |
| `ink-primary` | `#f8fafc` | Primary text, headings |
| `ink-secondary` | `#cbd5e1` | Body copy |
| `ink-muted` | `#94a3b8` | Labels, meta |
| `accent` (UI) | `#ff2b2b` | Interactive accents (dashboards, emails) |
| `accent` (PDF) | `#e10600` | PDF header rule, brand lockup |
| `positive` | `#22c55e` / `#86efac` | Hits |
| `negative` | `#ef4444` / `#fca5a5` | Misses |
| `gold` | `#fbbf24` | 1st place |
| `silver` | `#cbd5e1` | 2nd place |
| `bronze` | `#fb923c` | 3rd place |

### Rules

1. **Do not hardcode other colors in report templates.** If you need a new
   token, add it to `pdf-styles-dark.blade.php` and this table.
2. **Gradients are banned in PDFs** — DomPDF ignores them. Use flat `rgba()`
   tints over `surface-1` for podium / highlight rows.
3. **SVGs inside PDFs** must use hex `fill` / `stroke` (no `currentColor`,
   no `var(...)`). Spade/club in `pdf-header` must stay `#f8fafc` for
   contrast on `#071327`.
4. **Print fallbacks** on HTML reports (`@media print`) keep the dark theme
   so "Save as PDF" from the browser matches the server-rendered PDF.
5. **The matchbook editor + its PDF output are opt-out** of this system —
   they use their own printable-page typography.

## When you need something new

1. Look at the standard §14 first — is there already a primitive for this?
2. If no, can an existing primitive be extended with another prop/slot?
3. Only if neither works, add a new component to `resources/views/components/` with a docblock header matching the style above, and add it to this file.

The point is consistency. A user who learns the shape of one DeadCenter page should instantly read every other page.
