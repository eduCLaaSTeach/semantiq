# Button UI/UX Design Guide

> **Theming:** values here (palette, fonts, tokens) are approved CLaaS2SaaS constants, never per-app;
> the canonical source of truth is `design-tokens.md` (brand assets in `../assets/`). Every token is
> defined in **both** themes, with a System/Dark/Light switcher. Contrast tables below are computed
> against the light theme; dark pairs are verified in design-tokens.md. Semantic colors used as **text
> or icons** - a Secondary button's status label (§4.2) - go through the theme-aware readable tokens
> (`--badge-*-fg`), never the raw semantic hex on a surface.

Reference for AI-assisted development: follow these rules so buttons stay consistent, accessible, and predictable.

> **TL;DR for the AI**
> 1. Pick the variant by **meaning**, not color preference (§3).
> 2. Use the **design tokens** (`var(--color-...)`); never hardcode a new hex or invent a shade.
> 3. There are **two looks** (§4): one **solid** button per action group, and **`btn-secondary`** - one
>    neutral look - for every other labeled action. No ghost, no outline, no third treatment.
> 4. **Cancel, Back, Clear, Reset, Apply, Test, and every actions-column control are never the solid
>    one**, and never borderless either. They look identical on every screen (§4).
> 5. A labeled action always carries its **word** beside the icon; icon-only is chrome, not a button (§4.1, §4.2).
> 6. Always ship **hover, active, focus-visible, disabled, and loading** states.
> 7. **Dark text** on the light solid variants (Warning, Accent); **white text** on the other solids (§9).
> 8. During async work: **disable + spinner + guard against double-submit**, at the same width and the
>    same look. On success, the toast confirms it - return the button to rest.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [The button anatomy](#2-the-button-anatomy)
3. [Variants - meaning drives color](#3-variants--meaning-drives-color)
4. [The two looks (ENFORCED)](#4-the-two-looks-enforced)
5. [Sizes - driven by usage, not whim](#5-sizes--driven-by-usage-not-whim)
6. [States - how a button should behave](#6-states--how-a-button-should-behave)
7. [Loading & saving behavior](#7-loading--saving-behavior)
8. [Button copy / labels](#8-button-copy--labels)
9. [Contrast reference (WCAG)](#9-contrast-reference-wcag)
10. [Full CSS reference (copy-paste ready)](#10-full-css-reference-copy-paste-ready)
11. [Accessibility checklist](#11-accessibility-checklist)
12. [Do's and Don'ts](#12-dos-and-donts)
13. [Rules for the AI assistant](#13-rules-for-the-ai-assistant)
14. [Quick decision guide](#14-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Buttons consume the approved CLaaS2SaaS palette; no colors outside it. The authoritative token set
(both themes) is `design-tokens.md`; the light values:

```css
:root {
    /* Primary (10%) */
    --color-primary: #193E6B;           /* Midnight Blue */
    --color-accent: #B3A125;            /* Green Gold */
    /* Secondary (30%) */
    --color-secondary-violet: #7F3F98;  /* Cadmium Violet - badge/chip only, never a button fill (§3) */
    --color-secondary-blue: #448E9D;    /* Jelly Bean Blue - non-interactive only */
    --color-secondary-sunray: #E9AC53;  /* Sunray */
    /* Tertiary & Neutral (60%) */
    --color-success: #5F8025;           /* Avocado Green */
    --color-danger: #991547;            /* Violet-Red */
    --color-background: #E8DFD0;        /* canvas (dark: #1A2E46) */
    --color-surface: #FFFFFF;           /* card (dark: #253E5D) */
    --color-text: #1E2E42;              /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;        /* (dark: #A3B2C5) */
}
```

---

## 2. The button anatomy

A button is **one semantic meaning** + **one of the two looks** + **one size** + **its current state**.

```
[ variant / secondary ] × [ size ] × [ state ]
  primary                   md         idle
  danger                    sm         loading
  secondary  (the one       lg         disabled
             neutral look)
```

Class composition example: `class="btn btn-secondary btn-sm btn-text-danger"` - the neutral shell at the
table-row size, with a danger-colored label (§4.2). The shell never changes; only the label color does.

---

## 3. Variants - meaning drives color

Choose the variant by **what the action does to the user's data or flow**, then apply the mapped color.

| Variant | Token | Hex | Text color | Use for | Do **not** use for |
|---|---|---|---|---|---|
| **Primary** | `--color-primary` | Midnight Blue `#193E6B` | white | The single main action: **Submit, Save, Create, Confirm, Continue** | More than one action per section |
| **Secondary** *(neutral, the one non-solid look)* | `--btn-secondary-*` (surface + field border + ink) | surface `#FFFFFF` / `#253E5D` | ink `#1E2E42` / `#E9EFF6` | **Every labeled action that is not the group's one main action**: Cancel, Back, Clear, Reset, Apply, Test, Export, Duplicate, and every actions-column control | The one main action of a group |
| **Danger** | `--color-danger` | Violet-Red `#991547` | white | The **destructive / irreversible** action that actually commits: Delete, Remove, Discard, Revoke | A routine cancel, or a row action that only opens the confirmation (§4.2) |
| **Warning** | `--color-secondary-sunray` | Sunray `#E9AC53` | **dark ink `#1E2E42`** | **Caution, not destruction**: "Proceed anyway", override, leave-with-unsaved-changes | The primary CTA |
| **Success** | `--color-success` | Avocado Green `#5F8025` | white | Confirming a **positive completion**: Approve, Mark complete, Publish | A generic form submit - that's **Primary** |
| **Accent** | `--color-accent` | Green Gold `#B3A125` | **dark ink `#1E2E42`** | **Highlight / upsell CTA**: Upgrade, Try Pro, feature spotlight | Standard form actions |

**Rationale**
- **Destructive actions use `--color-danger` (Violet-Red `#991547`), not the accent.** An accent
  color doesn't read as "dangerous" - reusing it for Delete risks accidental deletion.
- **Green Gold (`--color-accent`) is reserved for Accent / highlight** (Upgrade, spotlight); never warnings or deletion.
- **Warning** uses `--color-secondary-sunray` (Sunray `#E9AC53`), separate from true danger.
- **Secondary is neutral, not a second fill.** Cadmium Violet is no longer a button color; it stays a
  badge and chip color. A filled secondary always contradicted the one-solid-per-group rule, and it is
  how the same Cancel ended up violet on one screen and borderless on the next.
- **Cancel, Back, Clear, Reset, and Apply are Secondary** - never a filled color, never borderless (§4).
- **A row's Delete only opens the confirmation**; the dialog's Delete commits. So the row action is
  Secondary with a danger-colored label, and the dialog's action is solid Danger (§4.2).

**Use cases**

| Situation / scenario | Example label | Variant |
|---|---|---|
| The one main action completing a form or view | "Save changes", "Create project", "Continue" | **Primary** |
| A genuine alternative beside the primary | "Save and add another" beside "Save" | **Secondary** |
| Permanently deleting or removing data | "Delete account", "Remove member", "Revoke access" | **Danger** |
| Same destructive action, deliberate pause wanted | "Delete account" in a danger dialog | **Danger** - the pause comes from the type-to-confirm friction, never from a softer look ([modals-dialogs.md](modals-dialogs.md)) |
| Proceeding despite a non-destructive caution | "Proceed anyway", "Leave without saving", "Override" | **Warning** (dark text) |
| Approving or finalizing something positive | "Approve", "Mark complete", "Publish" | **Success** |
| Promoting an upsell, trial, or feature highlight | "Upgrade to Pro", "Try premium", "Start free trial" | **Accent** (dark text) |
| Dismissing, going back, or any low-priority exit | "Cancel", "Back", "Skip", "Dismiss" | **Secondary** |
| Changing only what is on screen | "Apply", "Clear filters", "Reset filters", "Export" | **Secondary** (no solid button in that group, §4) |
| Any action in a toolbar, a table row, or an actions column | "View", "Edit", "Duplicate", "Delete" | **Secondary** + its word (§4.2) |

> **Gut-check:** a routine form submit is **Primary**, not Success. A cancel is **Secondary**, never a
> filled color and never borderless. A delete that commits is **Danger**, not Accent. When two rules seem
> to apply, the one tied to the action's consequence (destructive, positive completion, upsell) wins over
> generic "main action" - and if the action is not its group's main one at all, it is Secondary (§4).

---

## 4. The two looks (ENFORCED)

Color says *what kind*; the look says *how important*. There are exactly **two** looks for a labeled
button, plus one thing that is not a button at all. Nothing else exists, so the same action can never
turn up bordered on one screen and borderless on the next.

| Look | Class | Appearance | Used for |
|---|---|---|---|
| **Solid** | the variant class alone (`btn-primary`, `btn-danger`, `btn-success`, `btn-warning`, `btn-accent`) | Filled with the variant color | **Exactly one per action group**: that group's one main action |
| **Secondary** | `btn-secondary` | **Neutral**: surface fill, 1px control border, ink text | **Every other labeled action**, on every screen |
| *(not a button)* | `icon-btn` | Transparent square, hover tint, no label | Icon-only **chrome** only - the closed list in §4.1 |

**Retired: `btn-ghost` and `btn-outline`.** A borderless button beside a bordered one, an outlined Cancel
on one screen and a filled Cancel on another - that inconsistency is what this section removes. Cadmium
Violet is retired as a button fill too (§3).

**An action group** is one cluster of controls the user chooses between in one place: a form footer, a
dialog footer, a page header's action cluster, a row's actions cell, a table toolbar, an empty state's
action row. A page is not a group, so a page-header CTA and a failed region's `Retry` are both solid
without competing - they are different clusters. Inside one cluster, one solid, or none.

**Same geometry, always.** Every button at a given size shares one height, padding, radius, font, weight,
icon size, and icon-label gap (§5, §10). Between the two looks only the **fill, border, and label color**
differ. Never nudge one button's height, radius, or padding to make it fit a layout.

### Never the solid one

Whatever the screen, these are always `btn-secondary`:

`Cancel` · `Back` · `Close` · `Dismiss` · `Skip` · `Keep editing` · `Clear` · `Clear all` ·
`Clear filters` · `Reset` · `Reset filters` · `Apply` · `Filters` · `Export` · `Columns` · `Density` ·
`Duplicate` · `Test Configuration` (also written `Test call`) · and **every control in an actions column**
(§4.2, [tables.md](tables.md) §6).

**A group that only changes what is on screen has no solid button at all** - a filter bar, a filter
popover footer, a toolbar of view controls. Nothing there commits anything, so nothing there earns the
one solid. The page's solid action is the archetype's own primary CTA, in the page header.

`Test Configuration` is on the list for the same reason: it proves the connection, it does not save it.
So a connection form's footer carries no solid button until the test passes and `Save` appears as the one
solid action ([forms.md](forms.md) §18, [catalog-ui.md](../../ai-model-integration/reference/catalog-ui.md)).

### A button's look never changes with state

A control that is Secondary at rest stays Secondary while it loads, when it succeeds, and when it comes
back. Loading swaps the label for a spinner at the same width and changes nothing else (§7). Never
promote a button from Secondary to solid, or demote it, because its state changed. A button that shifts
emphasis between two states is the same defect as two screens disagreeing - just closer together.

### 4.1 Icon-only is chrome, not a button

`icon-btn` is a **closed list**, by kind rather than by screen. Use it only for:

a **dismiss or close mark** on something dismissable (a modal, a toast, a flash) · a **clear mark** on a
field or a chip (a search input, a filter field, a filter chip) · a **form repeater's per-row remove**
control (`ui-ux-quality.md`, Typed row repeater; [catalog-ui.md](../../ai-model-integration/reference/catalog-ui.md)).

Each one carries an accessible name (`aria-label`), and none of them is a `.btn`. Anything not on that
list is a labeled button. **Never** an actions-column action (§4.2).

**Component primitives are not `.btn` and keep the look their own file defines.** The two looks govern
labeled `.btn` actions, so these are not secondary buttons that lost their border, and they are not
`icon-btn` either:

| Primitive | Defined in | Note |
|---|---|---|
| The shell's top-bar icon controls, the rail brand block, the collapse toggle | [topbar-sidenav.md](topbar-sidenav.md) | The shell owns its own sizes (34px top bar) |
| A sortable column header | [tables.md](tables.md) §4 | A header, not an action |
| The pager: numbered pages, previous and next arrows | [tables.md](tables.md) §9 | The active page is a **current-item marker**, like an active tab, not an action's emphasis |
| An expandable row's chevron | [tables.md](tables.md) §5 | A disclosure control |
| A tab strip | [tabs.md](tabs.md) | The active tab is a current-item marker |
| A segmented control (the theme switcher) | [topbar-sidenav.md](topbar-sidenav.md), `ui-ux-quality.md` | One connected control split into equal segments; the active one is a current-item marker |
| A toast's one inline action (`Undo` / `Retry` / `View`) | [toasts.md](toasts.md) §8 | A text action inside the toast surface |

### 4.2 A labeled action carries its word

Every action the user picks between says what it does, in a word, beside its icon: `View`, `Edit`,
`Delete`, `Restore`, `Duplicate`, `Test`. The icon is the half that is recognisable at a glance; the word
is the half that tells a first-time user which one is which, survives a touch device that has no hover,
and stops two irreversible actions sitting a pixel apart with nothing to tell them apart.

- **The word stays at every breakpoint.** A narrow screen scrolls the actions column
  ([tables.md](tables.md) §11); it never falls back to bare icons.
- **More than about three actions on a row:** keep the first three labeled inline and move the rest
  behind one `More` control whose accessible name names the row ([tables.md](tables.md) §6).
- **A destructive Secondary action keeps the identical shell** and colors only its **icon and label**,
  through the theme-aware token - `btn-secondary btn-text-danger` for a row's Delete
  (`--badge-danger-fg`). Never a different fill, border, height, or radius. Danger is the only status a
  label carries; nothing else earns a colored label.

```html
<!-- Form footer -->
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-primary">Save changes</button>

<!-- Delete confirmation: the dialog's action is the one that commits -->
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-danger">Delete account</button>

<!-- Multi-step form (Continue saves the step's draft, forms.md §10) -->
<button class="btn btn-secondary">Back</button>
<button class="btn btn-primary">Continue</button>

<!-- Filter bar: view controls only, so no solid button in the group.
     Order and size follow the approved component (search-filter.md): clear on the left, apply on the right. -->
<button class="btn btn-secondary btn-sm">Clear all</button>
<button class="btn btn-secondary btn-sm">Apply</button>

<!-- Actions column: icon + word, one shell, danger only in the label -->
<button class="btn btn-secondary btn-sm">
  <svg class="ic" aria-hidden="true"><use href="#i-eye" /></svg> View
</button>
<button class="btn btn-secondary btn-sm">
  <svg class="ic" aria-hidden="true"><use href="#i-pencil" /></svg> Edit
</button>
<button class="btn btn-secondary btn-sm btn-text-danger">
  <svg class="ic" aria-hidden="true"><use href="#i-trash" /></svg> Delete
</button>
```

---

## 5. Sizes - driven by usage, not whim

| Size | Class | Height | Padding | Font | Icon | Use for |
|---|---|---|---|---|---|---|
| **Small** | `btn-sm` | 27px | `0 10px` | 12px | 14px | Dense UIs: tables, toolbars, inline/compact actions, filter chips |
| **Medium** *(default)* | `btn` / `btn-md` | 32px | `0 13px` | 15px | 16px | The default for forms and most of the app |
| **Large** | `btn-lg` | 48px | `0 24px` | 16px | 18px | Prominent CTAs, hero/landing, empty-state actions, mobile primary actions |

**Touch-target rule:** interactive targets should be **≥ 44 × 44px** on touch devices. `btn-sm` and
the 32px default are below that - only use them for mouse/trackpad input or where the element has
extra surrounding hit area. For a mobile primary action, use `btn-lg`.

**Touch bump (ENFORCED):** on a coarse pointer, `btn-sm` and `icon-btn` grow to a 44px minimum rather
than shrinking the label out (§10). The label never disappears to save width (§4.2).

**Modifiers**

| Modifier | Class | Use for |
|---|---|---|
| Full width | `btn-block` | Mobile forms, narrow cards, single stacked CTA |
| Status label | `btn-text-danger` | A Secondary action whose meaning is destructive: same shell, colored **icon and label** only (§4.2) |

There is no icon-only button modifier. An icon-only control is `icon-btn`, it is chrome, and it is
limited to the closed list in §4.1.

---

## 6. States - how a button should behave

Every button must implement all of these. The first five are the interaction contract; loading is §7.

| State | Trigger | Behavior |
|---|---|---|
| **Default / Rest** | - | The variant's base appearance |
| **Hover** | Pointer over (pointer devices) | Lifts slightly (`translateY(-1px)`) and darkens (`brightness 0.94`) |
| **Active / Pressed** | Mouse-down / touch | Presses down (`translateY(1px)`), darkens further (`brightness 0.90`) |
| **Focus (keyboard)** | Tab / keyboard focus | **Visible focus ring** via `:focus-visible` - mandatory; never remove without an equally visible replacement |
| **Disabled** | Action unavailable | `opacity: 0.5`, `cursor: not-allowed`, no hover/active transforms. Don't use disabled to *silently* signal a validation error - explain why nearby |
| **Loading / Saving** | Async work in progress | See §7 |

The enforced interaction CSS is preserved verbatim and extended (focus ring, hover/active darkening) in §10.

> **Disabled vs. "looks done but blocked":** if a primary action is blocked by an incomplete form,
> prefer keeping it enabled and showing the validation message on click, rather than a permanently
> greyed-out button users can't diagnose. If you do disable it, pair it with visible text explaining
> what's missing.

---

## 7. Loading & saving behavior

The button is the user's proof their click registered and work is happening - and the primary guard
against duplicate submissions.

1. **On click of an async action, immediately enter loading:** disable interaction, hide the label
   (`color: transparent`), show a spinner. Prevents double-submission.
2. **Keep the width stable.** The label is hidden, not removed, and the spinner is absolutely centered,
   so the button doesn't collapse. (Add `min-width` if your label changes length.)
3. **Pick the affordance for the duration:** short (< ~1s) spinner only is fine; longer / important /
   destructive → swap the label to a verb-ing form ("Saving...", "Deleting...", "Uploading...").
4. **On success:** return to **rest state**. Success is announced by the toast, so the button doesn't
   confirm anything. (Match the toast to the action: *Save changes* → toast *"Changes saved."*) Never
   leave a button spinning forever.
5. **On error:** return to rest, **re-enable**, surface the error elsewhere (inline / toast). Don't trap
   the user in loading.
6. **Announce to assistive tech:** set `aria-busy="true"` while loading and disable via the `disabled`
   attribute (or `aria-disabled` + a handler guard) so keyboard/screen-reader users can't re-trigger.
7. **Never rely on the spinner alone** for important or destructive operations - pair with text.

```
idle ──click──▶ loading ──success──▶ idle   (+ toast: "Changes saved.")
                   │
                   └────error─────▶ idle   (+ toast / inline error message)
```

### Pattern (framework-free; frameworks mirror this contract)

```js
async function runAction(btn, action) {
  if (btn.classList.contains('loading')) return;   // guard double-submit
  btn.classList.add('loading');
  btn.disabled = true;
  btn.setAttribute('aria-busy', 'true');
  try {
    await action();
    showToast('Changes saved.');   // success is handled by the toast notification system
  } catch (err) {
    throw err;                     // surface the error elsewhere (toast / inline); do not trap in loading
  } finally {
    btn.classList.remove('loading');   // back to rest state
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
  }
}
```

---

## 8. Button copy / labels

- **Say what happens.** Prefer "Save changes" / "Create project" over generic "Submit" or "OK".
- **Keep the verb consistent through the flow.** A "Publish" button should produce a "Published." toast.
- **Sentence case, active voice, no filler.** "Delete file", not "Click here to delete this file."
- **Every labeled action keeps its word**, even beside an icon and even on a narrow screen (§4.2).
- **Icon-only chrome controls still need a name** via `aria-label` (e.g. `aria-label="Close"`) - and they
  are only the eight controls in §4.1, never a table action.
- **One label, one look.** The same word means the same button everywhere: `Cancel` is Secondary on every
  screen in the app, not Secondary here and filled there.

---

## 9. Contrast reference (WCAG)

Ratios are computed for the approved palette against a white surface; ✓ = passes WCAG AA for normal
text (≥ 4.5:1). Dark-theme text/surface pairs are verified in `design-tokens.md`; let these results
drive the text color - do not restyle per app.

| Variant background | vs. white text | vs. dark ink `#1E2E42` | Use text color |
|---|---|---|---|
| Midnight Blue `#193E6B` Primary | **10.8 : 1** ✓ AAA | - | **white** |
| Violet-Red `#991547` Danger | **8.2 : 1** ✓ AAA | - | **white** |
| Neutral **Secondary**: surface `#FFFFFF` / `#253E5D` | - | **13.8 : 1** ✓ AAA (light; dark pair verified in design-tokens.md) | **ink `#1E2E42`** / `#E9EFF6` |
| Avocado Green `#5F8025` Success | **4.6 : 1** ✓ AA¹ | - | **white** (use ≥ 15px / 600 weight) |
| Green Gold `#B3A125` Accent | 2.6 : 1 ✗ | **5.3 : 1** ✓ AA | **dark ink `#1E2E42`** |
| Sunray `#E9AC53` Warning | 2.0 : 1 ✗ | **6.9 : 1** ✓ AA | **dark ink `#1E2E42`** |

¹ Borderline; comfortably passes for bold/large labels. Keep Success labels at the default weight (600) or higher.

> **Rule of thumb:** the two **light** solid buttons (Accent, Warning) use **dark** text. White text on
> them fails contrast. The neutral Secondary is the app's own surface-and-ink pair, so it is the same
> verified contrast as body text on a card.

The Secondary border is the same control-boundary token a form field uses (`--field-border` in
[forms.md](forms.md)) - one value for buttons and inputs, so a button and the field beside it are visibly
the same family. Card borders are a separate, softer token (`--card-border-strong` in `design-tokens.md`)
and are not interchangeable with it. Do not invent a third value for buttons.

---

## 10. Full CSS reference (copy-paste ready)

Enforced interaction rules are preserved verbatim and marked. Uses `color-mix()` (current evergreen browsers).

```css
:root {
  /* Palette from §1 (canonical: design-tokens.md). Button tokens: */
  --btn-radius: 8px;
  --btn-font-weight: 600;
  --btn-focus-ring: var(--color-primary);   /* focus ring is always the primary interactive accent, both themes */

  /* Status label token (canonical values, both themes: design-tokens.md). Declared here so this block
     stands alone; a project that already defines it references that one instead. */
  --badge-danger-fg: #85113E;               /* dark theme: #F5B8CF */

  /* The one neutral Secondary look (§4). This is the same control boundary a form field uses
     (--field-border in forms.md); a project that defines that token references it instead of
     restating the mix here. Card borders are a separate, softer token in design-tokens.md. */
  --btn-secondary-bg:           var(--color-surface);
  --btn-secondary-fg:           var(--color-text);
  --btn-secondary-border:       color-mix(in srgb, var(--color-text) 25%, transparent);
  --btn-secondary-border-hover: color-mix(in srgb, var(--color-text) 40%, transparent);
  --btn-secondary-hover-bg:     color-mix(in srgb, var(--color-text) 6%, var(--color-surface));
}

/* ===== Base (defaults to medium size) ===== */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 0.5em;
  border: 1px solid transparent; border-radius: var(--btn-radius);
  font-family: inherit; font-weight: var(--btn-font-weight); line-height: 1;
  text-decoration: none; cursor: pointer; user-select: none; white-space: nowrap;
  transition: all 0.2s ease;          /* ENFORCED */
  padding: 0 13px;                    /* md */
  font-size: 0.9375rem;               /* 15px, md */
  min-height: 32px;                   /* md */
  --btn-spinner: #fff;                /* spinner color; overridden on light variants */
}

/* ===== Interaction states (ENFORCED core preserved) ===== */
.btn:hover  { transform: translateY(-1px); filter: brightness(0.94); }  /* ENFORCED transform */
.btn:active { transform: translateY(1px);  filter: brightness(0.90); }  /* ENFORCED transform */
.btn:focus-visible { outline: 2px solid var(--btn-focus-ring); outline-offset: 2px; }
.btn:disabled, .btn[aria-disabled="true"] {
  opacity: 0.5;                       /* ENFORCED */
  cursor: not-allowed;                /* ENFORCED */
  transform: none; filter: none;
}

/* ===== Loading ===== */
.btn.loading {
  position: relative;                 /* ENFORCED */
  color: transparent;                 /* ENFORCED */
  pointer-events: none;
}
.btn.loading::after {
  content: ""; position: absolute; top: 50%; left: 50%;
  width: 1em; height: 1em; margin: -0.5em 0 0 -0.5em;
  border: 2px solid color-mix(in srgb, var(--btn-spinner) 35%, transparent);
  border-top-color: var(--btn-spinner); border-radius: 50%;
  animation: btn-spin 0.6s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }

/* ===== Look 1: solid - exactly one per action group (§4) ===== */
.btn-primary   { background: var(--color-primary);          color: #fff; }
.btn-danger    { background: var(--color-danger);           color: #fff; }
.btn-success   { background: var(--color-success);          color: #fff; }
.btn-warning   { background: var(--color-secondary-sunray); color: var(--color-text); --btn-spinner: var(--color-text); }
.btn-accent    { background: var(--color-accent);           color: var(--color-text); --btn-spinner: var(--color-text); }

/* ===== Look 2: the one neutral Secondary - every other labeled action (§4) ===== */
.btn-secondary {
  background: var(--btn-secondary-bg);
  color: var(--btn-secondary-fg);
  border-color: var(--btn-secondary-border);
  --btn-spinner: var(--btn-secondary-fg);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--btn-secondary-hover-bg);
  border-color: var(--btn-secondary-border-hover);
  filter: none;                       /* the neutral look does not darken; it tints */
}

/* Status label on the SAME shell - icon + label only, never a different fill or size (§4.2) */
.btn-text-danger { color: var(--badge-danger-fg); --btn-spinner: var(--badge-danger-fg); }
.btn-text-danger:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-danger) 8%, var(--color-surface));
  border-color: color-mix(in srgb, var(--color-danger) 45%, transparent);
}

/* ===== Not a button: icon-only chrome, closed list (§4.1) ===== */
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; padding: 0;
  border: 1px solid transparent; border-radius: var(--btn-radius);
  background: transparent; color: var(--color-text-muted);
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.icon-btn:hover        { background: color-mix(in srgb, var(--color-text) 8%, transparent); color: var(--color-text); }
.icon-btn:focus-visible { outline: 2px solid var(--btn-focus-ring); outline-offset: 2px; }
.icon-btn-sm           { width: 27px; height: 27px; }
/* Never used for a labeled action, and never in an actions column (§4.2). */

/* ===== Sizes ===== */
.btn .ic, .btn svg { width: 16px; height: 16px; flex: none; }   /* one icon size per size step (§5) */
.btn-sm .ic, .btn-sm svg { width: 14px; height: 14px; }
.btn-lg .ic, .btn-lg svg { width: 18px; height: 18px; }

.btn-sm { padding: 0 10px; font-size: 0.75rem;   min-height: 27px; }   /* 12px */
.btn-md { padding: 0 13px; font-size: 0.9375rem; min-height: 32px; }   /* 15px (default) */
.btn-lg { padding: 0 24px; font-size: 1rem;      min-height: 48px; }   /* 16px */

/* ===== Modifiers ===== */
.btn-block { display: flex; width: 100%; }

/* ===== Touch bump - grow, never drop the label (§4.2, §5) ===== */
@media (pointer: coarse) {
  .btn-sm  { min-height: 44px; }
  .icon-btn, .icon-btn-sm { min-width: 44px; min-height: 44px; }
}

/* ===== Reduced motion (quality floor) ===== */
@media (prefers-reduced-motion: reduce) {
  .btn { transition: none; }
  .btn:hover, .btn:active { transform: none; }
  .btn.loading::after { animation-duration: 1.2s; }
}
```

---

## 11. Accessibility checklist

- [ ] Use a real `<button>` (or `<a>` for navigation) - **never** a `<div>`.
- [ ] Set `type="button"` unless it is a genuine form submit (`type="submit"`).
- [ ] Visible **`:focus-visible`** ring; never strip `outline` without a replacement.
- [ ] Every labeled action shows its **word**, not the icon alone (§4.2).
- [ ] Every `icon-btn` has an **`aria-label`**, and is one of the three chrome kinds in §4.1.
- [ ] Don't convey state with **color alone** - pair with text/icon (especially Danger and Disabled).
- [ ] Meet **WCAG AA** contrast (§9). Light buttons (Warning, Accent) use **dark** text.
- [ ] Loading sets **`aria-busy`** and blocks re-trigger.
- [ ] Touch targets **≥ 44 × 44px**.
- [ ] **`prefers-reduced-motion`** respected.

---

## 12. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Use `btn-danger` (Violet-Red `#991547`) for Delete / Remove | Use `btn-accent` for destructive actions | Green Gold reads as a highlight, not a warning |
| Keep one solid button per group; make Cancel `btn-secondary` | Put two filled buttons side by side | Competing emphasis hides the real action |
| Give Cancel, Clear, Reset, Back, and Apply the **same** `btn-secondary` look on every screen | Render one of them borderless here and filled there | The same action looking different is the defect this section exists to remove (§4) |
| Label every actions-column control: icon **+** "View" / "Edit" / "Delete" | Ship a bare pencil and trash side by side | An unlabeled icon makes the user guess, and touch devices have no hover to explain it (§4.2) |
| Keep a button's look identical while it loads | Promote a Secondary button to solid when it becomes available | State changes the label and the spinner, never the emphasis (§4) |
| Leave a filter bar with no solid button | Make "Apply" the page's second primary button | Nothing in a filter bar commits anything (§4) |
| Disable the button and show the spinner on submit | Leave it clickable while the request runs | Prevents double-submits |
| Let a **toast** confirm success and return the button to rest | Relabel the button to "Saved ✓" after saving | The toast already confirms it |
| Put **dark** text on Warning / Accent (light) buttons | Use white text on Sunray `#E9AC53` or Green Gold `#B3A125` | White fails WCAG contrast (2.0:1 / 2.6:1) |
| Label by outcome: "Save changes", "Delete file" | Use vague labels: "Submit", "OK", "Click here" | Specific labels tell users what will happen |
| Keep wording consistent: "Publish" → "Published" toast | Mix vocab: "Publish" → "Saved" toast | Consistent wording is how people learn the UI |
| Ship a visible `:focus-visible` ring | Set `outline: none` with no replacement | Keyboard users lose track of where they are |
| Use a real `<button>` / `<a>` | Attach `onClick` to a `<div>` / `<span>` | Non-interactive elements break keyboard + screen-reader use |
| Size for context: `btn-sm` in tables, `btn-lg` for mobile CTAs | Use `btn-sm` as the only tap target on mobile | Below the 44 × 44px touch minimum |
| Convey state with text/icon **and** color | Rely on color alone (e.g. red = danger) | Color-blind users can't read color-only meaning |
| On failure, reset to rest and show the error | Leave the button spinning after an error | Traps the user with no feedback or retry |

---

## 13. Rules for the AI assistant

**ALWAYS**
- Use design tokens (`var(--color-...)`); never hardcode a new hex or invent a shade.
- Choose the variant by **semantic meaning** (§3), not appearance.
- Keep exactly **one** solid button per action group; every other labeled action is `btn-secondary` (§4).
- Ship **hover, active, focus-visible, disabled, and loading** states on every button.
- Use **dark text** on Warning/Accent; **white text** on Primary/Danger/Success; **ink** on Secondary.
- During async work: **disable + spinner + guard against double-submit**; surface errors elsewhere and reset on completion.
- Name buttons by what they do, and keep the verb consistent through the flow.
- Give every actions-column control its **word** beside the icon, at every breakpoint (§4.2).
- Keep one label to one look: `Cancel` / `Clear` / `Reset` / `Apply` are `btn-secondary` everywhere.

**NEVER**
- Use **Danger red** for a non-destructive action, or **Success green** for a generic submit (that's Primary).
- Use a filled color for **Cancel / Back / Clear / Reset / Apply** - they are `btn-secondary` (§4).
- Use `btn-ghost` or `btn-outline`. Both are retired; there is no borderless labeled button.
- Put an icon-only control in an actions column, or use `icon-btn` for anything outside the §4.1 list (a component primitive is not `icon-btn` either - it keeps its own file's look).
- Change a button's fill, border, size, or emphasis because its state changed (§4).
- Remove the focus outline without an equally visible replacement.
- Use a `<div>` (or click-handler on a non-interactive element) as a button.
- Rely on color alone to communicate meaning.
- Leave a button spinning with no success/error resolution.

---

## 14. Quick decision guide

```
Is this the ONE main action of its group?
├─ No ....................................................... btn-secondary   (always, every screen)
│    └─ carries a status meaning (a row's Delete)? .......... btn-secondary btn-text-danger
└─ Yes - what does it do?
     ├─ Submits / saves / creates / continues ............... btn-primary  (solid)
     ├─ Deletes / removes / cannot be undone, and commits .. btn-danger
     ├─ Caution, not destructive (override / proceed) ...... btn-warning  (dark text)
     ├─ Confirms a positive completion (approve / publish) . btn-success
     └─ Upsell / highlight / spotlight .................... btn-accent   (dark text)

Does the group only change what is on screen (filter bar, view toolbar)?
└─ Then it has NO solid button. Every control in it is btn-secondary.

Is it icon-only?
├─ A dismiss/close mark, a field or chip clear mark, a
│  repeater's row remove (the three kinds in §4.1) .......... icon-btn + aria-label
├─ A component primitive (pager, sort header, tab strip,
│  segmented control, shell control, toast action) ......... neither look; its own file defines it (§4.1)
└─ Anything else, including every actions-column control .... it is not icon-only. Add the word (§4.2)

How big?
├─ Table row, toolbar, dense area ........................... btn-sm  (44px minimum on touch)
├─ Default form / most UI ................................... btn-md
└─ Hero, landing, empty state, mobile primary ............... btn-lg
```
