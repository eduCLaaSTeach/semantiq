# Product Owner Test Script — UI, Brand & Navigation Foundation

Written for the Product Owner, in your words, on your screens. Required by
`CLAUDE.md` §3. Every one of the twelve required parts is present.

---

## 1. Feature or task being tested

**UI, Brand & Navigation Foundation.** The professional look and feel, the
CLaaS2SaaS branding, the redesigned Login page, and the complete approved
navigation with every future entry visible but locked.

This unit builds **no new capability**. Organisation (P1-01) is still the only
screen behind the menu. Everything else is a label.

## 2. Deployed build / merge SHA

| | |
| --- | --- |
| Branch | `claude/semantiq-github-review-a9jaqg` |
| Commit | `11b6fa9` — *UI, brand and navigation foundation* |
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

One standing warning still applies, because step 22 puts you on a screen that
can save: **SemantIQ has no hard delete for organisation structure.** If you
type into the Company Profile form and press *Save changes*, that change is
real and is recorded. **Do not save anything during this test.** Look at the
screen and move on.

## 6–7. Numbered steps and expected results

Record PASS or FAIL for every step. Where a step has several checks, it is FAIL
if any one of them fails; note which.

### A. The Login page

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 1 | Open the SemantIQ production URL while signed out. | The Login page appears: a single centred card on a plain background. No menu, no sidebar, no product areas. | |
| 2 | Look at the top of the card. | The **CLaaS2SaaS** logo sits at the top. Below it, the product name **SemantIQ**. Company brand above product brand, in that order. | |
| 3 | Look closely at the logo. | **Exactly one** logo. It is sharp, correctly proportioned, not stretched, not recoloured, not in a box, and it does not spill outside the card. | |
| 4 | Read the four lines of copy. | Word for word: **"Turn business data into confident decisions."** · **"See what changed. Understand why. Decide what's next."** · **"SemantIQ brings governed data, business context and intelligent insights together in one secure decision-intelligence experience."** · footer **"Access is assigned by your organisation's administrator. Contact them if you cannot sign in."** | |
| 5 | Look at the button. | One button: **"Sign in with Microsoft"**. No other sign-in method, no username or password field. | |
| 6 | Check the browser tab. | The tab shows the SemantIQ favicon, not a blank page icon. | |
| 7 | Read the whole page once more, slowly. | No spelling or grammar errors. No developer words, no codes, no "undefined", no "null", no version number, no tenant or customer name. | |

### B. Signing in

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 8 | Click **Sign in with Microsoft** and complete Microsoft sign-in. | You are returned to SemantIQ and land on the signed-in page. Sign-in behaves exactly as it did before this release — nothing about it has changed. | |

### C. The navigation

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 9 | Look at the left-hand navigation. | Three headings, top to bottom: **SemantIQ Workplace**, then **Fabric Configuration**, then **System Administration**. | |
| 10 | Note which are open. | Workplace and Fabric are **closed**. System Administration is **open**. | |
| 11 | Read the System Administration entries. | In order: Administration Home · **Organisation** · Users & Groups · Roles & Access · Business Domains · Identity & SSO · Security Status · Access Reviews · Audit · System Health. | |
| 12 | Look at which entries carry a **Soon** pill. | Every entry except **Organisation** carries it. Organisation is the only one that looks available. | |
| 13 | Click the **SemantIQ Workplace** heading. | It opens: Home · My Intelligence · Explore · Ask SemantIQ · Insights · Risks & Opportunities · Recommendations · Decisions & Alerts · Reports & Dashboards · My Workspace · Help. All carry **Soon**. | |
| 14 | Click **My Intelligence**. | It opens to eight entries: Executive, Sales, Finance, People, Operations, Customer, Learning and Custom Intelligence. All carry **Soon**. | |
| 15 | Click the **Fabric Configuration** heading. | It opens: Overview · Data Sources · Connect Source · Discovery · Data Classification · Ingestion · Data Quality · Business Model · Security Mapping · Semantic Model · AI Readiness · Pipelines & Refresh · Power BI Publication · Monitoring. All carry **Soon**. | |
| 16 | **Read every label in the whole menu.** | Every label is fully readable. **Nothing is cut off** with a "…". Long names may run onto a second line; none is hidden. | |
| 17 | Check every icon. | Every entry has an icon. No missing icons, no broken image boxes, and **no entry shows a word like `building` or `i-sitemap` where an icon belongs**. | |
| 18 | Close each heading again by clicking it. | Each closes cleanly. | |

### D. Locked entries grant nothing (negative and security cases)

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 19 | Click **Audit**, then **Sales Intelligence**, then **Semantic Model**. | **Nothing happens.** No page loads, no error, no blank screen. The address in the browser bar does not change. | |
| 20 | Hover over any **Soon** entry. | No hand/link cursor and no underline: it does not behave like a link. | |
| 21 | Press **Tab** repeatedly through the menu. | Keyboard focus **skips** the Soon entries and stops only on Organisation and the real controls. | |

### E. The one delivered capability

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 22 | Click **Organisation**. | The Company Profile screen opens inside the same shell — the menu is still there on the left. **Do not save anything.** | |
| 23 | Look at the menu. | **Organisation** is now marked as the current page (highlighted with a coloured bar on its left edge). | |
| 24 | Use your browser's Back button. | You return to the previous page with the menu intact. | |

### F. Appearance (visual and UX checks)

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 25 | Find the three small appearance buttons in the top bar and click the **moon** (Dark). | The whole product turns dark: background, menu, top bar and page together. Nothing stays stranded in light colours. | |
| 26 | Look at the logo now. | **Exactly one** logo, the dark-background version, correctly proportioned. | |
| 27 | Reload the page. | It comes back **dark immediately** — no white flash before it settles. | |
| 28 | Click the **sun** (Light), then the **screen** (System). | Light applies immediately. System follows your computer's own setting. | |
| 29 | In dark mode, read the menu and the page. | Text is comfortably readable everywhere. No dark-on-dark or light-on-light. The **Soon** pills are still legible. | |
| 30 | Hover over Organisation, then Tab to it. | Hover gives a clear highlight; keyboard focus gives a clearly visible focus ring. | |

### G. Small screens

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 31 | Narrow the browser window to roughly a phone width (about 390px). | The menu becomes a **narrow icon-only strip** on the left. It does **not** become a tall full-width list pushing the page down. | |
| 32 | Try to scroll the page sideways. | **The page does not move sideways.** | |
| 33 | Click the logo at the top of the narrow strip. | The full menu with labels comes back. **This is the important one:** confirm you can always get the menu back. | |
| 34 | Go to Organisation and look at any list with a table. | If a table is too wide, **the table itself** scrolls sideways. The menu and top bar stay put. | |
| 35 | Widen the window back to full size. | Everything returns to the normal layout with no leftover artefacts. | |

### H. Housekeeping

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 36 | Open your browser's developer console (F12) and reload. | **No red errors.** | |
| 37 | Click **Sign out**. | You are signed out and returned to the Login page. | |
| 38 | Press Back. | You are **not** returned to the signed-in product. | |

## 8. Negative, refusal and security cases

Covered above at steps 19, 20, 21, 37 and 38, plus:

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 39 | While signed out, open the production URL with `/console` on the end. | You land on the Login page. The menu is never shown. | |
| 40 | While signed out, view the Login page's source (Ctrl+U) and search it for `Audit`, `Sales`, `Semantic`. | **No match.** The roadmap is never sent to a signed-out browser. | |

## 9. Visual and UX checks

Steps 2, 3, 6, 7, 16, 17, 25–30 and 31–35 are the visual and UX checks.

## 10. Evidence to capture

Please attach:

1. The Login page, light theme, full window.
2. The signed-in page with all three menu headings **open**, light theme.
3. The same, dark theme.
4. The narrow-window icon strip (step 31).
5. The narrow window after clicking the logo to bring the menu back (step 33).
6. The developer console from step 36.

## 11. PASS / FAIL

The right-hand column of every table above. Please return the script with the
column filled in, and a note against any FAIL.

## 12. What cannot currently be tested, and why

Stated plainly, and **not** inferred from a passing test:

1. **The "Sign in with Microsoft" button could not be observed in a browser
   during development.** Blueprint 0.2 withholds the button on a deployment
   where Microsoft is not configured, and the development environment is not.
   What was verified automatically is the *condition* and the *destination*:
   the button is offered only when Microsoft is configured, and it points at
   `/auth/microsoft/redirect`, unchanged from P1-00. **Step 5 is the first real
   observation of that button, and step 8 the first of the journey behind it.**

2. **The approved brand fonts could not be observed.** Montserrat and Source
   Sans 3 load from Google Fonts, which the development environment cannot
   reach, so every screenshot taken during development used fallback fonts.
   The font declaration is in the page and the CSS names real fallbacks.
   **Step 7 and step 29 are the first real observation of the intended
   typeface.**

3. **Nothing here exercises more than one user.** This unit adds no capability
   that involves a second person. The multi-user observation carried forward
   from P1-01 (`PHASE-1-PLAN.md` §10) is unaffected and still outstanding.

4. **No test asks you to enter business data**, so no check in this script is
   blocked by the rule against creating false organisational structure.

---

## Appendix — findings from the design-standard audit

Four defects were found by opening the real screens in a browser, all of which
the full automated suite had passed over. They are fixed in this build; each
now has a guard that was deliberately broken and observed to fail.

| Found | What it was | Now |
| --- | --- | --- |
| Login card, light theme | Both logo variants rendered at once; the second spilled outside the card. A CSS specificity slip: the hide rule was weaker than the show rule. | Fixed; every show/hide rule qualifies the image element, and a test enforces that. |
| Collapsed rail | The collapse control hid itself, leaving 43 unlabelled icons and no way to reopen the menu. | Fixed; the head is the expand control, and every row carries its name for screen readers and hover. |
| Menu labels | Twelve labels were cut off behind an ellipsis. | Fixed; measured in the browser at zero truncation across all 43 labels. |
| Organisation lists at 390px | The whole page scrolled sideways, chrome and all. | Fixed; the table scrolls inside its own box and the page does not move. |

The shell was also brought onto the shared standard's fixed Shell Dimensions
(240px rail / 56px collapsed, 52px top bar and rail head, 22px wide logo,
40×34 short-mark slot); it had shipped at 264px and 56px.
