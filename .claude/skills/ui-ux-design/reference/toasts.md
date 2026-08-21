# Toast & Feedback Message UI/UX Design Guide

> Concrete values here (palette, fonts, icons, class prefixes) are approved CLaaS2SaaS design-system
> values; the canonical source of truth is `design-tokens.md`. Every token is defined in both light
> and dark themes. Use the skill's bundled icon registry for glyphs; type is always carried by icon +
> text, never color alone. Placement is the top right, always. Responsive is mobile-first.

Reference for AI-assisted development: follow these rules so feedback stays consistent, accessible,
and predictable.

> **TL;DR for the AI**
> 1. A **toast** is a brief, **non-blocking** confirmation of an action's result; it never interrupts
>    or demands a click (that's a [modal](modals-dialogs.md), §3).
> 2. Pick the **type by meaning** (§3): success / error / warning / info, each a fixed token color +
>    icon. Never signal by color alone.
> 3. **Success auto-dismisses** (~4s); **errors persist** until dismissed (§5). Always give a manual close.
> 4. **Stack at the top right**, always, newest nearest the edge; cap the visible stack (§6).
> 5. Toasts announce via an `aria-live` region (`polite` for success/info, `assertive` for errors),
>    **always in the DOM** (§9).
> 6. Use the **design tokens**; never hardcode a hex or invent a shade.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Anatomy of a toast](#2-anatomy-of-a-toast)
3. [Types - meaning drives color & icon](#3-types--meaning-drives-color--icon)
4. [Toast vs other feedback (when to use what)](#4-toast-vs-other-feedback-when-to-use-what)
5. [Duration & dismissal (ENFORCED)](#5-duration--dismissal-enforced)
6. [Placement & stacking (ENFORCED)](#6-placement--stacking-enforced)
7. [Content & copy](#7-content--copy)
8. [Actions inside a toast](#8-actions-inside-a-toast)
9. [Accessibility (ENFORCED)](#9-accessibility-enforced)
10. [Motion & behavior](#10-motion--behavior)
11. [Full CSS reference (copy-paste ready)](#11-full-css-reference-copy-paste-ready)
12. [Full JS reference (toast API)](#12-full-js-reference-toast-api)
13. [Accessibility checklist](#13-accessibility-checklist)
14. [Do's and Don'ts](#14-dos-and-donts)
15. [Rules for the AI assistant](#15-rules-for-the-ai-assistant)
16. [Quick decision guide](#16-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Toasts consume the palette and spacing scale. Buttons inside toasts follow [buttons.md](buttons.md).
No colors outside the palette. Every token is defined in both themes; the app provides a System /
Dark / Light switcher.

```css
:root {
    /* Palette (approved - dark-theme values live in design-tokens.md) */
    --color-primary: #193E6B;                 /* Midnight Blue */
    --color-accent: #B3A125;                  /* Green Gold */
    --color-secondary-violet: #7F3F98;        /* Cadmium Violet */
    --color-secondary-blue: #448E9D;          /* Jelly Bean Blue */
    --color-secondary-sunray: #E9AC53;        /* Sunray */
    --color-success: #5F8025;                 /* Avocado Green */
    --color-danger: #991547;                  /* Violet-Red */
    --color-background: #E8DFD0;              /* canvas (dark: #1A2E46) */
    --color-surface: #FFFFFF;                 /* dark: #253E5D */
    --color-text: #1E2E42;                    /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;              /* dark: #A3B2C5 */

    /* Theme-aware readable type colors - the toast edge + icon use THESE, never the raw semantic hex */
    --badge-success-fg: #3A4E13;              /* dark: #CBE79B */
    --badge-danger-fg: #85113E;               /* dark: #F5B8CF */
    --badge-warning-fg: #7A500C;              /* dark: #F8DFAC */
    --badge-info-fg: #1E545F;                 /* dark: #B4E3EC */

    /* Toast tokens */
    --toast-width: 360px;            /* max-width of a single toast */
    --toast-radius: 10px;
    --toast-gap: 12px;               /* vertical gap between stacked toasts */
    --toast-pad: 14px 16px;
    --toast-shadow: 0 8px 24px rgba(15, 25, 45, 0.14);  /* dark: 0 10px 28px rgba(0,0,0,0.45) */
    --toast-accent-bar: 4px;         /* colored left edge that carries the type */
    --toast-duration-success: 4000ms;
    --toast-duration-info: 5000ms;
    --toast-z: 1100;                 /* above modals (--modal-z is 1000) */
}
```

The **type color** lives on a left accent bar + icon, not the whole background; the surface stays
`--color-surface` so text keeps contrast (each theme defines its own surface).

---

## 2. Anatomy of a toast

An **icon** + a **message** + (optional) **one action** + a **close button**, on a surface with a
type-colored accent.

```
┌─┬───────────────────────────────────────┬───┐
│▌│ ✓  Contact saved                      │ ✕ │   ▌ = type accent bar
│▌│    Jane Doe was added to your pipeline│   │   ✓ = type icon
│▌│                         [ View ]      │   │   [ View ] = optional action (§8)
└─┴───────────────────────────────────────┴───┘
```

- **Accent bar / icon:** carries the type (§3), always paired with icon **and** wording, never alone.
- **Title:** one short line stating the outcome ("Contact saved"). Required.
- **Description:** optional one-sentence detail or next step.
- **Action:** at most **one** inline action (Undo, View, Retry); see §8.
- **Close:** always-present `✕` button (`aria-label="Dismiss"`), beside the action on the right, even
  on auto-dismissing toasts.

---

## 3. Types - meaning drives color & icon

Four types. Pick by **meaning**. Each pairs a token color with a distinct icon so type survives
color-blindness and greyscale.

| Type | Use for | Token (accent + icon) | Icon | Default behavior |
|---|---|---|---|---|
| **Success** | An action completed ("Saved", "Sent", "Deleted") | `--color-success` | `✓` check | Auto-dismiss (~4s) |
| **Error** | An action failed and the user should know | `--color-danger` | `⚠` triangle | **Persist** until dismissed |
| **Warning** | Completed with a caveat, or a risky reversible result | `--color-secondary-sunray` | `!` circle | Persist or long (~6-8s) |
| **Info** | Neutral status / FYI ("Export started", "Sync running") | `--color-secondary-blue` | `ⓘ` info | Auto-dismiss (~5s) |

- **Never color-only.** Each type carries its icon **and** wording.
- **Surface stays neutral.** The type color sits on the accent bar + icon (+ action link); the body is
  `--color-surface` with `--color-text`. Don't flood the toast with a saturated accent.
- **One type per toast.** For "saved but with warnings", pick the dominant message; don't stack two types.

---

## 4. Toast vs other feedback (when to use what)

Match the mechanism to how urgent and how blocking the message is.

| Mechanism | Use when | Blocking? | Lives in |
|---|---|---|---|
| **Toast** | Confirm the result of a **discrete action** just taken (save, delete, send) | No | this guide |
| **Inline field error** | A specific **form field** is invalid | No | [forms.md](forms.md) §6 |
| **Toast (error)** | A **submit was blocked** by validation: one toast naming how many fields need attention, while focus moves to the first invalid field. Never a summary card repeating the field messages | No | this guide + [forms.md](forms.md) §7 |
| **Inline form/page banner** | A **whole form or page** has a persistent state: a form-level error that belongs to no field (the save failed, the service was unreachable), or a standing condition ("Trial ends in 3 days"). Never a list of field errors | No (but stays) | banner pattern |
| **Modal dialog** | The user **must decide** before continuing (confirm destructive, discard changes) | **Yes** | [modals-dialogs.md](modals-dialogs.md) |
| **Empty / loading / error state** | A whole region has **no data**, is **loading**, or **failed** | No | region state pattern |

- Quick "it worked / it didn't" after an action → **Toast.**
- Problem tied to one input → **Inline field error**, and only there.
- Submit blocked by validation → **one error toast** + focus the first invalid field ([forms.md](forms.md) §7).
- Must the user acknowledge or choose? → **Modal** (a toast can be missed).
- Standing condition, not a one-off result → **Banner.**
- **Never use a toast for critical, must-not-miss information** - use a modal or persistent banner.

---

## 5. Duration & dismissal (ENFORCED)

1. **Success & info auto-dismiss.** Success ~ **4s** (`--toast-duration-success`), info ~ **5s**
   (`--toast-duration-info`).
2. **Errors persist** until dismissed (or navigation away). Same for any toast carrying an **action**
   (e.g. Undo).
3. **Always provide manual close.** Every toast has a `✕`; never rely on the timer as the only exit.
4. **Pause on interaction.** Auto-dismiss pauses on hover and on keyboard focus, resumes on leave.
5. **Dismiss is per-toast.** Closing one never clears the others (a "Clear all" is optional, §6).

Never auto-dismiss faster than ~3s (unreadable) or hold a non-error toast longer than ~8s. If content
needs longer to read, it isn't a toast - use a banner or modal (§4).

---

## 6. Placement & stacking (ENFORCED)

**Top right. Always, in every app, for every type of toast.** There is no other placement and no
per-screen choice: a user who has learned where feedback appears should never have to look anywhere
else, and an error that surfaces in a different corner from a success is the same defect as two
screens disagreeing about a button.

- **One host, top right.** Every toast shares a single fixed container anchored to the top right,
  stacked vertically.
- **Below the top bar.** Offset the host by the top-bar height plus the spacing scale, so a toast
  never covers the top-bar utilities (notifications, the profile menu) it sits beside.
- **Newest nearest the top edge.** New toasts enter at the top and push older ones down.
- **Success and error share the host.** The polite and assertive live regions are separate elements
  (§9) but they sit inside that one container, assertive first, so nothing moves to another corner
  because of its type.
- **Cap the visible stack** at ~**3-4**; collapse/queue extras and show the next when one dismisses.
- **Offset from edges** by the spacing scale (~24px).
- **Never reposition to dodge content.** If a toast would cover something important, the answer is to
  offset the host below the chrome once, for the whole app, not to move that screen's toast.

### "Clear all" / "Dismiss all"

A property of the **stack**, not a single toast.

- **Attach it to the host**, directly above the top toast, moving with the stack. Keep the count in the accessible name as well as the label, so "Clear all (4)" announces the four.
- **Show conditionally** once the visible stack passes ~**3+**; hide when it thins. A count ("Clear all (4)")
  reinforces why it appeared.
- **Right-aligned, and the shared neutral secondary button** at the small size (`btn btn-secondary btn-sm`, [buttons.md](buttons.md) §4). "Clear all" is a labeled action the user chooses, so it takes the one secondary look rather than a pill of its own.
- **Spare undismissed actions.** "Clear all" dismisses informational toasts but leaves (and re-promotes
  from the queue) any toast still showing an Undo/Retry.

There is no placement table. Bottom-right, top-center, and bottom-center are not options, including
for a single screen, a single toast type, or a layout where the top right feels crowded.

**Responsive (mobile-first).** On touch / tall / narrow screens the toast goes **full-width** across
the top (with side margins) instead of a fixed 360px card, keeping any inline action or close target
≥ 44px. That is the same top edge, widened - not a different corner. The fixed card is the
wider-screen enhancement. Use width and/or aspect-ratio breakpoints, and keep the host clear of a
fixed header.

---

## 7. Content & copy

- **Lead with the outcome** ("Contact saved", "Couldn't send invite"), in plain language.
- **Be specific but brief.** Name the object when it helps ("Invoice #1042 deleted"); push detail to
  the description.
- **Errors say what + what next:** "Couldn't save - check your connection and try again." Pair with a
  **Retry** action where possible (§8).
- **Match the verb to the action and its button** (Save → "Saved", Send → "Sent"), per [buttons.md](buttons.md) §8.
- **No raw system/stack errors.** Map to human copy; never dump a status code or exception text.
- **One idea per toast.** Two unrelated results = two toasts, or none.
- **Don't toast the obvious.** If the result is already plainly on screen (a row appears, a field
  updates), skip it. Toast when the change is off-screen, asynchronous, or easy to miss.

---

## 8. Actions inside a toast

At most **one** inline action, only when genuinely useful.

- **Common actions:** **Undo** (reversible delete/change), **Retry** (after failure), **View** (jump to item).
- **Keep it to one.** No multi-button toolbars; a real choice is a [modal](modals-dialogs.md) (§4).
- **Style as the toast's own inline text action** in the type color. It is a component primitive rather
  than a `.btn`, so it is neither of the two button looks and needs no border ([buttons.md](buttons.md)
  §4.1). Never a solid button, and never a second one.
- **Actions extend life.** A toast with an action must not auto-dismiss out from under the user -
  persist it (or a long timer that pauses on hover/focus, §5).
- **Undo needs a real window.** The destructive operation must stay recoverable at least the toast's
  lifetime; don't show Undo you can't honor.

```html
<div class="toast toast-success" role="status">
  <span class="toast-icon" aria-hidden="true">✓</span>
  <div class="toast-body">
    <p class="toast-title">Contact deleted</p>
  </div>
  <div class="toast-trailing">
    <button type="button" class="toast-action">Undo</button>
    <button type="button" class="icon-btn toast-close" aria-label="Dismiss">&times;</button>
  </div>
</div>
```

---

## 9. Accessibility (ENFORCED)

Toasts appear without focus moving to them, so screen-reader users learn of them only through a
**live region**.

1. **Live region always present.** Render a persistent container with `aria-live` already in the DOM
   at load; injecting the region with the message can swallow the announcement.
2. **Right politeness per type.** Success/info use `aria-live="polite"` (or `role="status"`); **errors**
   use `aria-live="assertive"` (or `role="alert"`). Two regions, one host: politeness decides how the
   message is announced, never where it appears (§6).
3. **Don't steal focus.** A toast must not move keyboard focus. Close and any action are reachable in
   tab order but not auto-focused.
4. **Keyboard-operable.** Close (`✕`) and the action are real `<button>`s with visible `:focus-visible`
   rings; `Esc` may dismiss the most recent focused toast.
5. **Never color-only.** Type is conveyed by icon + text (§3).
6. **Respect reduced motion** - replace slide/scale with a fade (§10).
7. **Readable timing.** Auto-dismiss pauses on hover/focus (§5); errors don't auto-dismiss.

```html
<!-- One host, top right, present from page load. Both live regions live inside it, so an
     error never lands in a different corner from a success (§6). Assertive first: an error
     stacks above the informational toasts. -->
<div class="toast-host" id="toastHost">
  <!-- Auto-shown only when the stack is deep (§6); spares undismissed actions -->
  <div class="toast-clear" id="toastClear" aria-hidden="true">
    <button type="button" class="btn btn-secondary btn-sm" aria-label="Clear all notifications">Clear all</button>
  </div>
  <div class="toast-region" id="toastAssertive" aria-live="assertive" aria-relevant="additions"></div>
  <div class="toast-region" id="toastPolite" aria-live="polite" aria-relevant="additions"></div>
</div>
```

---

## 10. Motion & behavior

- **Enter:** quick slide-in from the edge + fade (~150-200ms, ease-out).
- **Exit:** fade + slight slide/scale out (~150ms); reflow the stack smoothly.
- **Stack shift:** remaining toasts animate to their new position rather than snapping.
- **Keep it cheap.** Animate `transform` and `opacity` only.
- **Reduced motion:** under `prefers-reduced-motion: reduce`, drop slide/scale, use an opacity fade.

---

## 11. Full CSS reference (copy-paste ready)

Pairs with the button CSS ([buttons.md](buttons.md) §10). Type is set by a modifier class
(`toast-success` / `toast-error` / `toast-warning` / `toast-info`) driving one `--toast-color`.

```css
/* ===== Region (one per app, present at load) - TOP RIGHT, always (6) =====
   Offset below the top bar so a toast never covers the top-bar utilities it sits beside.
   --topbar-h is the shell's own height token (topbar-sidenav.md). */
.toast-host {
  position: fixed;
  top: calc(var(--topbar-h, 52px) + var(--spacing-sm, 8px));
  right: var(--spacing-lg, 24px);
  display: flex; flex-direction: column-reverse;   /* newest nearest the top edge */
  gap: var(--toast-gap); z-index: var(--toast-z);
  width: min(var(--toast-width), calc(100vw - 2 * var(--spacing-lg, 24px)));
  pointer-events: none;                             /* let clicks pass between toasts */
}
/* The two live regions (9) sit INSIDE that one host, assertive first, so an error never
   lands in a different corner from a success. */
.toast-region { display: flex; flex-direction: column-reverse; gap: var(--toast-gap); }
.toast-host > .toast { pointer-events: auto; }

/* ===== "Clear all" - shown only when the stack is deep (§6) ===== */
.toast-clear { order: 1000; align-self: flex-end; pointer-events: auto; display: none; }
.toast-clear.is-visible { display: inline-flex; }
/* The control itself is the shared .btn .btn-secondary .btn-sm from buttons.md §10. Only the
   stack-level shadow is added here, since it floats over the page rather than sitting on a card. */
.toast-clear .btn { box-shadow: var(--toast-shadow); }

/* ===== Toast surface ===== */
.toast {
  --toast-color: var(--color-secondary-blue);
  position: relative; display: grid; grid-template-columns: auto 1fr auto;
  align-items: start; gap: 10px;
  padding: var(--toast-pad); padding-left: calc(16px + var(--toast-accent-bar));
  background: var(--color-surface); color: var(--color-text);
  border-radius: var(--toast-radius); box-shadow: var(--toast-shadow);
  font-family: var(--font-body, 'Source Sans 3', sans-serif); overflow: hidden;
}
.toast::before {   /* type accent bar */
  content: ""; position: absolute; left: 0; top: 0; bottom: 0;
  width: var(--toast-accent-bar); background: var(--toast-color);
}

/* ===== Type modifiers (§3) - theme-aware readable tokens, never raw hex ===== */
.toast-success { --toast-color: var(--badge-success-fg); }
.toast-error   { --toast-color: var(--badge-danger-fg); }
.toast-warning { --toast-color: var(--badge-warning-fg); }
.toast-info    { --toast-color: var(--badge-info-fg); }

/* ===== Icon (carries the type alongside the bar) ===== */
.toast-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; flex: none; margin-top: 1px;
  color: var(--toast-color); font-size: 1rem; font-weight: 700;
}

/* ===== Copy ===== */
.toast-body { min-width: 0; }
.toast-title { margin: 0; font-weight: 600; font-size: 0.95rem; line-height: 1.3; }
.toast-desc { margin: 2px 0 0; font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.4; }

/* ===== Trailing controls - action + close on the right ===== */
.toast-trailing { display: flex; align-items: center; gap: 4px; align-self: start; }

/* ===== Inline action (one only, §8) - the toast's own text action, type-colored, not a .btn ===== */
.toast-action {
  font: inherit; font-weight: 600; font-size: 0.85rem;
  background: none; border: none; padding: 4px 6px; border-radius: 6px;
  color: var(--toast-color); cursor: pointer; white-space: nowrap;
}
.toast-action:hover { background: color-mix(in srgb, var(--toast-color) 12%, transparent); }
.toast-action:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }

/* ===== Close (always present, §5) ===== */
/* The close mark is the shared icon-only chrome control from buttons.md §4.1 (.icon-btn), which
   carries the size, the hover tint, the focus ring, and the coarse-pointer 44px bump. Only its
   placement inside the toast grid belongs here. */
.toast-close {
  background: none; border: none; cursor: pointer; line-height: 1;
  font-size: 1.25rem; color: var(--color-text-muted); padding: 0 2px;
}
.toast-close:hover { color: var(--color-text); }
.toast-close:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; border-radius: 4px; }

/* ===== Motion (§10) ===== */
@keyframes toast-in  { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: none; } }
@keyframes toast-out { from { opacity: 1; transform: none; }            to { opacity: 0; transform: translateX(16px); } }
.toast { animation: toast-in 0.18s ease-out; }
.toast.is-leaving { animation: toast-out 0.16s ease-in forwards; }
@media (prefers-reduced-motion: reduce) {
  .toast, .toast.is-leaving { animation: none; transition: opacity 0.15s linear; }
}

/* ===== Responsive (mobile-first) - full-width top bar on small/touch/tall screens ===== */
@media (max-aspect-ratio: 1/1) {
  .toast-host {
    left: var(--spacing-md, 16px); right: var(--spacing-md, 16px); top: var(--spacing-md, 16px);
    width: auto;                       /* full-width across the top */
  }
  @keyframes toast-in  { from { opacity: 0; transform: translateY(-16px); } to { opacity: 1; transform: none; } }
  @keyframes toast-out { from { opacity: 1; transform: none; }             to { opacity: 0; transform: translateY(-16px); } }
}
```

---

## 12. Full JS reference (toast API)

Dependency-free implementation of §5 (auto-dismiss + pause), §8 (one action), and §9 (live-region
politeness). Frameworks wrap their own toast lib; the contract is identical.

```js
const ToastHost = (() => {
  const host = document.getElementById('toastHost');       // present in the DOM at load (§9)
  const clearEl = document.getElementById('toastClear'), clearBtn = clearEl.querySelector('button');
  const MAX_VISIBLE = 4, CLEAR_THRESHOLD = 3, queue = [];   // cap the visible stack; reveal Clear all when deep (§6)
  const DURATION = { success: 4000, info: 5000, warning: 6000, error: 0 };  // 0 = persist (§5)
  const ICON = { success: '✓', error: '⚠', warning: '!', info: 'ⓘ' };
  const toastCount = () => host.querySelectorAll('.toast').length;

  function updateClear() {                                  // host-anchored bulk dismiss (§6)
    const n = toastCount(), show = n >= CLEAR_THRESHOLD;
    clearEl.classList.toggle('is-visible', show);
    clearEl.setAttribute('aria-hidden', show ? 'false' : 'true');
    clearBtn.textContent = `Clear all (${n})`;
  }

  function build({ type = 'info', title, desc = '', action } = {}) {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');  // errors interrupt (§9)
    if (action) el.dataset.hasAction = 'true';             // spared by Clear all (§6)
    el.innerHTML = `
      <span class="toast-icon" aria-hidden="true">${ICON[type] || ICON.info}</span>
      <div class="toast-body"><p class="toast-title"></p>${desc ? '<p class="toast-desc"></p>' : ''}</div>
      <div class="toast-trailing">
        ${action ? '<button type="button" class="toast-action"></button>' : ''}
        <button type="button" class="icon-btn toast-close" aria-label="Dismiss">&times;</button>
      </div>`;
    el.querySelector('.toast-title').textContent = title;
    if (desc) el.querySelector('.toast-desc').textContent = desc;

    let timer;
    const ms = action ? 0 : DURATION[type] ?? 5000;
    const dismiss = () => {
      clearTimeout(timer); el.classList.add('is-leaving');
      el.addEventListener('animationend', remove, { once: true });
      setTimeout(remove, 220);                             // fallback if motion is disabled
    };
    function remove() { if (el.isConnected) { el.remove(); pump(); } }

    el.querySelector('.toast-close').addEventListener('click', dismiss);
    if (action) {
      const btn = el.querySelector('.toast-action');
      btn.textContent = action.label;
      btn.addEventListener('click', () => { action.onClick?.(); dismiss(); });
    }
    // Auto-dismiss with pause-on-hover/focus (§5); errors & action-toasts persist
    const start = () => { if (ms > 0) timer = setTimeout(dismiss, ms); }, stop = () => clearTimeout(timer);
    el.addEventListener('mouseenter', stop); el.addEventListener('mouseleave', start);
    el.addEventListener('focusin', stop);    el.addEventListener('focusout', start);
    return { el, start };
  }

  function pump() {                                        // promote queued toasts up to the cap (§6)
    while (toastCount() < MAX_VISIBLE && queue.length) {
      const { el, start } = queue.shift();
      host.appendChild(el); start();                       // newest enters at the edge (§6)
    }
    updateClear();
  }
  function toast(opts) { queue.push(build(opts)); pump(); }

  function clearAll() {                                    // dismiss all but keep undismissed actions (§6)
    for (let i = queue.length - 1; i >= 0; i--) if (queue[i].el.dataset.hasAction !== 'true') queue.splice(i, 1);
    host.querySelectorAll('.toast').forEach(c => {         // keep Undo/Retry toasts
      if (c.dataset.hasAction !== 'true') c.querySelector('.toast-close').click();
    });
    pump();
  }
  clearBtn.addEventListener('click', clearAll);

  return {
    success: (title, opts) => toast({ ...opts, type: 'success', title }),
    error:   (title, opts) => toast({ ...opts, type: 'error',   title }),
    warning: (title, opts) => toast({ ...opts, type: 'warning', title }),
    info:    (title, opts) => toast({ ...opts, type: 'info',    title }),
    show: toast, clearAll,
  };
})();

// Usage
ToastHost.success('Contact saved', { desc: 'Jane Doe was added to your pipeline.' });
ToastHost.error('Couldn't send invite', { desc: 'Check your connection and try again.', action: { label: 'Retry', onClick: resend } });
ToastHost.success('Contact deleted', { action: { label: 'Undo', onClick: restore } });
```

---

## 13. Accessibility checklist

- [ ] A persistent live region (`aria-live`) exists **in the DOM at page load**, not injected with the message.
- [ ] Success/info use `polite` / `role="status"`; **errors** use `assertive` / `role="alert"`.
- [ ] Toasts **never auto-focus** or steal keyboard focus.
- [ ] Close (`✕`) and any action are real `<button>`s, keyboard-reachable, with `:focus-visible` rings.
- [ ] Type is conveyed by **icon + text**, never color alone.
- [ ] Auto-dismiss **pauses on hover and focus**; **errors don't auto-dismiss**.
- [ ] `prefers-reduced-motion` replaces slide/scale with a fade.
- [ ] Copy is human-readable - no raw status codes / stack traces.
- [ ] Toasts don't permanently cover primary actions or an open modal's footer.
- [ ] On touch/tall screens, toasts reflow full-width and stay clear of fixed bottom nav.

---

## 14. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Use a toast to confirm a discrete action's result | Use a toast for must-acknowledge decisions | Toasts are transient and missable - use a modal (§4) |
| Auto-dismiss success/info; **persist errors** | Auto-dismiss an error or an Undo toast | The user may miss it or lose the action (§5) |
| Convey type with icon **+** color **+** text | Rely on the accent color alone | Fails color-blind users (§3, §9) |
| Keep one short message (+ optional one action) | Cram paragraphs or multiple buttons in a toast | That's a banner or modal |
| Pause the timer on hover/focus | Cut a toast off while it's being read or reached | Accessibility + usability (§5) |
| Stack at the top right, cap at ~3-4 | Put a toast in any other corner, or scatter them by type | Predictability: one place to look, always (§6) |
| Write human error copy with a next step | Dump server/stack errors into the toast | Users can't act on raw errors (§7) |
| Keep the surface light, color on the accent bar | Flood the whole toast with a saturated color | Preserves text contrast (§1, §3) |

---

## 15. Rules for the AI assistant

When generating any success/error/feedback message, the assistant **must**:

1. **Default to a toast** only for confirming the result of a discrete user action; must-acknowledge →
   [modal](modals-dialogs.md); field problem → [inline form error](forms.md); standing condition → banner (§4).
2. **Pick the type by meaning** (success / error / warning / info) with the matching token color + icon
   + wording (§3). Never color-only.
3. **Set duration by type:** success ~ 4s, info ~ 5s, **errors and action-toasts persist** with a manual
   close; pause auto-dismiss on hover/focus (§5).
4. **Render one persistent `aria-live` region** at load, `polite` for success/info and
   `assertive`/`role="alert"` for errors, and **never auto-focus** the toast (§9).
5. **Allow at most one inline action** (Undo / Retry / View), styled as the toast's own inline text action in the type color - a component primitive, not a `.btn` ([buttons.md](buttons.md) §4.1); more is a modal (§8).
6. **Stack at the top right** and nowhere else, cap the visible stack, reflow **full-width on
   small/touch/tall screens** via width and/or aspect-ratio breakpoints (§6, §11).
7. **Use the design tokens**; keep the surface light with the type color on the accent bar/icon (§1).
8. **Write human copy** - outcome first, next step for errors, no raw status codes (§7).
9. **Honor `prefers-reduced-motion`** with a fade fallback (§10).

---

## 16. Quick decision guide

```
Need to give feedback?
│
├─ Must the user decide / acknowledge before continuing? ──▶ MODAL (modals-dialogs.md)
├─ Is it about a specific form field? ───────────────────▶ INLINE FIELD ERROR (forms.md §6)
├─ Is it a standing condition (not a one-off result)? ───▶ BANNER
└─ Confirming the result of an action the user just took? ▶ TOAST
   ├─ Did it succeed? ───────▶ toast-success  · auto-dismiss ~4s · ✓
   ├─ Did it fail? ──────────▶ toast-error    · PERSIST · ⚠ · (Retry?)
   ├─ Succeeded with a caveat / risky-but-reversible? ─▶ toast-warning · persist/long · !
   └─ Neutral status / FYI? ─▶ toast-info     · auto-dismiss ~5s · ⓘ

Offering Undo? ──▶ make the toast persist + ensure the action is actually recoverable (§8)
Off-screen / async result? ──▶ toast it. Already visible on screen? ──▶ don't toast (§7)
```

---
