# P1-02 — Identity & SSO — PRODUCT OWNER TEST SCRIPT

Written for the Product Owner: your words, your screens, your decisions.

---

## 1. Feature being tested

**Identity & SSO administration** — five read-only screens under
**System Administration → Identity & SSO**, plus one action (*Re-check now*),
and a correction to how long a signed-in session lasts.

## 2. Deployed build

| | |
| --- | --- |
| DESIGN merge SHA | `0f0a45be11b0905cb12c8abad3273aa77ea4fffd` |
| Implementation merge SHA | `8a812836270826ad5dd6b2e7af21cb9d15161621` (PR #78) |
| Deployment run | #102 — succeeded 2 September 2026, including the session-policy correction |
| Production verification | *Verify P1-02 identity state* run #1 — succeeded |

## 3. Preconditions

- You are signed in to SemantIQ as **salil@lithan.com** (System Administrator).
- The deployment carrying P1-02 has completed.

## 4. Test data required

**None.** Nothing in this unit creates, changes or deletes a business record.
There is nothing to type, nothing to name, and nothing that will persist
afterwards. **This is the first unit with no test-data warning to give.**

## 5. ⚠ READ THIS BEFORE STEP 1 — one thing does change for everybody

> **You will be signed out sooner than you are used to.**
>
> SemantIQ's approved policy is that an idle session ends after **60 minutes**.
> The server has been enforcing **120** — the setting said one thing and the
> approved policy said another, and nothing was checking. This release corrects
> it.
>
> On the deployment carrying this change, **anyone whose session has been idle
> for more than an hour is asked to sign in again at their next click**,
> including anyone who was signed in when the deployment started. Anyone idle for
> less than an hour is unaffected. From now on, an hour of inactivity ends a
> session instead of two.
>
> **Nothing is lost but the session.** Signing in again restores everything, and
> no data is affected. But someone who leaves a screen open over lunch will now
> be asked to sign in again, and they should hear it from you rather than
> discover it.
>
> This is not a regression. 60 minutes is the approved policy; 120 was the
> mistake, and the mistake is what changed.

---

## 6–7. Steps, and what you should see

| # | Do this | Expect | PASS / FAIL |
| --- | --- | --- | --- |
| 1 | In the left menu, open **System Administration** and click **Identity & SSO** | The entry is a real link, not marked *Soon*. You land on **Microsoft Entra ID** | |
| 2 | Read the five tabs across the top | **Microsoft Entra ID · Other Identity Providers · Login Experience · SSO Health · Session Policy**. The one you are on is highlighted | |
| 3 | On **Microsoft Entra ID**, find **Client secret** | It reads **Present** — and nothing else. No value, no part of a value, no length | |
| 4 | Find **Directory (tenant)** | Shown in part, like `a1b2c3d4…9f8e`, with a **Reveal** button | |
| 5 | Click **Reveal** | The full directory identifier appears, and a **Copy** button appears beside it | |
| 6 | Click **Copy**, then paste somewhere | The button says **Copied** and the pasted value is the identifier you just saw | |
| 7 | Click **Hide** | It goes back to the shortened form | |
| 8 | Look for any way to *change* anything on this screen | **There is none.** No text box, no dropdown, no Save. The screen says these settings are held on the server | |
| 9 | Open **Other Identity Providers** | **Microsoft Entra ID**, marked *Approved and in use*. Below: *"No other identity provider is approved"*, and that adding one needs your approval | |
| 10 | Look for Google, Okta or any other provider | **None is listed** — not greyed out, not "coming soon", not a switch | |
| 11 | Open **Login Experience** | A table of the sign-in page's parts and who decides each. Every row says *Not changed here*, except *Whether Microsoft sign-in appears at all*, which says *Reported, not set* | |
| 12 | Open **SSO Health** | An overall result at the top, then one row per check, each saying what it means | |
| 13 | Read the line under the overall result | It says these checks do not sign anyone in and **cannot tell whether the client secret is close to expiring**. That limit is stated on purpose | |
| 14 | Read the row **Microsoft reachable (live check)** | Straight after a deployment it says *"No live check has been run on this deployment yet."* **This is information, not a warning** | |
| 15 | Click **Re-check now** | The button shows it is working, then a green confirmation: **"Health re-checked."** The Microsoft reachable row now says when it was reached | |
| 16 | Click **Re-check now** again, straight away | A refusal: **"Health was checked moments ago. Try again shortly."** No countdown, no technical detail | |
| 17 | Wait a minute, then click **Re-check now** once more | It works again | |
| 18 | Open **Session Policy** | **Idle timeout 60 minutes**, **Maximum session length 12 hours**, **Account re-check: Every request. Always on.**, and where sessions are stored | |
| 19 | Look for any way to change those values | **There is none.** The screen explains that they are a security control and that changing one needs a durable record of who changed it, which a later release provides | |
| 20 | Switch the theme (sun / moon in the top bar) and revisit each of the five tabs | Everything stays readable in both. No text disappears, no colour becomes unreadable | |
| 21 | Narrow the browser window to about half width, then to phone width | Nothing runs off the side of the screen. Long sentences wrap; they are not cut off | |

---

## 8. Negative and security cases

| # | Check | Expect | PASS / FAIL |
| --- | --- | --- | --- |
| 22 | On every one of the five screens, look for the client secret, a password, a token or a key | **Nothing of the sort appears anywhere** | |
| 23 | Look for any raw setting names on a normal, working screen | None. Setting names appear **only** when something is missing, where naming it is the useful information | |
| 24 | Look for any account name or email address on any Identity screen | **None.** These screens report on the sign-in provider, never on people | |
| 25 | Sign out, then paste `…/console/identity` into the address bar | You are sent to the sign-in page. Nothing about the screen is disclosed | |

---

## 9. Visual and UX checks

| # | Check | PASS / FAIL |
| --- | --- | --- |
| 26 | Every result carries a **word** — *Healthy*, *Needs attention*, *Sign-in unavailable*, *Not checked* — not just a colour | |
| 27 | Every result that is not *Healthy* tells you **what to do about it** | |
| 28 | Nothing on any screen reads like developer text, an error code or a database term | |
| 29 | No pop-up errors, and nothing looks broken or misaligned | |
| 30 | Would you be comfortable showing these five screens to a customer? | |

## 10. Evidence to capture

- A screenshot of each of the five tabs, in **both** themes.
- The confirmation after **Re-check now**, and the refusal after the second press.
- The **SSO Health** screen with its rows visible.
- One narrow-window screenshot.

## 11. PASS / FAIL

Recorded per step above.

## 12. What cannot be tested yet, and why

Carried honestly. **None of this is inferred from a passing automated test.**

1. **A non-administrator being refused.** Production has one user account, so
   there is nobody to sign in as. **NOT CURRENTLY OBSERVABLE WITH REAL
   PRODUCTION DATA.** The automated evidence covers every screen and both
   actions, and the live observation is **carried to P1-03**, which owns user
   provisioning. **You are not asked to create a fake user for this.**
2. **A real Microsoft outage.** The *Sign-in unavailable* and *Needs attention*
   states — including a failed live check while cached settings still work — are
   proven by automated evidence. **They are not staged in production**, because
   doing so would break sign-in for real people.
3. **Two administrators pressing Re-check within five minutes of each other.**
   The provider-wide limit that stops repeated checks becoming repeated requests
   to Microsoft needs a second account to observe. **Carried to P1-03.**
4. **Whether the client secret is close to expiring.** SemantIQ **cannot see
   this at all**, and the screen says so. It would need a different permission
   from Microsoft, which this unit deliberately does not ask for. **Named as a
   known limit, not a check that passed.**
5. **The 60-minute idle timeout, observed by waiting.** You can confirm it by
   leaving SemantIQ untouched for just over an hour and then clicking — it will
   ask you to sign in again. It is proven automatically, and if you would rather
   not spend an hour on it, **the Session Policy screen shows the value actually
   in force**, which is the same number the system applies.
