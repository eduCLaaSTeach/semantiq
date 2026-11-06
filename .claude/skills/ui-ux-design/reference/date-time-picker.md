# Date & Time Picker UI/UX Design Guide

Rules for any date, time, or range selection. Concrete values (palette, fonts, icons, class prefixes) are approved by the CLaaS2SaaS design system; canonical source is `design-tokens.md`. Render only wired UI. Dark mode required (every token in both themes). Responsive is mobile-first.

**TL;DR**
1. Use the brand-styled **native** `<input>` (`type="date"`, `datetime-local`, or two linked `type="date"` for a range). Never build a custom calendar popover.
2. Pick mode by job: single date, date+time (`datetime-local`), range (two linked native date inputs), optional quick presets (Today / Last 7 days / This month).
3. No native range input exists: a range is two `type="date"` fields, linked so end's `min` follows the chosen start and start's `max` follows the chosen end.
4. Bounds via `min`/`max` attributes; browser enforces format. Cross-field rules (end after start) use the [forms](forms.md) error pattern.
5. Style the shell (1px border, 8px radius, primary-color focus ring); never strip or rebuild the native calendar affordance.
6. Use design tokens; never hardcode a hex or invent an off-palette shade.

---

## 1. Design tokens (source of truth)

The picker is a native input styled to the palette: flat white, 1px border, 8px radius, primary-color focus ring. The OS/browser supplies the dropdown calendar. A picker inside a form field follows the [forms guide](forms.md) for label/error placement.

```css
:root {
    /* Brand palette (approved - canonical: design-tokens.md) */
    --color-primary: #193E6B;                  /* Midnight Blue (dark: #7FADE1) - focus ring, accents */
    --color-accent: #B3A125;                   /* Green Gold */
    --color-secondary-violet: #7F3F98;         /* Cadmium Violet */
    --color-secondary-blue: #448E9D;           /* Jelly Bean Blue */
    --color-secondary-sunray: #E9AC53;         /* Sunray */
    --color-success: #5F8025;                  /* Avocado Green */
    --color-danger: #991547;                   /* Violet-Red */
    --color-background: #E8DFD0;               /* canvas (dark: #1A2E46) */
    --color-surface: #FFFFFF;                  /* dark: #253E5D */
    --color-text: #1E2E42;                     /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;               /* dark: #A3B2C5 */

    /* Date-picker tokens */
    --dp-radius: 8px;                  /* field radius (matches search/forms) */
    --dp-border: color-mix(in srgb, var(--color-text) 14%, transparent);
}
```

**Light rules (ENFORCED):** flat white field, 1px border, no inner shadow, no fill. Keep the browser's calendar-picker indicator visible (you may tint opacity, never remove it). Never signal a chosen value by color alone; the field shows the value as text.

---

## 2. Anatomy of a date picker

Every mode is one or more native inputs with a label; the browser renders the calendar, we style only the field shell.

- **Field** (`.dp-native`): native control (value text + browser calendar indicator).
- **Label** (`.dp-label`): visible label above the field (or `aria-label` when space-constrained).
- **Range** (`.dp-range`): two `.dp-native` date inputs (Start / End) with a "to" separator, linked in JS so each bounds the other.
- **Presets** (`.dp-presets`): a row of `btn btn-secondary btn-sm` controls that populate the range (see 3).

---

## 3. Native fields & modes (single, date+time, range, presets)

All four use the native control styled with `.dp-native`.

- **Single** (`single`) - `<input type="date" class="dp-native">`. One day; submitted value is ISO (`YYYY-MM-DD`). The default.
- **Date + time** (`datetime`) - `<input type="datetime-local" class="dp-native">`. Day plus time in one control (submitted `YYYY-MM-DDTHH:MM`). Use for any field capturing date and time together.
- **Range** (`range`) - two `type="date"` inputs in a `.dp-range` row (Start / End). No native range picker exists, so link them in JS: selecting Start sets End's `min`; selecting End sets Start's `max`. Emit `{ start, end }` (ISO or null).
- **Quick presets** (`.dp-presets`) - Today / Last 7 days / This month, each the shared neutral secondary button at the small size (`btn btn-secondary btn-sm`, [buttons.md](buttons.md) §4), filling the two range inputs and firing `change`. They are labeled actions that only change what is on screen, so none of them is solid and the row defines no button style of its own. Use when the picker filters a list (e.g. audit logs).

**ENFORCED (render-only-functional-UI):** never render a preset or mode field whose behaviour isn't wired. An unused preset is omitted, not shown disabled.

---

## 4. Accessibility (ENFORCED)

- Native `type="date"` / `datetime-local` are inherently accessible (keyboard entry, screen-reader support, OS calendar).
- Every field has a visible `<label>` (preferred) or `aria-label`. Label range inputs Start and End so they're distinguishable.
- Keep the focus ring visible (`2px solid var(--color-primary)`, `outline-offset: 2px`); never `outline: none` without a replacement.
- Don't trap focus or intercept keys; let the native control own its interaction.

---

## 5. States - empty, bounds, invalid

- **Empty** - no value; field shows the browser's placeholder format (e.g. `mm/dd/yyyy`).
- **Bounds** - set `min` / `max` (e.g. `min` = today for no past dates, or End's `min` = chosen Start). Browser disables out-of-range days.
- **Invalid** - browser enforces valid format. For cross-field rules the browser can't know (e.g. "disable date must be after assigned date"), validate on submit and show the [forms](forms.md) error beneath the field.

---

## 6. Responsive (ENFORCED)

Mobile-first: small screens first, then wider layouts via width and/or aspect-ratio breakpoints. On small screens fields go full-width and the range stacks into two inputs. Keep touch targets at least **44px** tall. Native pickers already adapt to touch. Never use JS for responsive layout; let CSS handle it.

---

## 7. Full CSS reference

```css
/* ===== Native field (1, 3) - mobile-first base is full-width ===== */
.dp { display: inline-flex; flex-direction: column; gap: 6px; width: 100%; }
.dp-label { font-size: var(--text-small); font-weight: 600; color: var(--color-text); }
.dp-native {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--dp-border);
    border-radius: var(--dp-radius);
    background: var(--color-surface);
    font-family: inherit;
    font-size: var(--text-body);
    color: var(--color-text);
    cursor: pointer;
}
.dp-native:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.dp-native::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; }
.dp-native::-webkit-calendar-picker-indicator:hover { opacity: 1; }

/* ===== Range: two linked inputs (3) - mobile-first base stacks ===== */
.dp-range { display: flex; align-items: flex-end; gap: var(--spacing-sm); flex-wrap: wrap; }
.dp-range-sep { padding-bottom: 10px; color: var(--color-text-muted); font-size: var(--text-small); }

/* ===== Presets (3) ===== */
.dp-presets { display: flex; flex-wrap: wrap; gap: var(--spacing-sm); margin-top: var(--spacing-sm); }
/* No .dp-preset rule: each preset IS the shared .btn .btn-secondary .btn-sm from buttons.md §10.
   A preset row lays the controls out and never defines a second button look. */

/* ===== Responsive - mobile-first (6): base above stacks; wider screens constrain width and row the range ===== */
@media (min-width: 600px) {
    .dp { max-width: 280px; }
    .dp-range .dp { max-width: 170px; }
    .dp-range { flex-wrap: nowrap; }
}
```

---

## 8. Full JS reference (behavior contract)

Vanilla, framework-free. Single and date+time need no JS. Only the range needs a linker, plus an optional preset helper. In React, do the same: bind `min`/`max` between the two inputs and set both values from a preset.

```js
/* ===== Link two native date inputs into a range (3) ===== */
function linkDateRange(startEl, endEl, { onChange } = {}) {
    const sync = () => {
        endEl.min = startEl.value || '';      // end can't precede start
        startEl.max = endEl.value || '';      // start can't follow end
        if (onChange) onChange({ start: startEl.value || null, end: endEl.value || null });
    };
    startEl.addEventListener('change', sync);
    endEl.addEventListener('change', sync);
    sync();
}

/* ===== Fill a linked range from a preset (3) ===== */
function applyDatePreset(startEl, endEl, preset) {
    const iso = (d) => { const p = (n) => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; };
    const t = new Date();
    let start = t, end = t;
    if (preset === 'last7') start = new Date(t.getTime() - 6 * 86400000);
    else if (preset === 'month') { start = new Date(t.getFullYear(), t.getMonth(), 1); end = new Date(t.getFullYear(), t.getMonth() + 1, 0); }
    startEl.value = iso(start);
    endEl.value = iso(end);
    endEl.dispatchEvent(new Event('change'));  // re-runs linkDateRange.sync -> onChange
}
```

---

## 9. Do's and Don'ts

**Do**
- Use the native control for every date/time field.
- Build a range from two linked `type="date"` inputs (end.min = start, start.max = end).
- Style the shell (border, radius, focus ring) and keep the native calendar indicator.
- Add quick presets when the picker filters a list.
- Express bounds with `min`/`max`; show a forms error for cross-field rules.

**Don't**
- Build a custom calendar popover.
- Expect a native range input; use two linked inputs.
- Strip or hide the native calendar affordance.
- Signal the chosen value by color alone.
- Render an unused preset or a dead mode field (omit it).
- Hardcode a hex or invent an off-palette shade.

---

## 10. Rules for the AI assistant

1. **Native first.** Single = `type="date"`, date+time = `datetime-local`. Never hand-roll a calendar unless a hard requirement forces it (then say so).
2. **Range = two linked date inputs** via `linkDateRange` (8); no native range picker.
3. **Presets only for list-filtering**, and only wired ones.
4. **Bounds via `min`/`max`; cross-field rules via a forms error** on submit; don't parse typed text.
5. **Style the shell, keep native behaviour** - visible label, primary-color focus ring, intact calendar indicator.
6. **Tokens only**, mobile-first; re-check accessibility (4) before finalizing.

---

## 11. Quick decision guide

```
One calendar day (form value)?           -> type="date"
Day + a time of day?                     -> type="datetime-local"
A start-end span?                        -> two linked type="date" inputs
Filtering a list by date?                -> range + quick presets
No-past-dates / bounded?                 -> min / max attributes
Cross-field rule (end after start)?      -> validate on submit + forms error
```

When unsure, reach for the plain native input and add time / range / presets only when the job needs them.
