# Typography

Font families, weights, and the size scale. Use when setting any font family, weight, or size. Families, weights, and sizes are approved constants of the CLaaS2SaaS design system; canonical source is `design-tokens.md`.

## Contents

- Font families
- Font weights (ENFORCED)
- Type scale (ENFORCED)
- Element mapping
- Open decisions (define per project)
- Do / Don't

---

## Font families

Two approved families, paired by role: **Montserrat** for headings, **Source Sans 3** for body. Keep the token names; values never vary per app.

```css
:root {
    --font-heading: 'Montserrat', system-ui, -apple-system, 'Segoe UI', sans-serif;
    --font-body:    'Source Sans 3', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
```

Load both from Google Fonts with exactly this import (or self-host the same families):

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
```

---

## Font weights (ENFORCED)

Weights match the approved import exactly: **Montserrat 600/700/800** and **Source Sans 3 400/500/600/700**. Weight 300 is not loaded, so nothing may use it.

```css
:root {
    --font-weight-normal:    400;
    --font-weight-medium:    500;
    --font-weight-semibold:  600;
    --font-weight-bold:      700;
    --font-weight-extrabold: 800;
}
```

| Token | Value | Name | Typical use |
|-------|-------|------|-------------|
| `--font-weight-normal` | 400 | Normal | Body copy (body typeface) |
| `--font-weight-medium` | 500 | Medium | Emphasised body, labels (body typeface) |
| `--font-weight-semibold` | 600 | Semibold | Headings (heading typeface); strong body emphasis such as table headers |
| `--font-weight-bold` | 700 | Bold | Headings / page titles (heading typeface) |
| `--font-weight-extrabold` | 800 | Extrabold | Optional display emphasis only (heading typeface) |

Heading typeface uses **600 / 700**, with **800 optional for display emphasis only**; body typeface uses **400 / 500**, with 600/700 reserved for strong emphasis (e.g. table headers). Never use a weight the import does not load - there is no 300.

---

## Type scale (ENFORCED)

Tuned for functional business-app density. Use these tokens - never hardcode sizes outside the scale. Approved size steps: **10 / 11 / 12 / 13 / 14 / 15 / 16 / 18 / 20 / 24 px**; no other size exists. Base: `1rem = 16px`. Body text is **13px with line-height 1.5** (approved); headings take a tighter, consistent line-height.

```css
:root {
    --text-display: 1.5rem;    /* 24px - Display (hero numbers, status headlines) */
    --text-h1:      1.25rem;   /* 20px - Page titles */
    --text-h2:      1.125rem;  /* 18px - Section headers */
    --text-h3:      1rem;      /* 16px - Card titles */
    --text-h4:      0.9375rem; /* 15px - Sub-section headers */
    --text-lead:    0.875rem;  /* 14px - Lead / emphasised body */
    --text-body:    0.8125rem; /* 13px - Body content (line-height 1.5) */
    --text-small:   0.75rem;   /* 12px - Labels, captions */
    --text-xs:      0.6875rem; /* 11px - Meta data */
    --text-micro:   0.625rem;  /* 10px - Uppercase micro-labels, table headers */
}
```

| Token | rem | px | Role | Family | Weight |
|-------|-----|----|------|--------|--------|
| `--text-display` | 1.5rem | 24px | Display (hero numbers, status headlines) | `--font-heading` | 700 (800 optional) |
| `--text-h1` | 1.25rem | 20px | Page titles | `--font-heading` | 700 |
| `--text-h2` | 1.125rem | 18px | Section headers | `--font-heading` | 700 |
| `--text-h3` | 1rem | 16px | Card titles | `--font-heading` | 600 |
| `--text-h4` | 0.9375rem | 15px | Sub-section headers | `--font-heading` | 600 |
| `--text-lead` | 0.875rem | 14px | Lead / emphasised body | `--font-body` | 400/500 |
| `--text-body` | 0.8125rem | 13px | Body content (line-height 1.5) | `--font-body` | 400 |
| `--text-small` | 0.75rem | 12px | Labels, captions | `--font-body` | 500 |
| `--text-xs` | 0.6875rem | 11px | Meta data | `--font-body` | 400 |
| `--text-micro` | 0.625rem | 10px | Uppercase micro-labels, table headers | `--font-body` | 600 |

Family and weight follow the role mapping (heading typeface 600/700, body typeface 400/500, with 600 for strong body emphasis such as micro-labels and table headers).

---

## Element mapping

The scale table above is the element mapping: its Role column names the element, and Family/Weight give its treatment. Micro-labels and table headers (`--text-micro`) are uppercase, 600 weight.

```css
.page-title {
    font-family: var(--font-heading);
    font-size: var(--text-h1);
    font-weight: var(--font-weight-bold);
}

.body-text {
    font-family: var(--font-body);
    font-size: var(--text-body);   /* 13px */
    font-weight: var(--font-weight-normal);
    line-height: 1.5;              /* approved body line-height */
}
```

---

## Open decisions (define per project)

The scale, weights, and body line-height above are approved (not open). Decide only these per project, then apply consistently:

- **Letter-spacing / tracking** - define if needed (uppercase micro-labels often read better with slight positive tracking); otherwise leave normal.
- **Responsive / fluid type scaling** - the scale above is fixed. Responsive typography is mobile-first: keep the base scale comfortable at the smallest viewport and scale up with width and/or aspect-ratio breakpoints. Keep interactive text controls at a touch target of at least 44px on small screens.

---

## Do / Don't

**Do**
- Use `--font-heading` for headings and `--font-body` for body.
- Size every text element from the scale tokens (`--text-display` ... `--text-micro`).
- Set body copy at 13px (`--text-body`) with line-height 1.5.
- Apply weights via `--font-weight-*` tokens, following the role mapping.

**Don't**
- Use system fonts where the heading and body typefaces are specified - they are mandatory.
- Hardcode font sizes outside the approved steps (e.g. `font-size: 17px`).
- Use a weight the import does not load - there is no 300; 800 is optional display emphasis only.
- Invent letter-spacing or responsive type rules without deciding them for the project (see Open decisions).
