# SemantIQ — Business User Demo Script

**Phase 1: System Administration.** A guided 25-minute walkthrough with a
complete data pack.

> ## ⚠️ READ THIS FIRST — WHERE YOU RUN THIS MATTERS
>
> **SemantIQ has no hard delete for users, organisations, domains or role
> assignments.** Anything you type in during a demo is permanent, real
> organisational history.
>
> | Where | What to run | Data |
> | --- | --- | --- |
> | **Production** (`semantiq.claas2saas.com`) | **PART A only** | **Create nothing.** Show the real organisation. |
> | **A demo / staging instance** | **PART A + PART B** | Use the data pack in §9. |
>
> **Do not run Part B on production.** Typing "Northwind Learning Group" into
> the live system would put fictional companies, departments and people into
> your real audit trail, permanently, where they cannot be removed.
>
> If you only have production, run **Part A**. It is a complete demo on its
> own and creates nothing.

---

## 1. What you are demonstrating

**SemantIQ decides who may see which business information, and can prove it.**

Phase 1 delivers the administration layer that makes that possible: the
organisation structure, the people, the roles, the business domains, and the
evidence that the controls are working.

**The one message to land:** in most systems, giving someone a role gives them
access. **In SemantIQ a role on its own grants nothing.** Access requires a
complete path — and the system will show you that path, or show you exactly
where it breaks.

## 2. Audience and length

Business stakeholders — heads of function, risk, compliance, operations. No
technical knowledge assumed. **About 25 minutes**, plus questions.

## 3. Before you start

- You are signed in as a **System Administrator** or **Organisation
  Administrator** (both now see the full administration surface).
- Sign-in is **Microsoft** — you will be asked for your work account.
- Use a normal browser window at full screen. Close other tabs.
- If you are demoing Part B, the data pack in §9 is already entered.
- Have this script open on a second screen or printed.

---

# PART A — THE READ-ONLY DEMO

**Creates nothing. Safe on production.**

## 4. Opening — the shape of the product (2 min)

**Do:** Sign in. Stop on the landing page and let the sidebar be seen.

**Say:**
> "Everything SemantIQ will ever do is already visible here, in two groups.
> **System Administration** is what we have delivered — every one of these
> eleven screens works today. **Fabric Configuration** is what comes next:
> connecting and modelling the data. Those entries are deliberately locked, so
> you can see where the product is going without anyone being able to wander
> into something unfinished."

**Point out:** the locked Fabric entries do not open. That is intentional —
the roadmap is visible, not clickable.

---

## 5. Administration Home — one honest summary (3 min)

**Do:** Open **Administration Home**.

**Say:**
> "This is the one screen that answers 'is this deployment actually set up?'
> It reads the real state of eleven other areas — it holds no numbers of its
> own, so it cannot tell you something different from the screen underneath."

**Point out:**
- The **Action Queue** — what genuinely needs attention, in plain language.
- If there is nothing outstanding, it says so rather than showing an empty box.

**Business point:** *"Nobody has to remember what was configured. The system
tells you what is missing."*

---

## 6. Organisation and People — the structure (4 min)

**Do:** Open **Organisation**. Walk the tabs: legal entities, business units,
departments, teams, reporting hierarchy.

**Say:**
> "This is your real company shape — the legal entities, the business units
> under them, departments, teams and who reports to whom. Everything else in
> SemantIQ refers back to this. When we say someone may see 'their team's
> records', *this* is what defines the team."

**Do:** Open **Users & Groups**.

**Say:**
> "People, and the groups they belong to. Creating a user here grants them
> nothing at all — it records that they exist. That surprises people, and it
> is the first half of the point I want to make."

**Business point:** *"Structure is recorded once, and everything else uses it."*

---

## 7. Business Domains — and the first surprise (3 min)

**Do:** Open **Business Domains**. Open one domain record — show that it has an
**owner**.

**Say:**
> "A business domain is a category of information — Finance, People, Sales,
> Learning Delivery. Each has an owner, who is accountable for it."

**Then pause and say:**
> "Here is the first thing that makes SemantIQ different. **Owning a domain
> grants no access to it.** The owner is accountable for who gets in — they do
> not automatically get in themselves. In a moment I will prove that."

**Business point:** *"Accountability and access are separate. Being
responsible for something is not the same as being able to read it."*

---

## 8. Roles & Access — the heart of the demo (7 min)

**Do:** Open **Roles & Access**.

**Say:**
> "This is where access is actually granted, and it takes four things — not
> one."

Walk the four, slowly, pointing at each on screen:

| # | The four-part path | In plain terms |
| --- | --- | --- |
| 1 | **Role** | What kind of person they are — Executive, Manager, Business User |
| 2 | **Entitlement** | Which business domain they have been let into |
| 3 | **Scope** | How much of it — only their own records, their team, their business unit, the whole domain, or the whole organisation |
| 4 | **Sensitivity ceiling** | How sensitive the information may be — Standard, Confidential, Restricted |

**Say:**
> "All four must line up. Miss any one and the answer is no. A role on its own
> grants nothing."

### The Access Simulator — the moment that lands

**Do:** Open the **Access Simulator**. Run these in order, reading the answer
aloud each time.

| # | Ask it | Expected answer | What you say |
| --- | --- | --- | --- |
| 1 | The **domain owner** of Finance, for Finance data | **Denied — no entitlement** | *"He owns it. He is accountable for it. He cannot read it, because nobody gave him an entitlement."* |
| 2 | A **Manager** with the role but no entitlement | **Denied — no entitlement** | *"Having the job title is not access."* |
| 3 | A **Business User** with scope 'Own records', asking for the team's data | **Denied — outside scope** | *"She is allowed in, but only to her own records."* |
| 4 | The same person, for a **Restricted** record, ceiling Confidential | **Denied — above sensitivity ceiling** | *"Right domain, right scope, information too sensitive."* |
| 5 | An **Executive** with all four in place | **Allowed** | *"And here is a complete path. Role, entitlement, scope, ceiling — all four."* |

**Say, after the last one:**
> "Four denials and one approval — and in every case it told us **which of the
> four was missing**. That is the difference between a system that says 'access
> denied' and one that can explain itself to an auditor."

**Business point:** *"You can answer 'why can't Daniel see this?' in ten
seconds, without a developer."*

---

## 9. Security Status, Access Reviews and Audit — the proof (4 min)

**Do:** Open **Security Status**.

**Say:**
> "What the controls are reporting right now — the baseline, security events,
> exceptions and privileged access. Every one of these screens is read-only.
> There is no button here that turns a control off."

**Do:** Open **Access Reviews**.

**Say:**
> "Periodically, someone has to confirm that the access people hold is still
> right. A review can **confirm** access or **remove** it. It can never create
> or widen access — so a review can only ever tighten things, never loosen
> them."

**Do:** Open **Audit**. Walk the four tabs.

**Say:**
> "And this is the record: who had access to what, what administrators
> changed, what configuration moved, and what security events occurred.
> Read-only. Nothing in the application can edit or delete an audit record."

**Business point:** *"When somebody asks 'who could see this, and when did
that change?' — that question has an answer."*

---

## 10. Identity, System Health and Integrations — the plumbing (2 min)

**Do:** Open **Identity & SSO**, then **System Health**, then **Integrations**.

> **⚠️ Look only. Do not edit a credential, do not send a test message, do not
> remove a secret.** These hold the live connection settings.

**Say:**
> "Sign-in is your Microsoft account — SemantIQ holds no passwords of its own.
> System Health reports whether the service is behaving. Integrations is where
> the platform connects outward. Secrets are masked and have to be revealed
> deliberately."

**Business point:** *"No new password for anyone to manage or lose."*

---

## 11. Closing (1 min)

> "Phase 1 gives you the administration layer: your structure, your people,
> who may see what, and the evidence that it is working. The decision is never
> one switch — it is four things lining up, and the system will tell you which
> one is missing.
>
> **Phase 2 is Fabric Configuration** — connecting and modelling the actual
> data. Everything you saw locked in the sidebar is next."

---

# PART B — THE DATA PACK

> ## ⚠️ NON-PRODUCTION INSTANCES ONLY
>
> These are **fictional** companies and people. Entering them into production
> would create permanent organisational history that cannot be deleted.

**Enter in this order** — each step depends on the one before.

## 12. Legal entities

| Name | Country | Registration |
| --- | --- | --- |
| Northwind Learning Group Pte Ltd | Singapore | NW-SG-2019-0441 |
| Northwind Academy Sdn Bhd | Malaysia | NW-MY-2021-7732 |

## 13. Business units

| Business unit | Legal entity |
| --- | --- |
| Corporate Training | Northwind Learning Group Pte Ltd |
| Professional Certification | Northwind Learning Group Pte Ltd |
| Digital Campus | Northwind Academy Sdn Bhd |

## 14. Departments

| Department | Business unit |
| --- | --- |
| Finance | Corporate Training |
| People & Culture | Corporate Training |
| Sales | Corporate Training |
| Delivery | Professional Certification |
| Technology | Digital Campus |

## 15. Teams

| Team | Department |
| --- | --- |
| Enterprise Sales | Sales |
| Certification Delivery | Delivery |
| Data & Platform | Technology |

## 16. People

Use a clearly fictional mail domain so no real person is ever contacted.

| Display name | Email | Department | Team |
| --- | --- | --- | --- |
| Arjun Mehta | arjun.mehta@northwind.example | Finance | — |
| Priya Raman | priya.raman@northwind.example | Finance | — |
| Daniel Okafor | daniel.okafor@northwind.example | Sales | Enterprise Sales |
| Mei Lin Tan | meilin.tan@northwind.example | Delivery | Certification Delivery |
| Sofia Alvarez | sofia.alvarez@northwind.example | Technology | Data & Platform |

## 17. Groups

| Group | Members |
| --- | --- |
| Finance Leadership | Arjun Mehta, Priya Raman |
| Enterprise Sales Team | Daniel Okafor |

## 18. Business domains

| Domain | Owner | Enabled |
| --- | --- | --- |
| **Finance** | **Arjun Mehta** | Yes |
| People | Priya Raman | Yes |
| Sales | Daniel Okafor | Yes |
| Learning Delivery | Mei Lin Tan | Yes |

**Arjun owns Finance and is given no Finance entitlement. That is deliberate —
it is demo moment 1.**

## 19. Role assignments — built to make each point

| Person | Role | Entitlement | Scope | Ceiling | Demonstrates |
| --- | --- | --- | --- | --- | --- |
| **Arjun Mehta** | Domain Owner | **none** | — | — | **Owning a domain grants nothing** |
| **Daniel Okafor** | Manager | **none** | — | — | **A role alone grants nothing** |
| **Mei Lin Tan** | Business User | Learning Delivery | **Own records** | Standard | **Scope limits reach** |
| **Priya Raman** | Executive | Finance | Organisation | **Confidential** | **Ceiling limits sensitivity** |
| **Sofia Alvarez** | Auditor | — | — | — | **Evidence without business data** |

## 20. Simulator scenarios — the exact five to run

| # | Person | Domain | Sensitivity | Expected |
| --- | --- | --- | --- | --- |
| 1 | Arjun Mehta | Finance | Standard | **Denied — no entitlement** |
| 2 | Daniel Okafor | Sales | Standard | **Denied — no entitlement** |
| 3 | Mei Lin Tan | Learning Delivery | Standard, **team record** | **Denied — outside scope** |
| 4 | Priya Raman | Finance | **Restricted** | **Denied — above ceiling** |
| 5 | Priya Raman | Finance | Confidential | **ALLOWED** |

**Run them in this order.** Four denials then one approval is what makes the
point; leading with the approval wastes it.

## 21. Optional closer — the Auditor

Sign in as **Sofia Alvarez** (Auditor).

**Expected:** she reaches **Security Status**, **Access Reviews** and **Audit**
— and **no administration areas, and no business data**.

**Say:**
> "An auditor can see the evidence that controls are working, without being
> able to see the business information itself, and without being able to
> change anything."

---

## 22. What this demo does NOT show, and why

Say this if asked — it is better coming from you than being discovered.

| Not shown | Why |
| --- | --- |
| **Actual business data** — dashboards, reports, insights | **Phase 3.** Phase 1 decides *who may see* information; the information itself is not connected yet. |
| **Connecting a data source** | **Phase 2 — Fabric Configuration.** Visible in the sidebar, locked on purpose. |
| **Deleting a user or a domain** | **There is no hard delete.** Records are deactivated, never erased — deliberate, so history stays intact. Say so plainly if asked. |
| **Changing identity or integration settings** | Live credentials. The demo looks, never edits. |

## 23. Questions you will get

**"Can an administrator give themselves access to everything?"**
> An Organisation Administrator administers the organisation — people, roles,
> domains, and the platform settings. They do **not** get business data by
> holding an administration role, and they cannot create a System
> Administrator. Administration authority and business-data authority are
> separate on purpose.

**"What if someone changes access and nobody notices?"**
> Administrator changes are recorded in Audit, and Access Reviews bring the
> access people hold back for confirmation. A review can remove access; it can
> never create it.

**"How do we know the controls actually work?"**
> Security Status reports what the controls say right now, and Audit holds the
> history. Neither screen has a button that turns anything off.

**"Can we see why someone was denied?"**
> That is the Access Simulator, and it names which of the four was missing —
> role, entitlement, scope, or sensitivity.

---

## 24. A note for whoever sets up the demo environment

- **`php artisan db:seed` does not work.** The stock `DatabaseSeeder`
  references a `User` model this application does not have, and there are no
  factories. **Do not run it** — enter the data pack through the screens
  instead, which is also a more honest rehearsal of the demo.
- Sign-in is Microsoft Entra SSO, so a demo instance needs identity configured
  before anyone can sign in.
- Entering §12–§19 through the UI takes roughly **30–40 minutes**. Do it the
  day before, not on the morning of.
