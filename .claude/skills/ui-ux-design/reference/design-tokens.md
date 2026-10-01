# Design Tokens - The Approved Values (source of truth)

Single source of truth for every visual value in the CLaaS2SaaS design system. The values are approved and produce the same look and feel in every application.

Define each token below in the project's own styling system (CSS custom properties, a theme file, a preprocessor map, a component-library theme). Keep one definition per token, define every token in both themes, declare `color-scheme: light` / `color-scheme: dark` per theme so native controls follow, and author every component against the tokens, never a hardcoded hex. Token names may adapt to the stack; token values may not change.

## Brand palette (CLaaS2SaaS Brand Guidelines, Oct 2025)

| Role | Color | Hex |
| --- | --- | --- |
| Primary / accent | Midnight Blue | `#193E6B` |
| Secondary accent (active-nav gold) | Green Gold | `#B3A125` |
| semantic.success | Avocado Green | `#5F8025` |
| semantic.warning | Sunray | `#E9AC53` |
| semantic.danger | Violet-Red | `#991547` |
| semantic.info | Jelly Bean Blue | `#448E9D` |
| Secondary (badges, chips, non-interactive accents - **not** a button fill; see `buttons.md` §4) | Cadmium Violet | `#7F3F98` |
| Neutral family | Platinum Beige + White | `#EEE7E0` ramp, `#FFFFFF` |

Never repurpose a role's color and never introduce an off-palette hue; a genuinely new tint derives from an approved hue and must keep the WCAG AA bar (all pairs below are verified: body text well above 4.5:1, indicators at least 3:1).

## Core tokens, per theme

| Token | Light | Dark |
| --- | --- | --- |
| Chrome (sidebar + top bar, ONE color) | `#FFFFFF` | `#080F1A` (ink navy) |
| Chrome text | `#223349` | `#E4EBF4` |
| Chrome muted text (icons, cluster labels, placeholders) | `#566779` | `#9AABC0` |
| Chrome hover tint (nav items, icon buttons) | `rgba(25,62,107,0.07)` | `rgba(255,255,255,0.09)` |
| Chrome edge shadow color | `rgba(15,25,45,0.14)` | `rgba(0,0,0,0.50)` |
| Canvas (main working area) | `#E8DFD0` | `#1A2E46` |
| Card / surface | `#FFFFFF` | `#253E5D` |
| Card border / strong | `#E8E2D8` / `#D5CEC1` | `rgba(255,255,255,0.10)` / `rgba(255,255,255,0.18)` |
| Surface hover tint (table rows, buttons, tabs at rest) | `rgba(25,62,107,0.06)` | `rgba(255,255,255,0.07)` |
| Divider (chrome region borders) | `#E4DCCD` | `rgba(255,255,255,0.13)` |
| Text (ink, never gray) | `#1E2E42` | `#E9EFF6` |
| Muted text | `#4D5E75` | `#A3B2C5` |
| Interactive accent (links, primary buttons, focus, active tab) | `#193E6B` (hover `#142F52`) | `#7FADE1` (hover `#93BBE8`) |
| Accent contrast (text on the accent) | `#FFFFFF` | `#0F1C2E` |
| Nav active (gold treatment) | bg `rgba(179,161,37,0.16)`, fg `#5C5010`, 3px bar `#B3A125` | bg `rgba(201,182,47,0.22)`, fg `#EBDD7E`, 3px bar `#C9B62F` |
| Sidebar filter input background | `#EFE8DB` | `rgba(255,255,255,0.08)` |
| "Soon" pill (nav) | bg `#EFEAE0`, fg `#514A3B` | bg `rgba(255,255,255,0.16)`, fg `#DCE4F0` |
| Notification / alert dot (on chrome) | `#991547` | `#F3AFC9` |
| Tooltip surface (white text on it) | `#202C3E` | `#2A3A52` |
| Text selection highlight | `rgba(179,161,37,0.35)` (both themes) | same |

## Status badge tokens (theme-aware readable pairs)

Semantic colors used as text, icons, or thin edges on a surface always go through these readable tokens, never the raw semantic hex (raw Violet-Red on a dark card is ~1.6:1, invisible). Raw semantic hex is only for solid fills (for example a danger button with white text).

| Role | Light bg (mix with `#FFFFFF`) | Light fg | Dark bg (mix with `#253E5D`) | Dark fg |
| --- | --- | --- | --- | --- |
| neutral | `#F0EBE1` | `#514A3B` | `rgba(255,255,255,0.10)` | `rgba(255,255,255,0.80)` |
| success | 16% Avocado | `#3A4E13` | 28% Avocado | `#CBE79B` |
| warning | 26% Sunray | `#7A500C` | 30% Sunray | `#F8DFAC` |
| danger | 14% Violet-Red | `#85113E` | 32% Violet-Red | `#F5B8CF` |
| info | 18% Jelly Bean | `#1E545F` | 30% Jelly Bean | `#B4E3EC` |
| violet | 15% Cadmium | `#5B2B6E` | 30% Cadmium | `#DFBAEC` |

"n% X" means mix the brand color at that percentage into the theme's card surface (sRGB blend, e.g. CSS `color-mix(in srgb, <brand> n%, <card>)`).

## Type, shape, elevation, motion

| Aspect | Fixed value |
| --- | --- |
| Heading font | Montserrat (600/700), fallback `system-ui, -apple-system, 'Segoe UI', sans-serif` |
| Body font | Source Sans 3 (400/500/600/700), same fallback; base body text 13px / line-height 1.5 |
| Font loading | Google Fonts: `css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap` (or self-host the same families) |
| Spacing scale | 4px base: 4 / 8 / 16 / 24 / 32 / 48 / 64 |
| Radius | cards 12px, controls 8px, tab tops 10px, modals 14px, pills 999px |
| Resting elevation | Cards are FLAT: 1px card border, no resting shadow |
| Hover / overlay elevation | md `0 2px 8px rgba(25,40,65,0.08)` light / `0 3px 10px rgba(0,0,0,0.30)` dark; lg (popovers, modals, toasts) `0 8px 24px rgba(15,25,45,0.14)` light / `0 10px 28px rgba(0,0,0,0.45)` dark |
| Chrome edge shadow | rail `2px 0 10px <chrome-edge>`, top bar `0 2px 10px <chrome-edge>` (lifts the chrome off the canvas) |
| Focus ring | `2px solid <accent>`, `outline-offset: 2px`, on every interactive element |
| Rail animation | width `0.28s cubic-bezier(0.4, 0, 0.2, 1)`; honor `prefers-reduced-motion` (instant) |

## Shell dimensions

| Element | Fixed value |
| --- | --- |
| Top bar and rail head height | 52px (their bottom dividers form one continuous line) |
| Sidebar width | 240px expanded / 56px collapsed |
| Controls | buttons and inputs 32px tall (small 27px), top-bar icon buttons 34px, avatar 30px; a button and the field beside it share one height and one border token, and every button at a given size shares one geometry (`buttons.md` §4) |
| Wide logo | `height: 22px; width: auto` in the rail head |
| C2S short mark | 40x34 slot, `object-fit: contain`, centered in the collapsed rail |
| Scrollbars | the sidebar nav list and the main canvas hide scrollbars visually (`scrollbar-width: none` + hidden webkit scrollbar) while staying fully scrollable |

## Theme switching

Provide the System / Dark / Light switcher (that segment order, System default) in the profile menu's fixed Appearance section, persist the choice, resolve System from the OS preference, and set the effective theme on the document root. The logos and the favicon swap with the effective theme (light variants on the light chrome, dark variants on the dark chrome). The two themes' chromes are different colors by design; never render the same chrome in both modes.

## Follow accordingly

Implement these values exactly in the confirmed stack. Component-level construction rules live in the other files in this folder and in `.claude/rules/ui-ux-quality.md`; this file owns the values they consume. Never invent a value outside it.
