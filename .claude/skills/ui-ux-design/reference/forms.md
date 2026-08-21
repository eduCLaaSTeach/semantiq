# Form & Input UI/UX Design Guide

Reference for AI-assisted development of forms, inputs, and validation. Values here (palette, fonts,
tokens) are approved constants, the same in every app; the canonical source is `design-tokens.md` (and
`../assets/`). Responsive is mobile-first; every token is defined in both a light and a dark theme with
a Light/Dark/System switcher.

> **TL;DR for the AI**
> 1. **Data entry is page-hosted, ENFORCED** (§3): a create / edit / multi-step / settings form is its own route or a form region on the current page, never a modal. Compact `form-modal` sizing survives only for the ~3 fields a decision dialog may carry.
> 2. **Validation is ENFORCED** (§6-§7): inline on blur, error message below the field, and the submit button stays enabled - clicking it validates and jumps to the first invalid field.
> 3. **One column by default** (§9). Use 2-3 columns only with justification on a page; a dialog's fields are always one column.
> 4. Many fields → group by category (§8): section headings / fieldsets, in-page tabs, or steps (wizard, §10).
> 5. **A wizard's draft is ENFORCED** (§10): the advance button is **Continue**, it saves the validated step server-side before advancing, and the flow resumes at that step on any device.
> 6. Every field needs a visible, associated `<label>`; placeholders are not labels.
> 7. Use the design tokens - never hardcode a hex or invent a shade outside the palette.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Anatomy of a field](#2-anatomy-of-a-field)
3. [Form types - data entry is page-hosted (ENFORCED)](#3-form-types--data-entry-is-page-hosted-enforced)
4. [Field sizes (per form type)](#4-field-sizes-per-form-type)
5. [Input types](#5-input-types)
6. [Validation states (ENFORCED - same for both form types)](#6-validation-states-enforced--same-for-both-form-types)
7. [Validation behavior & timing (ENFORCED)](#7-validation-behavior--timing-enforced)
8. [Grouping fields by category](#8-grouping-fields-by-category)
9. [Layout - columns (PRINCIPLED)](#9-layout--columns-principled)
10. [Multi-step forms (wizards) and resumable drafts](#10-multi-step-forms-wizards-and-resumable-drafts)
11. [Labels, help text, placeholders & tooltips](#11-labels-help-text-placeholders--tooltips)
12. [Full CSS reference (copy-paste ready)](#12-full-css-reference-copy-paste-ready)
13. [Full JS reference (validation contract)](#13-full-js-reference-validation-contract)
14. [Accessibility checklist](#14-accessibility-checklist)
15. [Do's and Don'ts](#15-dos-and-donts)
16. [Rules for the AI assistant](#16-rules-for-the-ai-assistant)
17. [Quick decision guide](#17-quick-decision-guide)
18. [Connection / integration configuration - test before save (ENFORCED)](#18-connection--integration-configuration--test-before-save-enforced)

---

## 1. Design tokens (source of truth)

Complete two-theme set is canonical in `design-tokens.md`; the values below are the approved constants
forms consume. Buttons inside forms follow [buttons.md](buttons.md); the few fields a decision dialog
carries follow [modals-dialogs.md](modals-dialogs.md) for the dialog itself.

```css
:root {
    /* Brand palette (approved) */
    --color-primary: #193E6B;                 /* Midnight Blue */
    --color-accent: #B3A125;                  /* Green Gold */
    --color-secondary-violet: #7F3F98;        /* Cadmium Violet - badge/chip only, never a button fill */
    --color-secondary-blue: #448E9D;          /* Jelly Bean Blue - non-interactive only */
    --color-secondary-sunray: #E9AC53;        /* Sunray */
    --color-success: #5F8025;        /* Avocado Green - success surface fills */
    --color-danger: #991547;         /* Violet-Red - danger surface fills */
    --color-background: #E8DFD0;     /* canvas; dark theme: #1A2E46 */
    --color-surface: #FFFFFF;        /* card; dark theme: #253E5D */
    --color-text: #1E2E42;           /* dark theme: #E9EFF6 */
    --color-text-muted: #4D5E75;     /* dark theme: #A3B2C5 */

    /* Theme-aware readable semantic tokens (--badge-*-fg pattern) - use these
       whenever a semantic hue is text, an icon, or a border on a surface;
       the raw semantic hex is not legible on both themes */
    --badge-danger-fg: #85113E;      /* dark theme: #F5B8CF */
    --badge-success-fg: #3A4E13;     /* dark theme: #CBE79B */

    /* Field tokens (shared) */
    --field-radius: 8px;
    --field-border: color-mix(in srgb, var(--color-text) 25%, transparent);
    --field-border-hover: color-mix(in srgb, var(--color-text) 40%, transparent);
    --field-focus-ring: var(--color-primary);   /* focus ring is always the primary interactive accent, both themes */
    --field-label-weight: 600;

    /* Page-form sizing (roomy) */
    --field-height-page: 32px;
    --field-font-page: 1rem;         /* 16px - avoids iOS zoom */
    --field-pad-page: 0 10px;        /* horizontal only - the 32px height centers content */
    --field-gap-page: 20px;          /* vertical gap between fields */
    --form-col-page: 640px;          /* single-column max-width */

    /* Dialog-field sizing (compact; see §3) */
    --field-height-modal: 32px;
    --field-font-modal: 0.9375rem;   /* 15px */
    --field-pad-modal: 0 10px;
    --field-gap-modal: 14px;
}
```

---

## 2. Anatomy of a field

A field is **a label** + **the control** + **(optional) help text** + **a validation message slot**.

```
Label *                      ← visible <label for>, * marks required
┌─────────────────────────┐
│  control (input/select) │  ← the input itself
└─────────────────────────┘
Helper text or hint          ← optional, muted, BEFORE the user errs
⚠ Error message              ← appears BELOW the field on invalid (§6)
```

- **Label:** always visible, always associated (`<label for>` or wrapping). Mark required with `*` **and** `aria-required`.
- **Control:** input/select/textarea, sized per form type (§4).
- **Help text:** optional guidance shown up front (`aria-describedby`).
- **Message slot:** reserved space below the control so the layout doesn't jump when the error appears.

---

## 3. Form types - data entry is page-hosted (ENFORCED)

**A form that creates or edits a record lives on a page, never in a modal.** Its own route
(`.../new`, `.../<id>/edit`) or a form region on the current page, inside the app shell, with the
navigation and page header still there. This is not a per-screen judgement call: no create form, edit
form, multi-step form, or settings form goes in a popup, however few fields it has.

That leaves one narrow in-dialog case. A confirmation or decision dialog may carry up to about **three
fields when those fields are the decision** - the word typed to confirm a purge, a reason for a
rejection, a new date when rescheduling. It is a decision with an input, not a record editor, and it is
the only reason the compact `form-modal` sizing still exists.

| | **Page form** (`form-page`) | **Fields inside a dialog** (`form-modal`) |
|---|---|---|
| **Lives in** | A route, or a form region on the current page | Inside a decision [dialog](modals-dialogs.md) |
| **Used for** | Every create / edit / multi-step / settings form | Up to ~3 fields that *are* the decision |
| **Space** | Generous, the page scrolls | Constrained - body scrolls |
| **Field size** | **Roomy** (32px, 16px) - §4 | **Compact** (32px, 15px) - §4 |
| **Submit location** | Sticky footer or end-of-form action bar | Dialog footer (one primary button) |
| **Class hook** | `form-page` | `form-modal`, or `.modal-input` for a single field ([modals-dialogs.md](modals-dialogs.md) §11) |

**Choosing:** if the user is entering or changing a record, it is a page form. If the user is answering
a question and the input is part of the answer, the fields may sit in the dialog. A dialog reaching for
a fourth field, a category, or a section heading has become a form and belongs on a page.

> Validation states, timing, labels, help text, and grouping are **identical** in both - only field sizing changes.

> **The multi-step form (wizard).** A wizard is a *structural* pattern hosting a long, sequential form across ordered steps. It is always page-hosted, and it saves a resumable draft. See §10.

---

## 4. Field sizes (per form type)

| Property | **Page form** (`form-page`) | **Dialog fields** (`form-modal`) | Why |
|---|---|---|---|
| Control height | **32px** | **32px** | Compact both contexts; touch raises to ≥44px (§12) |
| Font size | **16px** (`1rem`) | **15px** (`0.9375rem`) | 16px avoids mobile zoom on a standalone page |
| Padding | `0 10px` | `0 10px` | Horizontal only - the 32px height centers content |
| Vertical gap | **20px** | **14px** | A dialog is tighter |
| Single-column max-width | **640px** | Inherits the dialog width (420/600/900) | Readable line length |
| Label size | 12px / 600 | 12px / 600 | One compact label size |

**Rules**
- On touch (aspect-ratio ≤ 1/1), **both** form types raise controls to **≥ 44px** (§12).
- Don't mix sizes within one form.
- Full-width controls within their column by default; constrain width only for a known short length (a 4-digit code, a postcode).

---

## 5. Input types

Use the right control; don't reinvent native ones.

| Data | Control | Notes |
|---|---|---|
| Short text | `<input type="text">` | Set `autocomplete`, `inputmode` where helpful |
| Email / tel / url / number | `type="email|tel|url|number"` | Correct mobile keyboard + native validation |
| Long text | `<textarea>` | Allow vertical resize; cap rows sensibly |
| One of few options | radio group **or** native `<select>` | ≤ 5 visible → radios; more → select |
| One of many options | `<select>` / combobox | Searchable combobox for long lists |
| Multiple options | checkboxes / multi-select | Checkboxes for ≤ ~7; multi-select beyond |
| Boolean | single checkbox or switch | Switch for instant-apply settings |
| Date / time | `type="date|time"` or a date picker | Always show the expected format |
| Password | `type="password"` + show/hide toggle | Toggle needs `aria-label`; never block paste |

> Whatever the control, it still gets a label, the §6 validation states, and the §4 size for its form type.

**When to use which control:**
- **Text vs textarea** - single line for names/codes/short values; `<textarea>` only when multi-line is genuinely expected.
- **Radios vs select** - ≤ 5 exclusive options worth comparing at a glance → radios; more, or tight space → `<select>`; long lists → searchable combobox.
- **Checkboxes vs multi-select** - a handful of independent on/off choices seen together → checkboxes; many → multi-select.
- **Single checkbox vs switch** - checkbox for consent applied **on submit**; switch for a setting that takes effect **instantly**.
- **Number vs stepper vs slider** - free numeric → `type="number"` + `inputmode="numeric"`; small bounded counts → stepper; fuzzy ranges → slider.
- **Native date vs custom picker** - `type="date"` for a single date; a custom picker only for ranges or constrained availability; always surface the expected format.
- **One field vs split fields** - one field unless parts are validated/used separately (card expiry MM / YY, phone country code).

---

## 6. Validation states (ENFORCED - same for both form types)

Identical on a page and inside a dialog; only sizing differs. Every field implements them.

| State | Trigger | Appearance | A11y |
|---|---|---|---|
| **Default / Rest** | - | Neutral border (`--field-border`) | - |
| **Hover** | Pointer over | Border darkens (`--field-border-hover`) | - |
| **Focus** | Keyboard/click focus | **Visible focus ring** (`--field-focus-ring`), 2px | Mandatory - never remove without replacement |
| **Filled** | Has a value | Same as rest, with text color | - |
| **Disabled** | Not editable now | `opacity: 0.5`, `not-allowed`, muted | `disabled` |
| **Read-only** | Shown but not editable | Muted fill, no edit focus ring | `readonly` |
| **Error / Invalid** | Failed validation (§7) | **Danger** border + danger message **below** + `⚠` icon | `aria-invalid="true"`, `aria-describedby` → message id |

**Rules**
- **Validation is error-only.** Show feedback only when a field is invalid - no green border, `✓`, or "Looks good" on valid fields. A passing field returns to rest.
- **Error message goes BELOW the field** (ENFORCED), in danger color, tied via `aria-describedby`.
- **Error text and border use the theme-aware readable danger token** (`--badge-danger-fg`), never raw Violet-Red `#991547`.
- **Never rely on color alone.** Pair the error red with an icon (`⚠`) and text.
- **Reserve the message slot** so the form doesn't jump when an error appears/disappears.
- **A form-level error gets one alert at the form foot** (`.form-alert`), beside the submit, for the
  error that belongs to no field: the save failed, the service was unreachable, a cross-field rule broke.
  One message about the form, never a list of its fields, and it persists until the condition clears
  (unlike the blocked-submit toast, §7 rule 5).
- **No error-summary card** (ENFORCED). The inline messages are the record of what is wrong; a card at
  the top or foot of the form repeating them makes the user read every error twice and track it in two
  places. A blocked submit is announced once, by a toast (§7 rule 5).

---

## 7. Validation behavior & timing (ENFORCED)

The authoritative form-validation contract for *when* validation runs.

1. **Validate inline on blur.** Show any error immediately below the field. Don't wait for submit.
2. **Error message below the field** (§6).
3. **Submit stays enabled.** Never grey out the primary button. Clicking it runs full validation; if anything is invalid, submit is blocked and the first error is revealed (rule 5).
4. **Re-validate on input *after* a field has errored** so the error clears the moment it's fixed - the field returns to rest (no success styling).
5. **On submit, block and announce once.** If anything is still invalid, focus and scroll to the **first** invalid field and raise **one error toast** naming how many fields need attention ([toasts.md](toasts.md) §4). An error toast persists until dismissed, so nothing is gained by also stacking a summary card over the inline messages - and there is none (§6).
6. **Async/server errors** map back to the relevant field. One that belongs to **no** field - the submit itself failed, a cross-field rule broke, the service was unreachable - gets one **persistent inline alert at the form foot**, beside the submit, where the user needs the reason while acting on it. One message about the form, never a list of its fields. Never swallow either silently.
7. **Don't validate required-ness on first focus.** Empty-but-untouched is not an error yet - flag only on blur or submit.

> **Why submit stays enabled:** a permanently greyed-out submit with no explanation frustrates users. An enabled submit that validates on click and focuses the first invalid field explains itself. Matches [buttons.md](buttons.md) §6.

### Timeline

```
field focus → (type) → blur ─valid──▶ rest (no success styling)
                          │
                          └─invalid─▶ error below field
                                         │ (user edits)
                                         └─on input─▶ re-validate ─▶ clears to rest when fixed
submit click ─any invalid─▶ focus first invalid field + announce
submit click ─all valid───▶ submits (the button is never disabled by validation)
```

---

## 8. Grouping fields by category

When a form has many fields, group them by category instead of a single long wall of inputs.

**When to group:** more than **~8 fields**, or fields in clear real-world categories → group. Fewer, or
one obvious set → a single ungrouped column.

**How to group:**
- Use a `<fieldset>` with a `<legend>`, or a section with a heading, per category (*Personal details*, *Contact*, *Billing address*, *Preferences*).
- Order by priority and logic: what the user knows first comes first; required before optional.
- Keep each group short; a group that grows large may deserve its own step or page.
- One **primary action** for the whole form, not per group (unless groups are independently saved).
- For very long page forms, consider a stepper/wizard or in-page section nav.

```html
<form class="form form-page">
  <fieldset class="field-group">
    <legend class="field-group-title">Personal details</legend>
    <!-- first name, last name, date of birth ... -->
  </fieldset>
  <fieldset class="field-group">
    <legend class="field-group-title">Contact</legend>
    <!-- email, phone ... -->
  </fieldset>
  <div class="form-actions">
    <button type="button" class="btn btn-secondary">Cancel</button>
    <button type="submit" class="btn btn-primary">Save</button>
  </div>
</form>
```

> Grouping is a page-form concern. A dialog's few decision fields (§3) are never grouped: if grouping is called for, it is a form and belongs on a page.

### Three grouping mechanisms - headings, tabs, steps

| Mechanism | Use in | Pattern |
|---|---|---|
| **Section heading / `<fieldset>`** | Page forms | All categories visible at once; user scrolls between them |
| **Tabs** | Page forms | One category per tab; everything stays on one screen / height |
| **Steps (wizard)** | Long or sequential page-hosted flows | One category per step; **Back / Continue** (§10) |

Pick by length and flow: a few categories visible at once → headings; many to switch between without
scrolling → tabs; a long ordered flow → steps.

**Tabs** hold a long record's categories on one screen (e.g. *Basic Info · Contact & Address ·
Professional · CRM & Owner · Identity / Compliance · Marketing*, or *Profile · Security · Notifications ·
Billing*), using this markup:

```html
<!-- In-page form tabs: one category per tab, all on one route.
     A real ARIA tab widget - the panels are in-page, not separate routes (tabs.md §4.2). -->
<div class="form-tabs" role="tablist" aria-label="Contact details">
  <button type="button" class="form-tab active" role="tab" id="ftab-basic"
          aria-selected="true"  aria-controls="fpanel-basic"  data-ftab="cBasic">Basic Info</button>
  <button type="button" class="form-tab" role="tab" id="ftab-contact" tabindex="-1"
          aria-selected="false" aria-controls="fpanel-contact" data-ftab="cContact">Contact &amp; Address</button>
  <button type="button" class="form-tab" role="tab" id="ftab-prof" tabindex="-1"
          aria-selected="false" aria-controls="fpanel-prof"    data-ftab="cProfessional">Professional</button>
</div>

<!-- Each panel is its own field grid, so role="tabpanel" survives in the a11y tree -->
<div class="form-grid form-tab-panel" role="tabpanel" id="fpanel-basic"
     aria-labelledby="ftab-basic" tabindex="0" data-fpanel="cBasic">
  <div class="form-section-heading">Personal Details</div>
  <!-- fields ... -->
</div>
<div class="form-grid form-tab-panel" role="tabpanel" id="fpanel-contact"
     aria-labelledby="ftab-contact" tabindex="0" data-fpanel="cContact" hidden>
  <!-- fields ... -->
</div>
```

**Rules for tabs:** keep **required fields in the first tab** so they're never hidden behind a click;
order tabs by likelihood of use; still use section headings *inside* a tab that holds many fields; the
form's **one** submit validates across **all** tabs and, on failure, switches to the tab holding the
first invalid field.

**The widget contract is [tabs.md](tabs.md) §4.2 and §8.2, and it is ENFORCED.** These panels live on one
route, so they are the genuine tab-widget case rather than the route-backed link strip: `role="tablist"`
on the strip, `role="tab"` with `aria-selected` and `aria-controls` on every button, `role="tabpanel"`
with `aria-labelledby` and `tabindex="0"` on every panel, one **roving `tabindex`** (only the selected tab
is `0`), and Left/Right plus Home/End moving between tabs. Hide an inactive panel with `hidden`, never by
`display: contents`, which drops the panel and its role out of the accessibility tree.

---

## 9. Layout - columns (PRINCIPLED)

**Default: single column** - the most accessible and scannable, works on every screen.

- Single column is the default on a page, and the only option in a dialog.
- **Multi-column only with justification:** 10+ related fields, settings pages, or naturally paired fields (First/Last name, City/Postcode). Keep related fields together; never split a single logical field across columns.
- **Never** create a multi-column layout that reorders the tab sequence illogically - tab order follows visual order.
- On touch (aspect-ratio ≤ 1/1), **collapse multi-column to single column** (§12).
- Keep line length readable: single column caps at `--form-col-page` (640px) on a page; in a dialog it inherits the dialog width.

### Choosing the column count

| Columns | When | Field min-width |
|---|---|---|
| **1 (default)** | Most forms; ≤ ~8 fields; mobile / tall screens | full width |
| **2** | 8+ related fields, settings pages, naturally paired fields | ~220px each |
| **3** | Dense entry of **short** fields (codes, dates, numbers) on a wide page | ~200px each |

**A dialog's decision fields (§3) stay one column** at every dialog size. There is nothing in a dialog
wide enough to column-split, and a form that wants two columns is a page form.

```html
<!-- Justified pairing (two-col); a three-col row shares three short fields (code/date/qty) in a wide container -->
<div class="field-row two-col">
  <div class="field"><label for="first">First name</label><input id="first"></div>
  <div class="field"><label for="last">Last name</label><input id="last"></div>
</div>
```

All multi-column rows **collapse to a single column** on touch / tall screens (§12).

---

## 10. Multi-step forms (wizards) and resumable drafts

When a form is long and naturally sequential (onboarding, multi-entity create, guided setup), split it
into ordered steps. A wizard is categorization spread across screens: each step is one category.

**Use a wizard when:** steps are dependent or order matters, the flow is long, or you want to reduce
perceived effort. **Don't** when a single categorized page (§8) would do.

**Every wizard saves a resumable draft (ENFORCED - see "Draft mode" below).** The advance button is
**Continue**, and it persists the step it just validated before moving on, so a closed tab, an expired
session, a dropped connection, or a switch to another machine never costs the user work already entered.

### Structure

```
●━━━━━●━━━━━○━━━━━○
Account  Profile  Billing  Review
 (done)  (active)
```

- A **step indicator** showing all steps, which are done (saved to the draft), and which is current (`aria-current="step"`).
- **One step's fields visible at a time**, at page sizing (roomy, §4).
- **Exactly two navigation buttons: Back and Continue.** Back is hidden/disabled on the first step. **Continue** validates the current step, saves the draft, then advances. On the **last step, Continue becomes the completion action** (*Create* / *Save* / *Finish*) and submits, committing the record - the **same primary button changing its label**, never a third button.
- A **draft indicator** in the footer, left of the buttons: one muted line saying the draft is saved and when ("Draft saved 14:32"). Not a toast per step.
- An optional **Review** step summarizing entries before final submit.

```html
<form class="form form-page wizard" novalidate>
  <ol class="wizard-steps" aria-label="Progress">
    <li class="step done">Account</li>
    <li class="step active" aria-current="step">Profile</li>
    <li class="step">Billing</li>
    <li class="step">Review</li>
  </ol>
  <section class="wizard-panel" data-step="profile">
    <!-- this step's fields (one category) -->
  </section>
  <!-- Footer: draft indicator, then the right-aligned button group with one solid button.
       Back hidden on step 1; Continue morphs to "Create account" + type=submit on the last step. -->
  <div class="form-actions wizard-actions">
    <p class="draft-state" data-draft-state role="status" aria-live="polite"></p>
    <button type="button" class="btn btn-secondary" data-wiz="back" hidden>Back</button>
    <button type="button" class="btn btn-primary" data-wiz="continue">Continue</button>
  </div>
</form>
```

### Rules
- **Validate the current step before advancing** - Continue stays enabled; clicking it validates with the same states/timing as §6-§7 and advances only once valid.
- **Save the step, then advance** (ENFORCED, see "Draft mode" below). Continue is an async action per [buttons.md](buttons.md) §7: disable it, show the loading affordance, guard against double-submit. A failed save does not advance and does not clear anything.
- **Never lose entered data on Back** - keep every step's values in state.
- **One category per step**; keep steps short.
- **Two buttons, paired and placed per [buttons.md](buttons.md):** the same right-aligned footer group as any form - the neutral secondary **Back** (`btn-secondary`, hidden/disabled on step 1) immediately left of the one solid **Continue** (`btn-primary`), never split to opposite edges. No separate persistent submit and **no third "Save as draft" button** - Continue is the save. On the last step Continue's label becomes Create / Save / Finish and it submits. **Exactly one solid button per step.**
- A wizard is **always a page**, at any length, per §3. Never a modal, a drawer, or an off-canvas panel.
- Show progress and let users return to completed steps without redoing them where safe.

```css
.wizard-steps { display: flex; list-style: none; padding: 0; margin: 0 0 28px; counter-reset: wizstep; }
.wizard-steps .step {
  flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px;
  font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); counter-increment: wizstep;
}
.wizard-steps .step::before {                         /* numbered circle */
  content: counter(wizstep);
  position: relative; z-index: 1;
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-surface); border: 2px solid var(--field-border);
  color: var(--color-text-muted); font-weight: 700; font-size: 0.85rem;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.wizard-steps .step:not(:first-child)::after {        /* connector to the previous step */
  content: ""; position: absolute; top: 15px; left: -50%; right: 50%;
  height: 2px; background: var(--field-border); z-index: 0;
}
.wizard-steps .step.active { color: var(--color-primary); font-weight: 700; }
.wizard-steps .step.active::before { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.wizard-steps .step.active::after { background: var(--color-primary); }
.wizard-steps .step.done { color: var(--badge-success-fg); }   /* readable success text, both themes */
.wizard-steps .step.done::before { background: var(--color-success); border-color: var(--color-success); color: #fff; }
.wizard-steps .step.done::after { background: var(--color-success); }
.wizard-panel { animation: fadeIn 0.15s ease; }

/* Footer: draft indicator on the left, button group stays right-aligned */
.wizard-actions { justify-content: space-between; align-items: center; }
.draft-state { margin: 0; font-size: 0.8125rem; color: var(--color-text-muted); }
.draft-state[data-role="danger"] { color: var(--badge-danger-fg); }   /* failed save, readable in both themes */

@media (max-aspect-ratio: 1/1) {
  .wizard-steps .step { font-size: 0.7rem; }   /* condensed labels on tall screens */
  .wizard-actions { align-items: stretch; }    /* stacks with .form-actions (§12) */
}
```

### Draft mode (ENFORCED)

A wizard's completed steps are work the user has already done. Held only in the browser, that work is
gone the moment the tab closes, the session expires, the battery dies, the connection drops, or the user
picks up a different machine - and they restart at step one. So **Continue** saves.

| Moment | What happens |
|---|---|
| **Continue** clicked | validate the step (§6-§7), save its values to the draft, then advance and mark the step done |
| Save succeeded | the footer reads "Draft saved 14:32", announced politely through the live region |
| Save failed | stay on the step, keep every entered value, show the failure in the footer in the danger role, return Continue to rest so it can be retried |
| **Back** clicked | move back with all values intact; nothing new is saved, and nothing is lost |
| Reopened later, anywhere | resume at the saved step, earlier steps restored and marked done |
| Last step submitted | the draft is committed as the record and stops being a draft |
| Discarded | explicit and confirm-guarded, naming what is lost |

Saving on every Continue is the **floor**. Autosave on top of it (on an interval, on blur, on close) is
welcome and stays the project's call; it never relaxes the step validation that gates advancing.

#### Where the draft lives (ask, never decide)

Server-side, owned by the user who entered it, carrying the step reached. A draft kept only in
`localStorage` / `sessionStorage` / IndexedDB **does not satisfy this** - it dies with the browser
profile and the device, which is half of what the pattern exists for. Browser storage is a cache in
front of the server copy, never the copy.

The storage shape is the developer's decision, recorded in `PROJECT-CONTEXT.md` (the contract is in the
Step-By-Step Form Drafts section of `.claude/rules/ui-ux-quality.md`). Two shapes work:

| Shape | What it means | Trade-off |
|---|---|---|
| **Separate draft table** | one row per in-flight draft holding the entered values; the real record is created only on completion | the live table keeps its constraints strict, and partial values are the draft table's own problem |
| **Same table, draft state** | the record exists from step one carrying a `draft` state, promoted on completion | one row and one id, so resume is trivial - but every required column has to be nullable until completion, which weakens them for committed rows too, and every query, count, export, and report has to filter the state |

Either way, **a draft is not a record.** It may sit as a row in its own owner's list view with the `Draft`
badge and a `Continue` action, and it stays out of the default-filtered result and its counts until the
owner asks for drafts, and out of every other list, export, metric tile, and report. With the drafts
filter on, those rows count in that filtered total like any other match ([tables.md](tables.md) §4a). The draft state is a codified value, never free text, and the table goes through the Schema
MCP workflow and the semantic-data-model rules like any other table.

#### Resuming

- The draft is reachable from a standing surface: the entity list carrying a `Draft` badge in the **neutral** status role with a `Continue` row action, or a dedicated drafts view. Never a URL the user has to remember.
- Re-entering the create flow offers the saved draft instead of opening blank.
- Restoring is a load: show the wizard skeleton per [empty & loading](empty-and-loading-state.md), then open the saved step with the earlier ones restored and marked done.
- State what was restored in one muted line above the fields ("Resumed from step 2 of 4, saved 14:32 yesterday."), and offer a confirm-guarded **Discard draft** for starting clean.
- One resumable draft per user per flow by default. Several in flight only when the developer confirms that, and then the list is the resume surface.
- A draft belongs to the user who entered it. The per-record policy and the list query both scope it to its owner, so another user cannot resume or read it by guessing an id.
- On completion the draft is closed out in the same transaction that commits the record, so the finished record shows once and the draft leaves the drafts surface.

#### What a draft never holds

Never persist a credential, API key, token, or password into a draft, per
`.claude/rules/secret-handling.md`. Leave those fields out of the saved payload and re-collect them on
resume. A draft is also never a way around §18: a restored draft is not a passed connection test.

### The two-button navigation model

Only **Back** and **Continue** appear, in the same right-aligned footer group as any form (Back
immediately left of the primary, not split to edges). The primary button is **one element** throughout;
on the last step its label and behaviour change:

```
Step 1:        Draft saved 14:32              [ Continue → ]   ← Back hidden on step 1
Step 2:        Draft saved 14:35   [ ← Back ] [ Continue → ]
Last:          Draft saved 14:41   [ ← Back ] [ Create account ]   ← SAME button, new label, now submits
```

```js
function renderWizardNav(current, lastIndex) {
  back.hidden = current === 0;                                  // no Back on step 1
  const onLast = current === lastIndex;
  advance.textContent = onLast ? 'Create account' : 'Continue'; // or 'Save' / 'Finish'
  advance.type = onLast ? 'submit' : 'button';                  // becomes the submit on the last step
}

// Continue = validate this step, save the draft, then advance. Never advance on a failed save.
advance.addEventListener('click', async (e) => {
  if (!validateStep(current)) { e.preventDefault(); return; }   // gates the advance AND the final submit
  if (current === lastIndex) return;                            // last step: type="submit", form submits
  e.preventDefault();

  setBusy(advance, 'Saving...');                                // buttons.md §7 async contract
  try {
    const saved = await saveDraft({ step: current, values: stepValues(current) });   // server-side
    draftState.textContent = `Draft saved ${saved.at}`;
    draftState.removeAttribute('data-role');
    current++; renderStep(); renderWizardNav(current, lastIndex);
  } catch (err) {
    draftState.dataset.role = 'danger';                         // stay on the step, keep every value
    draftState.textContent = 'Could not save this step. Check your connection and try again.';
  } finally {
    clearBusy(advance);                                         // never leave it stuck in loading
  }
});
```

### There is no wizard in a modal

A step-by-step flow is never hosted in a dialog, whatever its length. Two steps is not an exception. A
wizard needs room, a route to resume into, a breadcrumb, and a draft it can save against, and a popup
gives it none of those. Put it on a page, per §3.

### All form cases at a glance

Sizing follows the host (page = roomy, dialog fields = compact, §4); validation is identical everywhere
(§6-§7); columns collapse to 1 on touch/tall screens; required fields always sit in the first category /
tab / step.

| # | Shape | Categorization | Columns | Navigation / submit |
|---|---|---|---|---|
| 1 | **Page form** | None (≤ ~8 fields) | 1 | Single submit (Save / Create) |
| 2 | Page form | Section headings | 1-3 | Single submit |
| 3 | Page form | **Tabs** | 1-3 per tab | Single submit (validates across tabs) |
| 4 | **Multi-step, always a page** | One category **per step** | 1-3 per step | **Back / Continue**, saving a draft per step; Continue becomes Create/Save/Finish on the last step |
| 5 | **Fields in a decision dialog** | None (≤ ~3 fields that *are* the decision) | 1 | Dialog footer confirm |

---

## 11. Labels, help text, placeholders & tooltips

### Labels & required state
- **Every field has a visible `<label>`** associated via `for`/`id`. Placeholders are **not** labels.
- **Mark required fields** with `*` in the label and `aria-required="true"`; or mark optional fields "(optional)" - pick one convention per form.
- **Label by outcome** and keep copy consistent with the flow and the submit button.

### Help text
- **Persistent guidance** (format, why you need it, constraints) sits under the label/control as muted **help text** (`.help`) and stays visible.
- **Help text before the error.** On failure the error joins/replaces it in the message slot (§6); reserve the slot.
- Use help text - **not** a placeholder or tooltip - for anything a user *must* know to fill the field correctly.

### Placeholder text
A placeholder is a **faint example of a valid value**, shown only while the field is empty. It vanishes
on input, so it can never carry meaning the user still needs.

**Use it to** show the **expected format** (`name@company.com`, `+1 555 0100`, `DD/MM/YYYY`, `SG-ENT-001`)
or hint **what kind of value** goes in (`Search contacts...`, `0.00`).

**Never** use it to replace the label, hold instructions/constraints ("Must be 8+ characters" is help
text), or state required-ness / validation rules.

**Rules:** keep it short and literal; no trailing punctuation except the ellipsis on search/filter
inputs; make it visibly dimmer than real input but legible (muted); **selects take no placeholder** -
use a disabled, non-selectable first option ("Select a stage..."); many short fields need no placeholder
at all.

| Field | Good placeholder | Why |
|---|---|---|
| Email | `name@company.com` | Shows the format |
| Phone | `+1 555 0100` | Format + country hint |
| Date (typed) | `DD/MM/YYYY` | Clarifies field order |
| Amount | `0.00` | Shows decimals |
| Search | `Search contacts...` | States the action |
| First name | *(none)* | The label is enough |

### Tooltips - only when necessary
Most fields don't need a tooltip; reach for visible help text first. A tooltip carries **secondary,
contextual** detail that would clutter the form if always shown.

**Use a tooltip (info `ⓘ` next to the label) for:** a definition of jargon/term of art; **why** a
non-obvious field is collected, or **where to find** a value; **explaining a disabled control** (pair
the disabled state with a tooltip/text saying what unlocks it, [buttons.md](buttons.md) §6).

**Never** put critical or required information only in a tooltip - hover/focus content is easy to miss and awkward on touch.

**Rules:** the trigger is a **focusable** `<button>` with an `aria-label`; link the content via
`aria-describedby`; it opens on **focus and hover** (not hover-only) and dismisses on `Esc`/blur (touch
users tap to toggle); keep it to a sentence or two.

```html
<div class="field">
  <label for="stage">
    Lifecycle stage
    <button type="button" class="field-info" aria-label="What is a lifecycle stage?"
            aria-describedby="stage-tip">ⓘ</button>
  </label>
  <select id="stage" aria-describedby="stage-tip"> <!-- options --> </select>
  <span class="help" id="stage-tip" role="tooltip">Where this contact sits in your funnel: Prospect → Lead → Customer.</span>
</div>
```

---

## 12. Full CSS reference (copy-paste ready)

Pairs with the button CSS ([buttons.md](buttons.md) §10). The two form types are driven by
`.form-page` / `.form-modal` wrapper classes that swap the size tokens (`.form-modal` is dialog-field
sizing only, §3).

```css
/* ===== Form wrappers select the size set ===== */
.form { display: flex; flex-direction: column; }
.form-page  { --fh: var(--field-height-page);  --ff: var(--field-font-page);  --fp: var(--field-pad-page);  --fg: var(--field-gap-page);  max-width: var(--form-col-page); }
.form-modal { --fh: var(--field-height-modal); --ff: var(--field-font-modal); --fp: var(--field-pad-modal); --fg: var(--field-gap-modal); }

/* ===== Field block ===== */
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: var(--fg); }
.field > label {
  font-weight: var(--field-label-weight);
  font-size: 0.75rem;                     /* 12px - labels, both contexts */
  color: var(--color-text);
}
.field .req { color: var(--badge-danger-fg); margin-left: 2px; }   /* readable danger, both themes */

/* ===== Controls ===== */
.field input,
.field select,
.field textarea {
  width: 100%;
  min-height: var(--fh);
  padding: var(--fp);
  font: inherit;
  font-size: var(--ff);
  color: var(--color-text);
  background: var(--color-surface);
  border: 1px solid var(--field-border);
  border-radius: var(--field-radius);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.field textarea { min-height: calc(var(--fh) * 2); resize: vertical; }

.field input:hover,
.field select:hover,
.field textarea:hover { border-color: var(--field-border-hover); }

.field input:focus-visible,
.field select:focus-visible,
.field textarea:focus-visible {
  outline: none;
  border-color: var(--field-focus-ring);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--field-focus-ring) 30%, transparent);
}

/* ===== Disabled / read-only ===== */
.field input:disabled,
.field select:disabled,
.field textarea:disabled { opacity: 0.5; cursor: not-allowed; }
.field input[readonly],
.field textarea[readonly] { background: var(--color-background); }

/* ===== Help + message slot ===== */
.field .help { font-size: 0.8125rem; color: var(--color-text-muted); }
.field .msg  { font-size: 11.5px; min-height: 1.1em; display: flex; align-items: center; gap: 6px; }

/* ===== Tooltip trigger (info icon by the label, §11) ===== */
.field-info {
  border: none; background: none; cursor: help; padding: 0 0 0 4px;
  color: var(--color-text-muted); font-size: 0.85em; line-height: 1;
}
.field-info:focus-visible { outline: 2px solid var(--field-focus-ring); outline-offset: 2px; border-radius: 50%; }

/* ===== Validation state (error-only; valid fields return to rest) =====
   Error text + border use --badge-danger-fg (not raw #991547) so they stay legible in dark mode. */
.field.is-error input,
.field.is-error select,
.field.is-error textarea { border-color: var(--badge-danger-fg); }
.field.is-error input:focus-visible { box-shadow: 0 0 0 3px color-mix(in srgb, var(--badge-danger-fg) 25%, transparent); }
.field.is-error .msg { color: var(--badge-danger-fg); }

/* ===== Grouping ===== */
.field-group { border: none; padding: 0; margin: 0 0 var(--spacing-xl, 32px); }
.field-group-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-primary);
  padding-bottom: 8px;
  margin-bottom: 16px;
  border-bottom: 1px solid color-mix(in srgb, var(--color-text) 10%, transparent);
  width: 100%;
}

/* ===== Columns (PRINCIPLED) ===== */
.field-row { display: grid; gap: 16px; }
.field-row.two-col   { grid-template-columns: 1fr 1fr; }
.field-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
.field-row .field { margin-bottom: var(--fg); }
.field-row .span-all { grid-column: 1 / -1; }   /* long field spans the row */

/* ===== Categorization: responsive field grid + section headings + in-page form tabs ===== */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); column-gap: 16px; }
.form-tabs { display: flex; flex-wrap: wrap; gap: 0; border-bottom: 2px solid var(--field-border); margin-bottom: 20px; }
.form-tab {
  border: none; background: none;          /* strip native <button> chrome */
  padding: 9px 18px; font: inherit; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);
  cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
}
.form-tab.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
.form-tab:hover:not(.active) { color: var(--color-text); }
.form-tab:focus-visible { outline: 2px solid var(--field-focus-ring); outline-offset: -2px; }
/* Each panel carries .form-grid, so it is its own field grid and keeps role="tabpanel" real.
   [hidden] needs the explicit rule to beat .form-grid's display: grid. */
.form-tab-panel[hidden] { display: none; }
.form-section-heading {
  grid-column: 1 / -1;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em;
  color: var(--color-text-muted); margin: 12px 0 2px; padding-bottom: 6px;
  border-bottom: 1px solid var(--field-border);
}

/* ===== Actions ===== */
/* Form-level error: one alert at the foot, for what no field can say (§6). */
.form-alert {
  display: flex; align-items: flex-start; gap: var(--spacing-sm);
  padding: var(--spacing-sm) var(--spacing-md);
  margin-bottom: var(--spacing-md);
  border: 1px solid color-mix(in srgb, var(--color-danger) 40%, transparent);
  border-radius: var(--field-radius, 8px);
  background: var(--badge-danger-bg); color: var(--badge-danger-fg);
  font-size: var(--text-small);
}
.form-alert[hidden] { display: none; }

.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }

/* ===== Responsive (mobile-first; width and/or aspect-ratio breakpoints) ===== */
@media (max-aspect-ratio: 1/1) {
  .form-page, .form-modal { --fh: 44px; }      /* touch target */
  .field-row.two-col { grid-template-columns: 1fr; }  /* collapse to single column */
  .field-row.three-col { grid-template-columns: 1fr; }
  .form-page { max-width: 100%; }
  .form-actions { flex-direction: column-reverse; }
  .form-actions .btn { width: 100%; }
}
```

---

## 13. Full JS reference (validation contract)

Dependency-free implementation of §7 (blur validation, error below field, submit-click validation that
focuses the first invalid field). Frameworks should use their own form library - the contract is identical.

```js
function initForm(form) {
  const fields = [...form.querySelectorAll('.field')];

  function validateField(field, { live = false } = {}) {
    const input = field.querySelector('input, select, textarea');
    const msg = field.querySelector('.msg');
    let error = '';

    if (input.required && !input.value.trim()) error = 'This field is required.';
    else if (input.value && !input.checkValidity()) error = input.validationMessage;

    field.classList.toggle('is-error', !!error);   // error-only - no is-valid state
    input.setAttribute('aria-invalid', error ? 'true' : 'false');
    if (msg) msg.textContent = error ? '⚠ ' + error : '';

    // Once errored, re-validate live so it clears as soon as it's fixed (§7.4)
    if (error && !live) {
      input.addEventListener('input', () => validateField(field, { live: true }), { once: false });
    }
    return !error;
  }

  fields.forEach(field => {
    const input = field.querySelector('input, select, textarea');
    input.addEventListener('blur', () => validateField(field));   // §7.1
  });

  // §7.3 - submit stays enabled; the click runs full validation
  form.addEventListener('submit', (e) => {
    let firstInvalid = null;
    fields.forEach(field => { if (!validateField(field) && !firstInvalid) firstInvalid = field; });
    if (firstInvalid) {                                   // §7.5
      e.preventDefault();
      const bad = fields.filter(f => f.classList.contains('is-error')).length;
      const input = firstInvalid.querySelector('input, select, textarea');
      input.focus();
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Announced once: the inline messages carry the detail, the toast says the submit was blocked.
      // No summary card (§6). toasts.md owns showToast.
      showToast('error', `${bad} field${bad > 1 ? 's need' : ' needs'} attention.`);
    }
  });
}

document.querySelectorAll('.form').forEach(initForm);
```

---

## 14. Accessibility checklist

- [ ] Every control has a **visible, associated `<label>`** (`for`/`id`).
- [ ] Required fields marked with `*` **and** `aria-required="true"`.
- [ ] Errors use `aria-invalid="true"` and `aria-describedby` → the message id; message sits **below** the field.
- [ ] Don't signal validity by color alone - pair with icon + text.
- [ ] Visible **`:focus-visible`** ring on every control.
- [ ] Help/placeholder text is not the only source of the field's meaning.
- [ ] Tab order follows visual order (especially in multi-column).
- [ ] Touch targets ≥ 44 × 44px on touch (aspect-ratio ≤ 1/1).
- [ ] Don't block paste (passwords, codes); allow native autofill (`autocomplete`).
- [ ] A blocked submit focuses and scrolls to the first invalid field and raises one error toast, with no summary card repeating the inline messages.
- [ ] In-page form tabs are a complete widget: `role="tablist"` / `role="tab"` (`aria-selected`, `aria-controls`) / `role="tabpanel"` (`aria-labelledby`, `tabindex="0"`), one roving `tabindex`, Left/Right/Home/End, inactive panels `hidden` (§8, [tabs.md](tabs.md) §8.2).

---

## 15. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Use `form-page` sizing on pages, `form-modal` sizing for a dialog's decision fields | One fixed field size everywhere | The two contexts have different space |
| Keep validation states identical in both contexts | Style a dialog's field errors differently from a page's | Consistency is how users learn the UI |
| Validate on blur and show the error **below** the field | Wait until submit to reveal the first error | Late errors force rework |
| Re-validate on input once a field has errored | Make users blur again to clear a fixed error | Instant clearing rewards the fix |
| Group many fields into labelled categories | Present 15 inputs as one undivided wall | Grouping makes long forms manageable |
| Keep a visible `<label>` on every field | Use a placeholder as the label | Placeholders vanish on typing; fail a11y |
| Default to a single column | Force multi-column for unrelated fields | Single column is most scannable/accessible |
| Pair error color with icon + text | Signal errors with red border only | Color-blind users miss color-only state |
| Reserve space for the message | Let the layout jump when an error appears | Stable layout is calmer |
| Build every create / edit / multi-step / settings form on a page | Open a form in a modal, however small | Data entry belongs in the app's UI, not over it (§3) |
| Let a dialog carry only the fields that are the decision | Grow a confirmation into a record editor | A fourth field means it is a form, so it is a page |
| Save the step on **Continue** before advancing | Hold a wizard's entered steps in the browser only | A closed tab, a dead session, or another machine loses the lot (§10) |

---

## 16. Rules for the AI assistant

**ALWAYS**
- Build data entry on a page (§3): `form-page` on its own route or as a form region on the current page. Use a **multi-step wizard** (§10) for long, sequential flows, also page-hosted. Reserve `form-modal` sizing for the few fields a decision dialog carries.
- Apply the matching field sizes (§4): page = 32px/16px, dialog fields = 32px/15px - one size set per form.
- Implement the same validation states (§6) and timing (§7) in both contexts: blur validation, error below field, submit enabled - on click validate and focus the first invalid field; in a wizard, validate each step before advancing.
- Give every field a visible, associated label; mark required state accessibly.
- **Group many fields by category** (§8): section headings/fieldsets, in-page tabs, or steps (wizard). Default to a single column; use 2-3 columns only with justification (§9).
- In a **multi-step form** use **exactly two buttons** (§10): **Back** (hidden/disabled on step 1) and **Continue**; on the last step Continue's label becomes Create / Save / Finish and it submits - never add a separate persistent submit and never a third "Save as draft". Pair and place them per [buttons.md](buttons.md): a right-aligned footer group with **Back** (`btn-secondary`) immediately left of the one solid **Continue** (`btn-primary`).
- Save the draft on every **Continue** (§10): validate the step, persist it server-side, then advance; on a failed save stay on the step with every value intact. Resume a returning user at the saved step instead of a blank first step.
- **Choose the control by the data** (§5): radios for ≤ 5 exclusive options, select/combobox beyond; checkbox on submit vs switch for instant settings; textarea only for true multi-line.
- Treat placeholders as faint examples (format hints), never labels or instructions; put must-know detail in help text, reserve tooltips for secondary/contextual info (§11).
- Use design tokens; respect mobile-first responsive rules and 44px touch targets.

**NEVER**
- Use a placeholder as the only label, or to carry instructions/constraints.
- Put critical or required information only in a tooltip.
- Signal validity by color alone.
- Reveal the first error only at submit time.
- Put a create, edit, multi-step, or settings form in a modal, a drawer, or an off-canvas panel - data entry is page-hosted (§3).
- Let a decision dialog grow past ~3 fields, or give it categories, sections, or tabs.
- Advance a wizard step without saving the draft, keep a draft in browser storage only, or persist a credential into one (§10).
- Remove the focus ring without an equally visible replacement.
- Hardcode colors or invent shades outside the palette.

---

## 17. Quick decision guide

```
Which form shape?
├─ Creating or editing a record ................. form-page, own route or a region on this page
│                                                 (32px fields, 16px, max 640px) - NEVER a modal
├─ Long & sequential / dependent steps .......... multi-step wizard on a page
│                                                 (validate each step, save the draft, §10)
└─ ~3 fields that ARE a decision ................ form-modal sizing inside the decision dialog
                                                  (32px fields, 15px, one column, no categories)

How many fields?
├─ A handful, one obvious set ................... single ungrouped column
└─ Many (~8+) or clear categories .............. group → headings/fieldsets · in-page tabs · steps (wizard)

Columns?
├─ Default ...................................... 1 column
├─ 8+ related / settings / paired ............... 2 columns (justify)
└─ Dense short fields, wide page ................ 3 columns (justify)
      dialog fields: always 1 · all multi-column collapses to 1 on touch

Validation (same for ALL shapes):
├─ On blur ...................................... validate + show error BELOW the field
├─ After it errors ............................. re-validate on input, clear when fixed
├─ Submit button ............................... stays enabled - clicking it validates the form (or step)
└─ On submit if invalid ........................ focus + scroll to first invalid field

Multi-step nav (always a page):
└─ Two buttons only ............................ Back (hidden/disabled on step 1) + Continue
                                                  Continue: validate → save draft → advance
                                                  last step: Continue becomes Create/Save/Finish (submit)

Every field: visible <label> · required marked accessibly · color never the only signal · :focus-visible ring
```

---

## 18. Connection / integration configuration - test before save (ENFORCED)

Applies to the Settings / config archetype whenever the fields configure a connection to an external
system: API credentials, email/SMTP, a third-party app integration, a webhook, or any similar
outbound-connection config. Cross-ref: `.claude/rules/ui-ux-quality.md` Page Archetypes, Settings / config.

**The rule:** the form can never be saved without a successful live connection test on the current
field values. There is no path that skips the test.

### Footer button contract

| State | Buttons shown | Looks |
|---|---|---|
| Untested (first load, or after any tested field is edited) | `Reset` + `Test Configuration` | Both `btn btn-secondary`; **no solid button in the footer** |
| Test in progress | `Reset` (disabled) + `Test Configuration` (loading) | The same two looks; only the loading affordance changes, per [buttons.md](buttons.md) §7 |
| Test succeeded, values unchanged since | `Reset` + `Test Configuration` + `Save` | `Reset` and `Test Configuration` unchanged; `Save` is the one solid `btn btn-primary` |
| Test failed | `Reset` + `Test Configuration` | Same as Untested; inline failure detail shown, no `Save` |

**Until the test passes, the footer carries no solid button at all** - and that is the point: nothing in
it saves anything yet. `Reset` and `Test Configuration` are both `btn btn-secondary` in every state, and
`Save` is the footer's only solid action, appearing when a passing test earns it. A control's look never
changes with its state, so neither of the two secondary buttons is ever promoted or demoted as the test
runs, passes, or fails ([buttons.md](buttons.md) §4).

- **`Reset`** restores the last-saved values, or clears the form on first setup (no prior save). It discards unsaved edits and any test result. The neutral secondary look (`btn btn-secondary`) - it is a routine exit, not destructive ([buttons.md](buttons.md) §4, The two looks), the same look `Cancel` and `Back` carry on every screen.
- **`Test Configuration`** runs the real connection check as the async action from [buttons.md](buttons.md) §7: on click, disable and show the loading affordance, guard against double-submit, keep the button's width stable. It is `btn btn-secondary` in every state, because proving a connection is not saving it ([buttons.md](buttons.md) §4); only the loading affordance changes, never the look. On completion, report the result two ways: a toast (success/error) per the toasts guide, **and** an inline connection-status indicator next to the fields (a status badge - success/danger role, never color alone) that persists after the toast dismisses, so the last result stays visible.
- **`Save`** is never rendered (or stays disabled with an explanatory affordance, per the same disabled-state rule as any blocked primary action) until the most recent `Test Configuration` against the *current, unedited* field values returned success. `Save` never appears as a way to bypass an untested or failed configuration - there is no "save anyway" or force-save affordance.
- **Editing invalidates the test.** The moment any tested field changes after a successful test, `Save` hides/disables again and the inline status indicator clears back to untested, so the footer is back to two secondary buttons and no solid one. A fresh successful test is required before `Save` reappears.

### Example

```html
<form class="form form-page" data-connection-config>
  <fieldset class="field-group">
    <legend class="field-group-title">Connection details</legend>
    <!-- API key / SMTP host / webhook URL fields ... -->
  </fieldset>

  <div class="conn-status" data-conn-status hidden>
    <!-- success or danger status badge + message, filled in after a test -->
  </div>

  <div class="form-actions">
    <button type="button" class="btn btn-secondary" data-conn="reset">Reset</button>
    <button type="button" class="btn btn-secondary" data-conn="test">Test Configuration</button>
    <button type="submit" class="btn btn-primary" data-conn="save" hidden>Save</button>
  </div>
</form>
```

```js
function initConnectionConfig(form) {
  const testBtn = form.querySelector('[data-conn="test"]');
  const saveBtn = form.querySelector('[data-conn="save"]');
  const status = form.querySelector('[data-conn-status]');
  let lastTestedSnapshot = null;

  function snapshot() {
    return JSON.stringify(Object.fromEntries(new FormData(form)));
  }

  function invalidateTest() {
    saveBtn.hidden = true;      // the footer's only solid button goes with it; the two others never change look
  }

  form.addEventListener('input', () => {
    if (snapshot() !== lastTestedSnapshot) invalidateTest();   // any edit after a pass invalidates it
  });

  testBtn.addEventListener('click', async () => {
    testBtn.disabled = true;
    testBtn.setAttribute('aria-busy', 'true');
    const priorLabel = testBtn.textContent;
    testBtn.textContent = 'Testing...';
    try {
      const ok = await runConnectionTest(new FormData(form));   // the real, project-specific check
      status.hidden = false;
      status.dataset.role = ok ? 'success' : 'danger';
      status.textContent = ok ? 'Connection succeeded.' : 'Connection failed.';
      if (ok) {
        lastTestedSnapshot = snapshot();
        saveBtn.hidden = false;   // Save appears; the test button keeps its one look
      } else {
        invalidateTest();
      }
    } finally {
      testBtn.disabled = false;
      testBtn.removeAttribute('aria-busy');
      testBtn.textContent = priorLabel;
    }
  });
}
```

### Rules for the AI assistant

**ALWAYS**
- Treat this as mandatory, not a style choice: no `Save` (or equivalent submit) on a connection/integration config form until a `Test Configuration` on the current values has succeeded.
- Re-invalidate a passed test the moment a tested field is edited.
- Report both a toast and a persistent inline status indicator for the test result.

**NEVER**
- Add a "save anyway", "skip test", or force-save affordance for a connection/integration config form.
- Let `Save` remain visible/enabled after a field changes post-test.
- Treat a boolean toggle or non-connection setting on the same Settings/config screen as needing this pattern - it applies only to the fields that configure an outbound connection.
