# Apache Denial-Capability Diagnostic

**Status:** COMPLETE — result accepted by the Product Owner, 31 August 2026.
**Result:** **APACHE ROOT DENIAL MECHANISM VERIFIED.** All four mechanisms returned 403.
**Probes removed** in the follow-up security-cleanup change; the `ErrorDocument 403`
instrument was made permanent with a neutral body.
**Scope:** Diagnostic only. No application behaviour changes, no schema changes,
no `.env` change, no APP_KEY change, no manual server edits.
**Relates to:** `DEPLOYMENT-LAYOUT-AMENDMENT.md` (D-08B), PR #37 findings.

---

## 1. The question

Under D-08B the document root is `public_html` and the whole Laravel tree is
deployed inside it. Today a single `.htaccess` catch-all rewrites every request
into `public/`, and that forwarder is what actually keeps the tree unreachable.
The deny rules that sit above it have **never been observed to fire**.

The layout amendment moves the front controller to `public_html/index.php` and
removes that forwarder. At that moment the deny rules stop being belt-and-braces
and become the only guard. So the capability has to be demonstrated first.

**Objective:** determine which, if any, Apache/`.htaccess` denial mechanism
available on this host can reliably prevent direct access to a file or path in
`public_html`.

---

## 2. What PR #37 established, and what it could not

| Fact | Evidence |
| --- | --- |
| The server `.htaccess` is the repository file | SHA-256 match, enforced in the deploy workflow |
| `RewriteRule … [F,L]` does not yield 403 | Synthetic probe returned **404** |
| mod_rewrite is active and `[L]` passthrough works | `/.well-known/acme-challenge/` returns 200 |
| Nothing is exposed | 29-path exposure suite passes; no 200, no secret marker |

What PR #37 **could not** settle: the probe's 404 body was byte-identical to an
ordinary Laravel 404. That single observation is equally consistent with two
opposite explanations —

1. the deny rule never fired, and the request simply reached Laravel; or
2. the deny rule **did** fire, and the 403 was laundered back into a 404.

Explanation 2 is mechanically plausible. Apache serves a denial through its
`ErrorDocument` chain; if that chain resolves to a local URL it re-enters the
rewrite engine, meets the catch-all, lands in Laravel and returns 404. The
response body cannot separate the two cases, because both produce the same body.

That ambiguity is why this diagnostic needs an instrument as well as probes.

---

## 3. Design

### 3.1 Three mechanisms, not one

"Denial" is not a single feature. Each of these is a different module, with a
different activation phase and a different `AllowOverride` requirement.

| | Mechanism | Directive | Module |
| --- | --- | --- | --- |
| **A** | mod_rewrite, explicit status | `RewriteRule ^__semantiq_rewrite_403_probe(/\|$) - [R=403,L]` | mod_rewrite |
| **A′** | mod_rewrite, `[F]` control | `RewriteRule ^__semantiq_apache_deny_probe(/\|$) - [F,L]` | mod_rewrite |
| **B** | mod_alias | `RedirectMatch 403 ^/?__semantiq_alias_403_probe(/\|$)` | mod_alias |
| **C** | mod_authz_core | `<Files "__semantiq_files_deny_probe.txt"> Require all denied` | mod_authz_core |

A′ is the probe already measured in PR #37, retained so `[F]` and `[R=403]` are
compared **in the same request cycle against the same server state** rather than
across two deployments.

### 3.2 The instrument

```apache
ErrorDocument 403 "SEMANTIQ_403_ERRORDOCUMENT_MARKER"
```

A **quoted literal** ErrorDocument is answered from memory: no internal redirect,
no second pass through the rewrite engine, no Laravel. It closes explanation 2
above. If a denial is firing, the status now arrives intact and the marker
appears in the body.

This is an instrument, not a fourth denial mechanism. Without it all three probes
could report 404 while a denial was in fact working, and the single authorised
diagnostic would produce a **false negative** — after which the hosting provider
would be asked the wrong question.

Blast radius is nil: nothing on this site currently returns 403; that absence is
precisely the finding under investigation. `ErrorDocument 404` is deliberately
**not** overridden, so Laravel's own not-found page is untouched.

### 3.3 Why mechanism C is the only falsifiable probe

A and B deny a path where nothing exists. If they fail, the request falls through
to the forwarder and Laravel answers 404 — **the same 404 the path would produce
with no rule at all**. Their negative result is unfalsifiable: absence of
evidence, not evidence of absence.

C is built the other way round. The deploy workflow writes a real, harmless file
at `public/__semantiq_files_deny_probe.txt` — *inside* `public/`, where the
forwarder genuinely serves it — containing one marker line, `SEMANTIQ_DENY_DIAGNOSTIC_ONLY`,
and nothing else.

| Result | Reading |
| --- | --- |
| **403** | `<Files>` + `Require all denied` **works** |
| **200** with the marker body | The file was reachable and the directive **did not deny** it. A real negative |
| **404** | The file was never written. **Inconclusive** — the probe did not run |

A sibling control file, `public/__semantiq_files_control_probe.txt`, carries the
same body and **no** deny directive. It is fetched in the same step and must
return 200. Without it, a 403 on the guarded file could be some unrelated blanket
refusal of `.txt` under `public/` rather than this directive doing its job.

Per the Product Owner's constraint: **a 404 generated by the forwarder or Laravel
is not evidence that the tested mechanism works.** The interpretation logic
refuses to read any 404 as a positive.

### 3.4 Safety

| Constraint | How it is met |
| --- | --- |
| No real protected file used as a probe | Every probe path is synthetic; the only files created contain one marker line |
| Forwarder not weakened | The catch-all and every production deny rule are unchanged; `ApacheDenyProbeTest::test_the_production_deny_rules_are_intact` enforces it |
| `.env` protection not weakened | Untouched; `.env` and `/vendor/` are re-asserted as controls, and a 200 on either fails the step |
| No permanent public diagnostic file | Files are created over SSH after the sync and removed by a `trap … EXIT` that fires on success, failure and cancellation. Neither file is in the repository, so the next `rsync --delete` is the backstop |
| No manual server edits | Everything runs through GitHub Actions over SSH |
| No secret in logs | Only statuses, byte counts and marker names are printed; every response body is scanned for secret markers and server paths, and a hit fails the step |
| ACME renewal preserved | `.well-known/` passthrough stays first; a 403 there fails the step |

---

## 4. Guards

`tests/Architecture/ApacheDenyProbeTest.php`. A diagnostic that cannot fail
proves nothing, so each guard was **deliberately broken and observed to fail**:

| # | Deliberate break | Guard that caught it |
| --- | --- | --- |
| 1 | Probe A moved after the catch-all | `test_both_rewrite_probes_precede_the_catch_all_forwarder` |
| 2 | Mechanism A flag changed to `[F,L]` | `test_mechanism_a_denies_with_an_explicit_403_status` |
| 3 | Mechanism B moved inside the rewrite block | `test_mechanism_b_sits_outside_the_rewrite_block` |
| 4 | A rewrite rule added that shadows the mechanism C file | `test_no_rewrite_deny_rule_shadows_the_mechanism_c_probe_file` |
| 5 | ErrorDocument changed from a literal to a path | `test_the_error_document_instrument_is_a_literal_string` |
| 6 | The control file given a deny directive | `test_the_mechanism_c_control_file_carries_no_deny_directive` |
| 7 | A production deny rule removed | `test_the_production_deny_rules_are_intact` |
| 8 | The cleanup trap removed from the workflow | `test_the_workflow_removes_the_probe_files_on_every_exit_path` |
| 9 | Mechanism C file created outside `public/` | `test_the_mechanism_c_probe_files_are_created_inside_public` |
| 10 | A real file committed at a probe path | `test_no_probe_file_is_committed_to_the_repository` |

Guard 4 matters most. If any rewrite deny rule matched the mechanism C file
first, a 403 would come from **mod_rewrite** and be misread as a `<Files>`
success — exactly the confusion this diagnostic exists to end.

The diagnostic step itself was rehearsed against stubbed responses before it ran
against production. That rehearsal found two real defects, both fixed here:

- the cleanup verification parsed a count out of an SSH stream, which an ordinary
  cPanel login banner would have corrupted — it now uses an explicit marker and
  keeps an honest "could not verify" state;
- the secret scan ran once after the probe loop, so it only ever examined the
  **last** response body — it now scans every response as it is read.

---

## 5. Decision rules

Fixed in advance, so the result is not interpreted after the fact.

| Outcome | Conclusion | Next step |
| --- | --- | --- |
| Any mechanism returns 403 | **APACHE ROOT DENIAL MECHANISM VERIFIED** | Report which one; propose its use in the layout amendment; **stop** |
| All mechanisms fail, C returns 200 with the marker | **HOST-LEVEL APACHE DENIAL CAPABILITY NOT AVAILABLE / NOT PROVEN THROUGH CURRENT `.htaccess` CONFIGURATION** | Stop code experiments; prepare hosting-provider questions |
| Unexpected content exposed | **Security failure** | Restore via Git, stop, report |

In every case the probes are removed by a follow-up change once the result is
recorded. Nothing in this diagnostic is intended to remain in the codebase.

---

## 6. Result — measured 31 August 2026

Deployment run
[33349882458](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33349882458)
on `d44f8e0`. All 26 steps succeeded. `.htaccess` checksum **MATCH**.

| Probe | Path | Status | Bytes | Marker | Verdict |
| --- | --- | --- | --- | --- | --- |
| **A** mod_rewrite `[R=403,L]` | `/__semantiq_rewrite_403_probe` | **403** | 33 | `403-errordocument` | **VERIFIED** |
| **A′** mod_rewrite `[F,L]` | `/__semantiq_apache_deny_probe` | **403** | 33 | `403-errordocument` | **VERIFIED** |
| **B** mod_alias `RedirectMatch 403` | `/__semantiq_alias_403_probe` | **403** | 33 | `403-errordocument` | **VERIFIED** |
| **C** `<Files>` + `Require all denied` | `/__semantiq_files_deny_probe.txt` | **403** | 33 | `403-errordocument` | **VERIFIED** |
| **C-control** unguarded sibling | `/__semantiq_files_control_probe.txt` | **200** | 30 | `probe-file-body` | control held |

Controls: absent path `404` (6586 B, Laravel) · `/` `200` · `/up` `200` ·
`/console` `302` · `/.well-known/` `200` · `/.env` **403** · `/vendor/` **403**.
Both probe files removed; independently re-verified from outside the pipeline.

### 6.1 The decisive observation

`.env` and `/vendor/` returned **404 in every previous run** and **403** here,
with the rewrite rules byte-for-byte unchanged. Rule evaluation does not depend
on `ErrorDocument`, so those rules **must have been firing all along**.

Mechanism C carries the weight: the guarded file returned 403 while its
unguarded sibling, in the same directory in the same request cycle, returned 200
with the diagnostic body. The file was genuinely reachable and the directive
refused it — a falsifiable positive, not an absence of evidence.

### 6.2 What this changes

- The PR #37 negative was a **false negative** caused by ErrorDocument masking.
  Adding the instrument was what prevented the wrong question being taken to the
  hosting provider.
- `AllowOverride` was never the cause. Declining to assert it as fact was right.
- The security objection to the permanent `public_html` root layout is
  **resolved**: Apache refuses at the document root by four mechanisms, and
  mechanism C proves the refusal holds against a file that genuinely exists —
  the exact condition after the forwarder is removed.

### 6.3 What became permanent

| Kept | Why |
| --- | --- |
| `ErrorDocument 403`, neutral literal body | Without it a denial reports 404 and the boundary is unobservable. It reveals no framework, path, trace or configuration |
| Exposure gate **requires 403** | A 404 now means a fall-through into Laravel — a boundary regression — and fails the deployment |
| `.htaccess` SHA-256 verification | Under D-08B this file *is* the boundary; the server copy must be the reviewed copy |
| `ApacheDenialBoundaryTest` | Eight guards, each deliberately broken and observed to fail |

Every synthetic probe path, the marker string and the diagnostic workflow step
were removed. No diagnostic endpoint remains in production, and a test enforces
that.

---

## 7. Permanent hosting architecture

```text
cPanel document root          = public_html
deployment root               = public_html
production front controller   = public_html/index.php
public_html/public            = NOT a required production layer
```

`public_html/public/` was only an early pre-project Git-to-cPanel
synchronisation test location. It is not part of the intended production
architecture and the document root will not be repointed to it. The repository
keeps its normal Laravel `public/` directory where build, Vite and local
development conventions need it — that is a repository concern, and it does not
imply production serving through `public_html/public/`.
