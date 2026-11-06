# Modal & Dialog UI/UX Design Guide

Part of the `ui-ux-design` skill. Values here (palette, fonts, tokens) are approved CLaaS2SaaS
constants, not per-project choices; the canonical source is `design-tokens.md` and `../assets/`.
Responsive is mobile-first; every token is defined in both the light and dark theme with a
Light/Dark/System switcher.

> **TL;DR for the AI**
> 1. A modal **interrupts** - use it only for a decision that must be answered now. Otherwise use a page or a toast.
> 2. **Data entry is never a modal (ENFORCED, §2):** a create / edit / multi-step / settings form is page-hosted, per [forms.md](forms.md) §3. A dialog carries at most ~3 fields when those fields *are* the decision.
> 3. **Behavior is ENFORCED** (§6): ESC closes, backdrop click closes, focus is **trapped**, and focus **returns to the trigger** on close.
> 4. **Pick size by content** (§5): Small 420px (confirmations, and the ~3 decision fields), Medium 600px (a longer explanation or result), Large 900px (content that needs the room, such as a wide table preview).
> 5. **Destructive confirmations** (§7) name the exact thing, use a **Danger** button, and never default-focus the destructive action.
> 6. **Discard confirmations** (§8) only appear when there are **unsaved changes**.
> 7. Always ship **labelled** dialogs (`role="dialog"` + `aria-modal="true"` + `aria-labelledby`), a scroll lock, and a visible close affordance.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [When to use a modal (and when not to)](#2-when-to-use-a-modal-and-when-not-to)
3. [Anatomy of a modal](#3-anatomy-of-a-modal)
4. [Modal types](#4-modal-types)
5. [Sizes - principled, driven by content](#5-sizes--principled-driven-by-content)
6. [Behavior rules (ENFORCED)](#6-behavior-rules-enforced)
7. [Delete / destructive confirmations](#7-delete--destructive-confirmations)
8. [Discard / unsaved-changes confirmations](#8-discard--unsaved-changes-confirmations)
9. [Focus management & accessibility](#9-focus-management--accessibility)
10. [Copy / labels](#10-copy--labels)
11. [Full CSS reference (copy-paste ready)](#11-full-css-reference-copy-paste-ready)
12. [Full JS reference (behavior contract)](#12-full-js-reference-behavior-contract)
13. [Accessibility checklist](#13-accessibility-checklist)
14. [Do's and Don'ts](#14-dos-and-donts)
15. [Rules for the AI assistant](#15-rules-for-the-ai-assistant)
16. [Quick decision guide](#16-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Modals consume the brand palette and spacing scale; introduce no colors outside it. The complete
two-theme set is canonical in `design-tokens.md` (dark-theme values in the comments below). Buttons
inside a modal follow [buttons.md](buttons.md).

```css
:root {
    /* Brand palette (approved) */
    --color-primary: #193E6B;           /* Midnight Blue */
    --color-accent: #B3A125;            /* Green Gold */
    --color-secondary-violet: #7F3F98;  /* Cadmium Violet */
    --color-secondary-blue: #448E9D;    /* Jelly Bean Blue */
    --color-secondary-sunray: #E9AC53;  /* Sunray */
    --color-success: #5F8025;           /* Avocado Green */
    --color-danger: #991547;            /* Violet-Red */
    --color-background: #E8DFD0;        /* canvas - dark theme: #1A2E46 */
    --color-surface: #FFFFFF;           /* card - dark theme: #253E5D */
    --color-text: #1E2E42;              /* dark theme: #E9EFF6 */
    --color-text-muted: #4D5E75;        /* dark theme: #A3B2C5 */

    /* Modal tokens */
    --modal-backdrop: rgba(25, 30, 40, 0.55);
    --modal-backdrop-blur: 4px;         /* the backdrop dims AND blurs the page behind */
    --modal-radius: 14px;
    --modal-shadow: 0 8px 24px rgba(15, 25, 45, 0.14);  /* overlay shadow - dark theme: 0 10px 28px rgba(0,0,0,0.45) */
    --modal-padding: 24px;
    --modal-gap: 16px;

    /* Sizes (max-width) */
    --modal-sm: 420px;   /* confirmations, decision fields (strict minimum, default) */
    --modal-md: 600px;   /* a longer explanation or result */
    --modal-lg: 900px;   /* read-only content that needs the room */
    --modal-xl: min(1200px, 95vw); /* by exception: content that needs > 900px */
    --modal-z: 1000;     /* toasts stack above at 1100 (toasts.md) */
}
```

---

## 2. When to use a modal (and when not to)

A modal **interrupts the user and blocks the page behind it** - the whole point and the whole risk.
Use it sparingly.

| Use a modal when... | Use something else when... |
|---|---|
| The action needs a **deliberate decision now** (delete, discard, confirm payment) | The content is long or browsable → use a **page / route** |
| The decision itself needs an input: a typed confirm word, a reason, a new date (**~3 fields, max**) | The user is **creating or editing a record** → a **page form**, always ([forms.md](forms.md) §3) |
| You must **block progress** until the user responds | The flow has **steps** → a page-hosted wizard, at any length |
| A short result or explanation must be acknowledged | It's a non-blocking notification → use a **toast** (button guide §7) |

**Rule:** data entry never goes in a modal. No create form, edit form, multi-step form, or settings form,
however few fields it has, and a drawer or off-canvas panel is not a loophole. A dialog reaching for a
fourth field, a section heading, or a tab strip has become a form, and a form is a page.

**Rule:** never open a modal from within a modal. If a flow needs that, it belongs on a page.

---

## 3. Anatomy of a modal

A modal is **a backdrop** + **a labelled dialog surface** + **a header** + **a body** + **an action footer**.

```
┌───────────────────────────────────────────────┐  ← backdrop (click closes)
│   ┌─────────────────────────────────────┐     │
│   │  Title (aria-labelledby)     [ × ]  │ ← title left · close right (same row)
│   ├─────────────────────────────────────┤     │
│   │  Body - message / decision field    │ ← body (scrolls if tall)
│   ├─────────────────────────────────────┤     │
│   │            [ Cancel ]  [ Confirm ]   │ ← footer: one solid action
│   └─────────────────────────────────────┘     │
└───────────────────────────────────────────────┘
```

- **Backdrop:** dims and blocks the page; clicking it closes the modal (§6).
- **Dialog surface:** `role="dialog"`, `aria-modal="true"`, `aria-labelledby` → the title id.
- **Header:** the **title on the left** and a close (`×`) button on the **right**, on the **same row** and vertically aligned; the close button is `icon-btn` chrome rather than a `.btn` (button guide §4.1) and carries `aria-label="Close"`.
- **Body:** the message, and at most the ~3 fields that are the decision (§2). Scrolls internally when content exceeds the viewport - the header/footer stay put.
- **Footer:** action buttons. **At most one** solid button, the dialog's one main action; every other labelled action, Cancel included, is the neutral `btn-secondary` (button guide §4). A dialog with nothing to decide, only to close (a content preview, a plain "Close"), has no main action, so it carries no solid button.

---

## 4. Modal types

| Type | Purpose | Default size | The one solid action |
|---|---|---|---|
| **Confirmation** | A yes/no decision before a single action | Small (420px) | Matches the action (Primary or **Danger**) |
| **Destructive confirmation** | Confirm an **irreversible** action (delete, remove, revoke) | Small (420px) | **Danger** |
| **Discard confirmation** | "You have unsaved changes" guard on exit | Small (420px) | **Danger** ("Discard") |
| **Decision with an input** | ~3 fields that *are* the decision: a typed confirm word, a reason, a new date | Small (420px) | Matches the action |
| **Result / explanation** | An outcome the user must acknowledge, too long for a toast | Small or Medium | Primary ("Got it"); a plain "Close" is `btn-secondary`, so that dialog has no solid action |
| **Content preview** | Read-only content that needs the room (a wide table, side-by-side compare) | Large (900px) | None - nothing commits, so "Close" is `btn-secondary` (button guide §4) |

> There is no form modal. A create, edit, multi-step, or settings form is page-hosted ([forms.md](forms.md) §3), so nothing in this table is a record editor.
>
> Confirmation and discard modals are the two most common and the easiest to get wrong - §7 and §8 cover them in full.

---

## 5. Sizes - principled, driven by content

**PRINCIPLED:** pick the size from what's inside, not from how important it feels.

| Size | Token | Max-width | Use for |
|---|---|---|---|
| **Small** *(default)* | `modal-sm` | **420px** | Confirmations, discard prompts, and the ~3 fields that are a decision |
| **Medium** | `modal-md` | **600px** | A longer explanation or result that needs reading room |
| **Large** | `modal-lg` | **900px** | Read-only content that needs the room (a wide table, side-by-side compare) |
| **Extra-large** *(by exception)* | `modal-xl` | **> 900px** | Content that genuinely needs more room (wide tables, side-by-side views) |

**Rules**
- **420px is a strict minimum.** Never make a modal narrower than `modal-sm` - even a one-line confirmation uses 420px.
- **For content that fits within 900px:** 420px (confirmations and decision fields), 600px (a longer explanation), 900px (content needing the room). Pick the smallest size that comfortably holds the content.
- **900px is not a hard ceiling.** If read-only content legitimately needs more (a wide table, side-by-side panels), the modal **may exceed 900px** - still capped so it never becomes full-bleed on large screens (e.g. `max-width: min(1200px, 95vw)`). An editing surface is not on that list: a rich editor is data entry, so it is a page (§2).
- The width is a **max-width**; on narrow screens the modal becomes near-full-width with a safe margin (§11).
- Height is **content-driven** with a cap (`max-height: 90vh`); the **body scrolls**, never the whole dialog - keep header and footer pinned.
- A dialog reaching for more width because of its *fields* has become a form, and a form is a page (§2).

```css
/* Exceeding 900px when content requires it - still capped, never full-bleed */
.modal-xl { max-width: min(1200px, 95vw); }
```

---

## 6. Behavior rules (ENFORCED)

Non-negotiable. Every modal implements **all four**.

| Rule | Behavior | Why |
|---|---|---|
| **ESC key closes** | Pressing `Escape` dismisses the modal (treated as Cancel) | Universal, expected escape hatch |
| **Backdrop click closes** | Clicking the dimmed area outside the dialog dismisses it | Predictable, fast dismissal |
| **Focus trapped** | `Tab`/`Shift+Tab` cycle **only** through focusable elements inside the modal | Keyboard users can't get lost behind the modal |
| **Return focus on close** | On close, focus returns to the **element that opened it** | No "lost focus" jump to the top of the page |

**Supporting requirements that make the four work:**
- **Move focus in on open** - to the first focusable control, the dialog, or (for destructive modals) the **safe** action, *never* the destructive one.
- **Scroll-lock the background** - the page behind must not scroll while the modal is open.
- **Inert background** - set `aria-hidden`/`inert` on the rest of the page so AT and Tab don't reach it.

> **Exception - destructive dialogs:** ESC and backdrop-click still close, but treat them as **Cancel**, never confirm. For a high-stakes action, prefer disabling backdrop dismissal in favour of the explicit buttons over letting a stray click resolve it.

### Close-intent matrix

| Trigger | A dialog | Leaving a dirty page form or wizard step |
|---|---|---|
| **Cancel / Keep editing** | Close, no action | Stay put, no action |
| **× / ESC / backdrop** | Close, no action - a decision dialog discards its own typed input, since nothing was being saved | Route through the discard guard (§8) |
| **Confirm / Continue / Save** | Run the action, then close | Validate, save, then move on |

---

## 7. Delete / destructive confirmations

The most safety-critical modal. Make an **irreversible** action **deliberate** without being annoying.

### Rules
1. **Name the exact target.** "Delete **project "Q3 Launch"**?" - not "Delete this item?".
2. **State the consequence and whether it's recoverable.** "This permanently deletes the project and its 14 tasks. **This can't be undone.**"
3. **Use a Danger button** (`btn-danger`, Violet-Red `#991547`) labelled with the **verb** - "Delete project", not "OK" or "Yes".
4. **Cancel is the safe default.** The neutral `btn-secondary`, left of the destructive action, **initial focus on Cancel** (or the dialog), never on Delete.
5. **Don't rely on color alone.** Pair Danger red with a clear verb and (optionally) a warning icon.
6. **High-stakes deletes get friction.** For bulk or unrecoverable destruction, require typing the name or checking "I understand" before enabling the Danger button.
7. **Resolve the outcome with a toast.** On success, close and show a toast ("Project deleted."); on error, keep the modal open or reopen with an inline error. Don't relabel buttons (button guide §7).

### Markup pattern

```html
<div class="modal-backdrop" data-modal>
  <div class="modal modal-sm" role="alertdialog" aria-modal="true"
       aria-labelledby="del-title" aria-describedby="del-desc">
    <div class="modal-header">
      <h2 class="modal-title" id="del-title">Delete project "Q3 Launch"?</h2>
      <button class="icon-btn" aria-label="Close" data-close>×</button>
    </div>
    <div class="modal-body">
      <p id="del-desc">
        This permanently deletes the project and its 14 tasks.
        <strong>This can't be undone.</strong>
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-close autofocus>Cancel</button>
      <button class="btn btn-danger" data-confirm>Delete project</button>
    </div>
  </div>
</div>
```

> Use `role="alertdialog"` (not plain `dialog`) for destructive/critical confirmations so screen readers announce them with urgency.

### High-stakes "type to confirm" variant

```html
<div class="modal-body">
  <p id="del-desc">This deletes <strong>all 1,204 customer records</strong> permanently.
     Type <code>DELETE</code> to confirm.</p>
  <input class="modal-input" type="text" aria-label="Type DELETE to confirm"
         data-confirm-text="DELETE" autocomplete="off">
</div>
<div class="modal-footer">
  <button class="btn btn-secondary" data-close>Cancel</button>
  <button class="btn btn-danger" data-confirm disabled>Delete everything</button>
</div>
```

---

## 8. Discard / unsaved-changes confirmations

Protects work-in-progress; appears **only when there are actually unsaved changes**.

### Rules
1. **Track "dirty" state.** Only intercept close/cancel/navigation when the form has **unsaved edits**. A clean form closes immediately - never nag.
2. **Be honest about what's lost.** "You have unsaved changes. If you leave now, they'll be lost."
3. **The destructive choice is "Discard".** In the two-way guard, **Discard changes** is the one solid `btn-danger`; **Keep editing** is the safe `btn-secondary` default with initial focus.
4. **Offer a way to save when it makes sense.** Three-way: *Keep editing* (`btn-secondary`) · *Discard changes* (`btn-secondary btn-text-danger`) · *Save* (the one solid `btn-primary`) - only if saving is valid right now. Saving is the action that commits, so it takes the single solid, and discarding keeps the neutral shell with a danger-coloured label (button guide §4.2). Two solids in one footer is never right, and highlighting the destructive path is not the emphasis you want here.
5. **Guard every exit path.** Cancel button, ×, ESC, backdrop click, *and* in-app navigation away from a dirty form all route through this guard.
6. **For full-page navigation,** also wire the browser's `beforeunload`.
7. **In a step-by-step form, tell the truth about the draft** ([forms.md](forms.md) §10). The steps already saved with **Continue** are kept, and only the current step's unsaved edits go, so say exactly that: "Leave this step? Steps 1 and 2 are saved as a draft. What you have typed on this step is not." Never imply the whole thing is lost, and never imply it is all safe. When the project also persists a partial step, the three-way's save option is **Save and finish later**.

### Markup pattern

```html
<div class="modal modal-sm" role="alertdialog" aria-modal="true"
     aria-labelledby="discard-title" aria-describedby="discard-desc">
  <div class="modal-header">
    <h2 class="modal-title" id="discard-title">Discard unsaved changes?</h2>
  </div>
  <div class="modal-body">
    <p id="discard-desc">You've made changes that haven't been saved.
       If you leave now, they'll be lost.</p>
  </div>
  <div class="modal-footer modal-footer-3">
    <button class="btn btn-secondary" data-close autofocus>Keep editing</button>
    <button class="btn btn-secondary btn-text-danger" data-discard>Discard changes</button>
    <button class="btn btn-primary" data-save>Save</button>
  </div>
</div>
```

---

## 9. Focus management & accessibility

Modals live or die on focus handling - this makes the §6 contract real.

- **On open:** save a reference to the trigger element, then move focus into the modal (first field / safe action / the dialog itself).
- **Trap focus:** intercept `Tab` and `Shift+Tab` so focus wraps within the modal's focusable elements only.
- **On close:** restore focus to the saved trigger element.
- **Roles:** `role="dialog"` for standard modals, `role="alertdialog"` for destructive/critical ones. Always `aria-modal="true"`.
- **Labelling:** `aria-labelledby` → the title; `aria-describedby` → the body message.
- **Background:** apply `inert` (or `aria-hidden="true"`) to everything outside the modal and **lock page scroll**.
- **Close button:** a real `<button>` with `aria-label="Close"`, as `icon-btn` chrome rather than a `.btn` (button guide §4.1).
- **Motion:** respect `prefers-reduced-motion` - fade only, no large transforms.

---

## 10. Copy / labels

- **Title asks the question; button gives the answer.** Title "Delete project?" → button "Delete project". Keep the verb consistent.
- **Name the object** in the title or body ("...project "Q3 Launch"").
- **Buttons say the outcome,** never "OK" / "Yes" / "No". "Delete project", "Discard changes", "Keep editing".
- **State recoverability** for destructive actions: "This can't be undone."
- **Sentence case, active voice, no filler.**

| Context | Title | Safe (secondary) | Destructive / primary |
|---|---|---|---|
| Delete record | Delete invoice #1042? | Cancel | **Delete invoice** (danger) |
| Remove member | Remove Alex from team? | Cancel | **Remove member** (danger) |
| Unsaved changes | Discard unsaved changes? | Keep editing | **Discard changes** (danger) |
| Confirm submit | Submit application? | Cancel | **Submit** (primary) |

---

## 11. Full CSS reference (copy-paste ready)

Pairs with the button CSS from [buttons.md](buttons.md) §10. Uses `color-mix()`.

```css
/* ===== Backdrop ===== */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: var(--modal-z);
  display: grid;
  place-items: center;
  padding: 24px;
  background: var(--modal-backdrop);
  backdrop-filter: blur(var(--modal-backdrop-blur));          /* dim AND blur the page behind */
  -webkit-backdrop-filter: blur(var(--modal-backdrop-blur));  /* Safari */
  opacity: 0;
  transition: opacity 0.2s ease;
}
.modal-backdrop.open { opacity: 1; }

/* JS shows/hides via `hidden` (§12). Because .modal-backdrop sets display:grid, you MUST re-assert
   the hidden state - an author display rule overrides the UA [hidden]{display:none}, otherwise the
   transparent backdrop stays in layout and swallows clicks. */
.modal-backdrop[hidden] { display: none; }

/* Lock page scroll when a modal is open (add to <body>) */
body.modal-open { overflow: hidden; }

/* ===== Dialog surface ===== */
.modal {
  width: 100%;
  max-width: var(--modal-md);          /* overridden per size class below */
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  color: var(--color-text);
  border-radius: var(--modal-radius);
  box-shadow: var(--modal-shadow);
  transform: translateY(8px) scale(0.98);
  opacity: 0;
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-backdrop.open .modal { transform: none; opacity: 1; }

/* ===== Sizes ===== */
.modal-sm { max-width: var(--modal-sm); }  /* 420px - confirmations, decision fields (default)  */
.modal-md { max-width: var(--modal-md); }  /* 600px - a longer explanation or result            */
.modal-lg { max-width: var(--modal-lg); }  /* 900px - read-only content that needs the room     */
.modal-xl { max-width: var(--modal-xl); }  /* > 900px - only when content requires it, capped  */

/* ===== Regions ===== */
.modal-header {
  display: flex;
  align-items: center;                 /* title and close share one row, vertically aligned */
  justify-content: space-between;      /* title on the left, close on the right */
  gap: 12px;
  padding: calc(var(--modal-padding) / 2) var(--modal-padding) 0;  /* half padding on top */
}
.modal-title {
  font-family: var(--font-heading, 'Montserrat', sans-serif);  /* the fixed heading typeface */
  font-size: var(--text-h3, 18px);     /* 18px from the type scale (not a one-off value) */
  font-weight: 700;
  color: var(--color-primary);
  line-height: 1.3;
}
/* Close button: icon-only chrome - `icon-btn` is defined in [buttons.md](buttons.md) §10 and never
   redefined here; this only places it on the title's row, pinned right. */
.modal-header .icon-btn {
  flex-shrink: 0;                      /* never let a long title squash the close target */
  margin: -4px -6px 0 0;               /* nudge into the top-right corner */
  font-size: 24px;                     /* larger × glyph - the `icon-btn` box stays 32px */
  line-height: 1;
}
.modal-body {
  padding: 12px var(--modal-padding);
  overflow-y: auto;                    /* only the body scrolls */
  color: var(--color-text);
  scrollbar-width: none;               /* hide the scrollbar visually; scroll still works */
}
.modal-body::-webkit-scrollbar { width: 0; height: 0; }   /* same, WebKit/Blink */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 0 var(--modal-padding) calc(var(--modal-padding) / 2);  /* half padding on bottom */
}
.modal-footer-3 { justify-content: space-between; }

/* ===== Inputs inside modals ===== */
/* A dialog's decision field (§2). Same primitive as a form field at dialog sizing -
   the values mirror `form-modal` in forms.md §4; keep the two in step. */
.modal-input {
  width: 100%;
  margin-top: 10px;
  min-height: 32px;                 /* --field-height-modal */
  padding: 0 10px;                  /* --field-pad-modal */
  font-family: inherit;
  font-size: 0.9375rem;             /* 15px - --field-font-modal */
  color: var(--color-text);
  background: var(--color-surface);
  border: 1px solid color-mix(in srgb, var(--color-text) 25%, transparent);
  border-radius: 8px;
}
.modal-input:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* ===== Responsive (mobile-first) =====
   On small or tall screens the modal becomes near-full-width and docks to the bottom, actions stack,
   and buttons grow to a >= 44px touch target. */
@media (max-width: 640px), (max-aspect-ratio: 1/1) {
  .modal-backdrop { padding: 12px; place-items: end center; }
  .modal { max-width: 100%; max-height: 92vh; }
  .modal-footer { flex-direction: column-reverse; }
  .modal-footer .btn { width: 100%; min-height: 44px; }  /* touch target */
}

/* ===== Reduced motion ===== */
@media (prefers-reduced-motion: reduce) {
  .modal-backdrop, .modal { transition: opacity 0.15s ease; }
  .modal { transform: none; }
}
```

---

## 12. Full JS reference (behavior contract)

Dependency-free implementation of the four ENFORCED rules plus focus management. Frameworks should use
their own dialog primitive (native `<dialog>`, Radix, Headless UI) - the contract is identical.

```js
const FOCUSABLE = [
  'a[href]', 'button:not([disabled])', 'input:not([disabled])',
  'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])'
].join(',');

function openModal(backdrop, { onConfirm, isDirty } = {}) {
  const dialog  = backdrop.querySelector('.modal');
  const trigger = document.activeElement;            // remember opener (§6: return focus)

  document.body.classList.add('modal-open');         // scroll lock
  backdrop.hidden = false;
  requestAnimationFrame(() => backdrop.classList.add('open'));

  // Move focus in: prefer [autofocus] (the SAFE action), else the dialog
  const first = dialog.querySelector('[autofocus]') || dialog.querySelector(FOCUSABLE) || dialog;
  first.focus();

  function close(confirmed = false) {
    // Optional guard: pass isDirty only where dismissing really would lose work.
    // A decision dialog does not need it - nothing in it was being saved (§6).
    if (!confirmed && isDirty && isDirty()) {
      return showDiscardGuard(() => teardown());     // route to discard confirmation instead
    }
    teardown();
    if (confirmed && onConfirm) onConfirm();
  }

  function teardown() {
    backdrop.classList.remove('open');
    backdrop.addEventListener('transitionend', () => {
      backdrop.hidden = true;
      document.body.classList.remove('modal-open');
      if (trigger) trigger.focus();                  // §6: return focus to trigger
    }, { once: true });
  }

  function onKeydown(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(false); }   // §6: ESC closes
    if (e.key === 'Tab') trapFocus(e, dialog);                      // §6: focus trap
  }

  // §6: backdrop click closes (ignore clicks inside the dialog)
  backdrop.addEventListener('mousedown', (e) => {
    if (e.target === backdrop) close(false);
  });

  dialog.querySelectorAll('[data-close]').forEach(el =>
    el.addEventListener('click', () => close(false)));
  dialog.querySelectorAll('[data-confirm]').forEach(el =>
    el.addEventListener('click', () => close(true)));

  document.addEventListener('keydown', onKeydown);
  backdrop._cleanup = () => document.removeEventListener('keydown', onKeydown);
}

function trapFocus(e, container) {
  const nodes = [...container.querySelectorAll(FOCUSABLE)].filter(n => n.offsetParent !== null);
  if (!nodes.length) return;
  const first = nodes[0], last = nodes[nodes.length - 1];
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
}
```

### React note

Prefer a headless dialog primitive (Radix `Dialog`, Headless UI `Dialog`, or native `<dialog>`) for
focus trap, ESC, scroll-lock, and `aria-modal`. Your job is then: track dirty state, choose the size
class, and wire the Danger/Discard buttons per §7-§8.

---

## 13. Accessibility checklist

- [ ] `role="dialog"` (or `role="alertdialog"` for destructive/critical) + `aria-modal="true"`.
- [ ] `aria-labelledby` → title id; `aria-describedby` → body message id.
- [ ] Focus moves **into** the modal on open (safe action / first field / dialog).
- [ ] Focus is **trapped** within the modal (`Tab` / `Shift+Tab` wrap).
- [ ] Focus **returns to the trigger** on close.
- [ ] **ESC** and **backdrop click** close the modal.
- [ ] Background is **inert / `aria-hidden`** and page scroll is **locked**.
- [ ] Close button is a real `<button>` with `aria-label="Close"`.
- [ ] Destructive confirm uses a verb label + Danger color (not color alone).
- [ ] Touch targets ≥ 44 × 44px; modal usable on small/tall screens.
- [ ] `prefers-reduced-motion` respected.

---

## 14. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Name the target: "Delete project "Q3 Launch"?" | Use vague "Are you sure?" / "Delete this item?" | Users must know exactly what they're destroying |
| Use `btn-danger` + a verb ("Delete project") for destructive confirm | Use a neutral "OK" or gold/accent button to delete | Verb + red prevents accidental destruction |
| Focus the **safe** action (Cancel / Keep editing) on open | Auto-focus the Delete / Discard button | Stops an instinctive Enter from destroying data |
| Show the discard prompt **only when the form is dirty** | Prompt "discard changes?" on a clean form | Nagging on no-op exits trains users to ignore it |
| Close on ESC and backdrop, returning focus to the trigger | Trap users with no ESC/backdrop and lost focus | The expected, accessible escape contract |
| Let only the **body** scroll; pin header + footer | Let the whole dialog (and the page behind) scroll | Keeps title and actions reachable |
| Size by content: 420 / 600 / 900px | Put a one-line confirmation in a 900px modal | Oversized modals bury a simple choice |
| State recoverability ("This can't be undone") | Hide the consequence of a destructive action | Users need to weigh the risk |
| Resolve success/error with a toast, close the modal | Leave the modal open showing "Deleted ✓" | The toast confirms it; the modal's job is done |
| Open one modal at a time | Stack a modal on top of another modal | Stacked modals disorient and trap focus badly |
| Send every create / edit / multi-step / settings form to a page | Open a form in a modal because it is "only a few fields" | Data entry belongs in the app's UI, not over it (§2) |
| Keep a dialog's fields to the ~3 that are the decision | Grow a confirmation into a record editor | A fourth field means it is a form, so it is a page |

---

## 15. Rules for the AI assistant

**ALWAYS**
- Send data entry to a page (§2): a create, edit, multi-step, or settings form is never a dialog. Use a dialog only for a decision, carrying at most the ~3 fields that are the decision.
- Implement the four ENFORCED behaviors (§6): ESC closes, backdrop closes, focus trapped, focus returns to trigger.
- Add `role="dialog"`/`alertdialog`, `aria-modal="true"`, `aria-labelledby`, and `aria-describedby`.
- Pick size by content: 420px (confirmations and decision fields), 600px (a longer explanation), 900px (content that needs the room).
- For deletes: name the target, state recoverability, use `btn-danger` + verb, and focus the **safe** action.
- For dirty forms: guard every exit path (Cancel, ×, ESC, backdrop, navigation) with a discard prompt.
- Lock background scroll and make the background inert while open.
- Resolve outcomes with a toast; close the modal on success.

**NEVER**
- Put a create, edit, multi-step, or settings form in a modal, a drawer, or an off-canvas panel.
- Give a dialog a fourth field, a section heading, or a tab strip - that is a form, and it moves to a page.
- Auto-focus or default the destructive (Delete / Discard) button.
- Use "OK" / "Yes" / "No" for destructive actions, or a non-Danger color to delete.
- Prompt to discard changes when the form has none.
- Stack a modal on top of another modal.
- Let the page behind scroll, or leave focus loose behind the modal.
- Rely on color alone to signal danger.

---

## 16. Quick decision guide

```
Do I even need a modal?
├─ Creating or editing a record? ................................. NO  → page form (forms.md §3)
├─ Must the user decide/act right now, blocking the page? ........ yes → modal
├─ Long or browsable content? .................................... no  → page / route
└─ Non-blocking confirmation of something done? .................. no  → toast

What KIND of modal?
├─ Irreversible (delete / remove / revoke) ...................... alertdialog + btn-danger (focus Cancel)
├─ Leaving a dirty form or a wizard step ........................ discard guard: Keep editing · Discard · Save
├─ A yes/no confirm (non-destructive) ........................... dialog + btn-primary
└─ A decision needing input (~3 fields, max) .................... dialog + the fields that ARE the decision

What SIZE? (by content, not importance)
├─ Confirmation, or the ~3 decision fields ...................... modal-sm  (420px)
├─ A longer explanation or result ............................... modal-md  (600px)
└─ Read-only content that needs the room ........................ modal-lg  (900px)

Which behaviors are NON-NEGOTIABLE?
└─ ESC closes · backdrop closes · focus trapped · focus returns to trigger
```
