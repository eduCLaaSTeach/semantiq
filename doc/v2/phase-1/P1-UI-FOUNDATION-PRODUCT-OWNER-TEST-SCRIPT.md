# Product Owner Test Script — UI, Brand & Navigation Foundation

Written for the Product Owner, in your words, on your screens. Required by
`CLAUDE.md` §3. Every one of the twelve required parts is present.

---

## 1. Feature or task being tested

**UI, Brand & Navigation Foundation.** The professional look and feel, the
CLaaS2SaaS branding, the **redesigned two-column Login page**, and the complete
approved navigation with every future entry visible but locked.

This unit builds **no new capability**. Organisation (P1-01) is still the only
screen behind the menu. Everything else is a label.

## 2. Deployed build / merge SHA

| | |
| --- | --- |
| Branch | `claude/semantiq-github-review-a9jaqg` |
| Commits | `11b6fa9` — *UI, brand and navigation foundation*<br>plus the split Login screen, on the same branch |
| Merge SHA | **filled in after merge — see the pull request** |
| Deployed to | production, after CI is green and the branch is merged |

## 3. Preconditions

Before you start:

1. You can sign in to production with your Microsoft account
   (`salil@lithan.com`) and you are the **System Administrator**.
2. The build above is deployed. If the Login page still shows the old styling,
   the deployment has not landed — stop and say so.
3. Use a normal desktop browser. You will also be asked to narrow the window,
   so do not run it maximised on a second monitor you cannot resize.
4. Your browser is set to **light** appearance to begin with. You will switch
   themes inside the product during the test.

## 4. Test data required

**None.** This unit reads nothing and writes nothing.

You are **not** asked to create, edit or delete any organisation, legal entity,
business unit, department, team or person. If any step below appears to ask you
to enter business data, stop — that is a defect in this script.

## 5. Warning — permanent data

This script asks you to enter **no data at all**, so nothing permanent is
created by following it.

One standing warning still applies, because step 33 puts you on a screen that
can save: **SemantIQ has no hard delete for organisation structure.** If you
type into the Company Profile form and press *Save changes*, that change is
real and is recorded. **Do not save anything during this test.** Look at the
screen and move on.

## 6–7. Numbered steps and expected results

Record PASS or FAIL for every step. Where a step has several checks, it is FAIL
if any one of them fails; note which.

### A. The Login page — corporate branding and SemantIQ identity

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 1 | Open the SemantIQ production URL while signed out. | A **two-column** screen: a dark blue branded panel on the left (a little over half the width) and a clean, bright sign-in panel on the right. It looks like a finished enterprise product, not a form on an empty page. | |
| 2 | Look at the top-left of the dark panel. | The **CLaaS2SaaS** logo, then a divider, then **SemantIQ**. One is a logo and one is set in type, so they read as company and product — not as two competing logos. | |
| 3 | Look closely at that logo. | **Exactly one** logo. Sharp, correctly proportioned, not stretched, recoloured, boxed or plated, and fully inside the panel. | |
| 4 | Read the small pill above the headline. | **Business Decision Intelligence** | |
| 5 | Read the headline. | Three lines: **"From business data to"** / **"confident decisions"** / **"in moments."** — with **"confident decisions"** in gold. It should feel like the most important thing on the screen. | |
| 6 | Read the paragraph below it. | "Bring governed data, business context and intelligent analysis together to understand what changed, why it matters and what to do next." | |
| 7 | Look at the row of numbered chips. | Five, in order: **1 Connect · 2 Govern · 3 Understand · 4 Ask · 5 Decide**. | |
| 8 | Click one of the chips. | **Nothing happens.** They describe the product journey; they are not navigation. | |
| 9 | Read the three cards at the bottom of the dark panel. | **Unified Intelligence** — "Bring trusted business information together in one governed intelligence experience." · **Ask SemantIQ** — "Explore performance, change, risk and opportunity using natural business questions." · **Decision Intelligence** — "Turn insights into clearer priorities, recommendations and informed next actions." Each has an icon. | |
| 10 | Look at the whole dark panel. | No large empty areas, no clutter, generous spacing, everything comfortably readable against the dark background. | |

### A2. The sign-in panel

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 11 | Look at the right-hand panel. | Heading **"Welcome to SemantIQ"**, then "Sign in securely to continue to your decision intelligence workspace." | |
| 12 | Look at the button. | One button, **"Continue with Microsoft"**, with the Microsoft four-square logo. It is obvious and clearly the main action. | |
| 13 | Check what else is offered. | **Nothing else.** No Google, no Apple, no email-and-password, no "or continue with" tabs, no sign-up or forgotten-password link. Microsoft is the only method SemantIQ supports. | |
| 14 | Read below the button. | "Access is managed by your organisation's administrator." and "Contact your administrator if you cannot access SemantIQ." — quiet and secondary, not shouting. | |
| 15 | Look at the bottom of the panel. | A small row: **Secure sign-in · Role-aware access · Governed intelligence**, on one line, each with an icon. **No compliance claim** — no SOC 2, ISO, GDPR or PDPA badge. | |
| 16 | Press **Tab** once. | Focus lands on the Microsoft button with a clearly visible focus ring. | |
| 17 | Check the browser tab. | The SemantIQ favicon, not a blank page icon. | |
| 18 | Read every word on the whole screen, slowly. | No spelling or grammar errors. No developer words, codes, "undefined", "null", version number, tenant name or customer name. Capitalisation is consistent. | |

### B. Signing in

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 19 | Click **Continue with Microsoft** and complete Microsoft sign-in. | You are returned to SemantIQ and land on the signed-in page. **Sign-in behaves exactly as it did before this release** — the same Microsoft account, the same prompts, the same outcome. Only the entrance was restyled. | |

### C. The navigation

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 20 | Look at the left-hand navigation. | Three headings, top to bottom: **SemantIQ Workplace**, then **Fabric Configuration**, then **System Administration**. | |
| 21 | Note which are open. | Workplace and Fabric are **closed**. System Administration is **open**. | |
| 22 | Read the System Administration entries. | In order: Administration Home · **Organisation** · Users & Groups · Roles & Access · Business Domains · Identity & SSO · Security Status · Access Reviews · Audit · System Health. | |
| 23 | Look at which entries carry a **Soon** pill. | Every entry except **Organisation** carries it. Organisation is the only one that looks available. | |
| 24 | Click the **SemantIQ Workplace** heading. | It opens: Home · My Intelligence · Explore · Ask SemantIQ · Insights · Risks & Opportunities · Recommendations · Decisions & Alerts · Reports & Dashboards · My Workspace · Help. All carry **Soon**. | |
| 25 | Click **My Intelligence**. | It opens to eight entries: Executive, Sales, Finance, People, Operations, Customer, Learning and Custom Intelligence. All carry **Soon**. | |
| 26 | Click the **Fabric Configuration** heading. | It opens: Overview · Data Sources · Connect Source · Discovery · Data Classification · Ingestion · Data Quality · Business Model · Security Mapping · Semantic Model · AI Readiness · Pipelines & Refresh · Power BI Publication · Monitoring. All carry **Soon**. | |
| 27 | **Read every label in the whole menu.** | Every label is fully readable. **Nothing is cut off** with a "…". Long names may run onto a second line; none is hidden. | |
| 28 | Check every icon. | Every entry has an icon. No missing icons, no broken image boxes, and **no entry shows a word like `building` or `i-sitemap` where an icon belongs**. | |
| 29 | Close each heading again by clicking it. | Each closes cleanly. | |

### D. Locked entries grant nothing (negative and security cases)

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 30 | Click **Audit**, then **Sales Intelligence**, then **Semantic Model**. | **Nothing happens.** No page loads, no error, no blank screen. The address in the browser bar does not change. | |
| 31 | Hover over any **Soon** entry. | No hand/link cursor and no underline: it does not behave like a link. | |
| 32 | Press **Tab** repeatedly through the menu. | Keyboard focus **skips** the Soon entries and stops only on Organisation and the real controls. | |

### E. The one delivered capability

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 33 | Click **Organisation**. | The Company Profile screen opens inside the same shell — the menu is still there on the left. **Do not save anything.** | |
| 34 | Look at the menu. | **Organisation** is now marked as the current page (highlighted with a coloured bar on its left edge). | |
| 35 | Use your browser's Back button. | You return to the previous page with the menu intact. | |

### F. Appearance (visual and UX checks)

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 36 | Find the three small appearance buttons in the top bar and click the **moon** (Dark). | The whole product turns dark: background, menu, top bar and page together. Nothing stays stranded in light colours. | |
| 37 | Look at the logo now. | **Exactly one** logo, the dark-background version, correctly proportioned. | |
| 38 | Reload the page. | It comes back **dark immediately** — no white flash before it settles. | |
| 39 | Click the **sun** (Light), then the **screen** (System). | Light applies immediately. System follows your computer's own setting. | |
| 40 | In dark mode, read the menu and the page. | Text is comfortably readable everywhere. No dark-on-dark or light-on-light. The **Soon** pills are still legible. | |
| 41 | Hover over Organisation, then Tab to it. | Hover gives a clear highlight; keyboard focus gives a clearly visible focus ring. | |

### G. Small screens

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| L1 | Sign out, then narrow the browser to a tablet width (about 900px). | Both panels stay side by side. Neither is crushed; the headline and the button are both still comfortable. | |
| L2 | Narrow further to a phone width (about 390px). | The layout stacks and **the sign-in panel comes FIRST**. "Continue with Microsoft" is visible without scrolling. The branded panel follows below it in a compact form. | |
| L3 | Try to scroll the Login page sideways. | **It does not move sideways.** | |
| L4 | Switch your computer to dark appearance and reload the Login page. | The dark branded panel is unchanged — it is a brand surface, so it stays Midnight Blue in both appearances. The sign-in panel follows the theme, and the divider between them is still visible so the two panels still read as two. | |
| L5 | In dark appearance, read the sign-in panel. | Every word is comfortably readable. Nothing is dark-on-dark. | |
| 42 | Sign in again. Narrow the browser window to roughly a phone width (about 390px). | The menu becomes a **narrow icon-only strip** on the left. It does **not** become a tall full-width list pushing the page down. | |
| 43 | Try to scroll the page sideways. | **The page does not move sideways.** | |
| 44 | Click the logo at the top of the narrow strip. | The full menu with labels comes back. **This is the important one:** confirm you can always get the menu back. | |
| 45 | Go to Organisation and look at any list with a table. | If a table is too wide, **the table itself** scrolls sideways. The menu and top bar stay put. | |
| 46 | Widen the window back to full size. | Everything returns to the normal layout with no leftover artefacts. | |

### H. Housekeeping

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 47 | Open your browser's developer console (F12) and reload. | **No red errors.** | |
| 48 | Click **Sign out**. | You are signed out and returned to the Login page. | |
| 49 | Press Back. | You are **not** returned to the signed-in product. | |

## 8. Negative, refusal and security cases

Covered above at steps 8, 13, 30, 31, 32, 48 and 49, plus:

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 50 | While signed out, open the production URL with `/console` on the end. | You land on the Login page. The menu is never shown. | |
| 51 | While signed out, view the Login page's source (Ctrl+U) and search it for `Audit`, `Sales`, `Semantic`. | **No match.** The roadmap is never sent to a signed-out browser. | |

## 9. Visual and UX checks

Steps 1–18 (the whole Login page), 27, 28, 36–41, L1–L5 and 42–46 are the visual and UX checks.

## 10. Evidence to capture

Please attach:

1. The Login page, light appearance, full window (both panels).
2. The Login page, dark appearance, full window.
3. The Login page at a phone width, showing the sign-in panel first.
4. The signed-in page with all three menu headings **open**, light theme.
5. The same, dark theme.
6. The narrow-window icon strip (step 42).
7. The narrow window after clicking the logo to bring the menu back (step 44).
8. The developer console from step 47.

## 11. PASS / FAIL

The right-hand column of every table above. Please return the script with the
column filled in, and a note against any FAIL.

## 12. What cannot currently be tested, and why

Stated plainly, and **not** inferred from a passing test:

1. **The Microsoft sign-in button WAS observed**, and this note is narrower
   than it was. Blueprint 0.2 withholds the button where Microsoft is not
   configured, so it was rendered by handing placeholder Microsoft settings to
   a throwaway local server as process environment only — no `.env` file, no
   Entra configuration and no production setting was touched. The button, its
   Microsoft logo, its focus ring and its position were verified in Chromium.
   **What was NOT exercised is the journey behind it**: no real Entra
   round-trip was performed, because that needs real credentials. Step 19 is
   the first real observation of the sign-in journey itself.

2. **The approved brand fonts could not be observed.** Montserrat and Source
   Sans 3 load from Google Fonts, which the development environment cannot
   reach, so every screenshot taken during development used fallback fonts.
   The font declaration is in the page and the CSS names real fallbacks.
   **Step 18 and step 40 are the first real observation of the
   intended typeface.**

3. **The reference screenshot mentioned in the design direction did not
   reach me.** The message carried the layout diagram and the full written
   specification but no image. This screen was built from that written
   direction and our own design standard. Step 1 and step 10 are the first
   comparison against the reference you had in mind.

4. **Nothing here exercises more than one user.** This unit adds no capability
   that involves a second person. The multi-user observation carried forward
   from P1-01 (`PHASE-1-PLAN.md` §10) is unaffected and still outstanding.

5. **No test asks you to enter business data**, so no check in this script is
   blocked by the rule against creating false organisational structure.

---

## Appendix — findings from the design-standard audit

Six defects were found by opening the real screens in a browser, all of which
the full automated suite had passed over. They are fixed in this build; each
now has a guard that was deliberately broken and observed to fail.

| Found | What it was | Now |
| --- | --- | --- |
| Login card, light theme | Both logo variants rendered at once; the second spilled outside the card. A CSS specificity slip: the hide rule was weaker than the show rule. | Fixed; every show/hide rule qualifies the image element, and a test enforces that. |
| Collapsed rail | The collapse control hid itself, leaving 43 unlabelled icons and no way to reopen the menu. | Fixed; the head is the expand control, and every row carries its name for screen readers and hover. |
| Menu labels | Twelve labels were cut off behind an ellipsis. | Fixed; measured in the browser at zero truncation across all 43 labels. |
| Organisation lists at 390px | The whole page scrolled sideways, chrome and all. | Fixed; the table scrolls inside its own box and the page does not move. |
| Login headline | Left to wrap on its own it broke as "in / moments." — an orphan that reads as an accident. | Fixed; set as the three deliberate lines the direction specifies. |
| Login trust row | The three indicators wrapped 2-and-1, which looks unintended. | Fixed; one tidy row at every width above a phone. |

The shell was also brought onto the shared standard's fixed Shell Dimensions
(240px rail / 56px collapsed, 52px top bar and rail head, 22px wide logo,
40×34 short-mark slot); it had shipped at 264px and 56px.
