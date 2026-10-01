# Cards

One enforced base shell plus principled variants by use case (content, KPI, entity record, form section), with an interactive modifier. Use for any card, dashboard tile, or record summary. Values (palette, fonts, icons, tokens) are approved constants; canonical source is `design-tokens.md` (brand assets in the skill's `assets/` pack). Responsive is mobile-first; dark mode required (every token in both themes).

## Contents

- Anatomy (base - ENFORCED)
- Tokens & values
- Variants (PRINCIPLED): Content, KPI / Metric, Entity, Form section, Interactive (modifier)
- Layout & responsive
- States
- Do / Don't

---

## Anatomy (base - ENFORCED)

Every card is the same shell filling a subset of slots: **header** (title, plus any actions), **body**, optional **footer** (actions). A card's actions are labeled buttons like any other action cluster - the icon plus its word ([buttons.md](buttons.md) §4.2).

```html
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Title</h3>
    <div class="card-icon"><!-- icon from the bundled icon registry - see assets.md --></div>
  </div>
  <div class="card-content">Content</div>
  <div class="card-footer"><!-- optional actions --></div>
</div>
```

```css
.card {
  background: var(--color-surface);     /* #FFFFFF light / #253E5D dark */
  border: 1px solid var(--card-border); /* flat at rest - no resting shadow */
  border-radius: var(--radius-card);    /* 12px */
  padding: var(--spacing-lg);           /* 24px */
  display: flex; flex-direction: column; gap: var(--spacing-md);
}
.card-header { display: flex; align-items: center; justify-content: space-between; gap: var(--spacing-sm); }
.card-title { font-family: var(--font-heading); font-size: var(--text-h3); font-weight: 600; margin: 0; } /* h3 = 18px */
.card-icon { color: var(--color-text-muted); display: flex; }
.card-content { font-size: var(--text-body); color: var(--color-text); }
.card-footer { display: flex; gap: var(--spacing-sm); justify-content: flex-end; }
```

**ENFORCED:** the fixed card surface (white `#FFFFFF` on light canvas, raised navy `#253E5D` on dark), a flat resting card (1px `--card-border`, 12px radius, no resting shadow), the `card` -> `card-header`/`card-content` structure, and the `h3` card title (matches `typography.md`: card title -> `--text-h3`). Padding and gap use the listed spacing tokens.

---

## Tokens & values

Cards reference tokens - never hardcode. (See `../SKILL.md` for the overview; authoritative values in `design-tokens.md`.)

| Property | Token | Value |
|----------|-------|-------|
| Surface | `--color-surface` | `#FFFFFF` light / `#253E5D` dark |
| Border (resting) | `--card-border` | `#E8E2D8` light / `rgba(255,255,255,.10)` dark |
| Radius | `--radius-card` | `12px` |
| Elevation (resting) | `--shadow-sm` | `none` - resting cards are flat |
| Elevation (raised/hover) | `--shadow-md` | `0 2px 8px rgba(25,40,65,.08)` light / `0 3px 10px rgba(0,0,0,.3)` dark |
| Padding (default) | `--spacing-lg` | `24px` |
| Internal gap | `--spacing-md` | `16px` |
| Title | `--text-h3` / Montserrat 600 | `18px` |

---

## Variants (PRINCIPLED)

Choose by use case. Each variant = base shell + the overrides below.

### Content card

The default. Title + body, optional footer actions. No overrides beyond the base.

### KPI / Metric card

Label, large value, trend delta. Compact padding.

```html
<div class="card card-kpi">
  <div class="kpi-label">Active Users</div>
  <div class="kpi-value">12,480</div>
  <div class="kpi-trend kpi-trend-up">▲ 8.2%</div>
  <!-- optional: <div class="kpi-progress">...</div> -->
</div>
```

```css
.card-kpi { gap: var(--spacing-xs); padding: var(--spacing-md) var(--spacing-lg); }
.kpi-label { font-size: var(--text-small); color: var(--color-text-muted); }
.kpi-value { font-family: var(--font-heading); font-size: var(--text-h1); font-weight: 700; color: var(--color-primary); } /* 24px; Midnight Blue #193E6B light / #7FADE1 dark */
.kpi-trend { font-size: var(--text-small); font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.kpi-trend-up   { color: var(--badge-success-fg); }  /* readable Avocado Green ramp - never raw #5F8025 as text */
.kpi-trend-down { color: var(--badge-danger-fg); }   /* readable Violet-Red ramp - never raw #991547 as text */
```

**Trend color rule (ENFORCED): direction-based.** Up = success (Avocado Green `#5F8025`), down = danger (Violet-Red `#991547`); the arrow shows direction, the color matches it. As text on a card the color goes through the theme-aware readable tokens (`--badge-success-fg` / `--badge-danger-fg`), never the raw semantic hex on a surface. *Exception (PRINCIPLED):* for "lower-is-better" metrics (error rate, cost, latency) the colors invert - flag this as a deviation and state the metric's polarity.

The value uses `--text-h1` (24px), the top of the tuned scale; KPI numbers stay within the scale, no larger display size.

### Entity card

Represents a record (employee, course, contact): media + text + actions in a row.

```html
<div class="card card-entity">
  <div class="entity-media"><!-- 40px avatar / thumbnail --></div>
  <div class="entity-body">
    <div class="entity-title">Jane Cooper</div>
    <div class="entity-subtitle">Security Admin</div>
    <div class="entity-meta">Last active 2h ago</div>
  </div>
  <div class="entity-actions">
    <!-- A record's action cluster, so every action carries its word (buttons.md §4.2) -->
    <a class="btn btn-secondary btn-sm" href="/people/jane-cooper">
      <svg class="ic" aria-hidden="true"><use href="#i-eye" /></svg> View
    </a>
    <button type="button" class="btn btn-secondary btn-sm btn-text-danger" aria-label="Remove Jane Cooper">
      <svg class="ic" aria-hidden="true"><use href="#i-trash" /></svg> Remove
    </button>
  </div>
</div>
```

```css
.card-entity { flex-direction: row; align-items: center; gap: var(--spacing-md); padding: var(--spacing-md); }
.entity-media { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; overflow: hidden; }
.entity-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.entity-title { font-family: var(--font-heading); font-size: var(--text-h4); font-weight: 600; } /* 16px */
.entity-subtitle { font-size: var(--text-small); color: var(--color-text-muted); }
.entity-meta { font-size: var(--text-xs); color: var(--color-text-muted); }
.entity-actions { display: flex; gap: var(--spacing-xs); flex-shrink: 0; }
```

### Form section card

Groups form fields. **Reduced padding** (flat 1px `--card-border` border and no resting shadow are already the card default).

```css
.card-form {
  padding: var(--spacing-md);
}
```

### Interactive (modifier)

A modifier, not a separate type - apply to Content or Entity cards to make the whole card clickable.

```css
.card-interactive { cursor: pointer; transition: box-shadow .2s ease, transform .2s ease; }
.card-interactive:hover { border-color: var(--card-border-strong); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.card-interactive:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
```

When the whole card is a link, the card is the interactive element; don't nest separately focusable controls that duplicate the card's action.

---

## Layout & responsive

Cards (especially KPI tiles) sit in a mobile-first responsive grid: single column on small screens, fanning out as width allows. `auto-fit` + `minmax` gives this for free, with an explicit single-column fallback.

```css
.card-grid {
  display: grid;
  grid-template-columns: 1fr;                /* mobile-first: single column */
  gap: var(--spacing-md);
}
@media (min-width: 600px) {
  .card-grid {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--spacing-lg);
  }
}
```

Use width and/or aspect-ratio breakpoints as needed (e.g. `@media (max-aspect-ratio: 1/1)` to force one column). On small screens cards stack to one column and the shell's sidebar goes off-canvas. Keep any interactive card a comfortable touch target (at least 44px tall).

---

## States

Referenced from other files, not redefined here:

- **Loading** -> skeleton card from `empty-and-loading-state.md`.
- **Empty** (an empty list rendered as a card) -> `empty-and-loading-state.md`.
- **Hover / focus** -> only on the Interactive modifier (above). Static cards have no hover.

---

## Do / Don't

**Do**
- Build every card from the base shell + slots; reference tokens for surface/border/radius/padding.
- Use `h3` (`--text-h3`) for card titles; KPI value as `--text-h1` bold.
- Put cards in `.card-grid`; stack via aspect-ratio.
- Apply the Interactive modifier only when the whole card is actionable.

**Don't**
- Hardcode card colors, radius, padding, or shadow (use tokens).
- Invent new card "types" - compose slots; deviate only with justification.
- Color KPI trends by sentiment when direction-based is the rule (invert only for documented lower-is-better metrics).
- Give a resting card a shadow, or add hover elevation to non-interactive cards - soft elevation is reserved for the Interactive hover and overlays.
