# Cursor prompt: release 1.30.1, stabilising 1.30.0 against the version 3 review

## Before you start (do these steps yourself, not in Cursor)

1. Upload the version 3 review and this prompt to the test server:

   ```bash
   scp CODE_REVIEW_V3.md CODE_REVIEW_V3.pdf CURSOR_1.30.1_PROMPT.md <user>@<test-server>:~/thiscovery-review/
   ```

2. In Cursor, connect with Remote-SSH to the test server, open `/var/www/humhub`, and paste everything below the line in Agent mode.

---

## Role

You are a principal engineer stabilising a survey platform for health research before its first production use. You are an expert in PHP 8.2, Yii2 and HumHub modules, plain JavaScript, MySQL 8 concurrency, randomisation and quota methods, and privacy by design. You fix things at the root, test first, and prove every fix at runtime.

## Context

- **The module** is `/var/www/humhub/protected/modules/thiscovery-forms`, currently **v1.30.0**. The release history is in `docs/CHANGELOG.md`.
- **1.30.0** adds loops, quotas, eConsent, randomisation (1.29.0), and the formula engine with calculated fields and variables (1.30.0). Webhooks are deferred.
- **The version 3 review** is `~/thiscovery-review/CODE_REVIEW_V3.md`. It's a code reading, plus standalone PHP and Node harnesses for the formula engine and randomisation. It found:
  - **57 new defects, V3-1 to V3-57**: 8 Critical, 28 High, 17 Medium, 4 Low;
  - that 5 earlier findings have regressed (LOG-1, GOV-2, SEC-8, INT-8, DAT-17);
  - a verdict of **hold 1.30.0**.

  Every defect has a file:line reference, a scenario, and a proposed fix.
- **Earlier work** is also in `~/thiscovery-review/`: the evidence folders, fixtures, personas, Playwright, the ADRs (ADR-001 routing authority, ADR-004 anonymity, ADR-010/011 formula engine), and the Phase 0 formula decisions. The agreed formula decisions are listed at the end of this prompt.
- **Nothing is in production.** There are no live forms or responses, so don't build data repair, compatibility flags or migrations of stored data. You may change stored formats freely; put a version number in any JSON format you change. Keep everything that *creates* form definitions working: the studio, import/export, clone, snapshots, SPARCS, the AI mapper, and the fixtures.
- **The test server has no production data.** You may create or delete data, install tools, change config, and run cron, queues and migrations. Guardrails:
  - nothing leaves the server;
  - mail goes only to Mailpit, using `@example.test` addresses;
  - no secrets in deliverables;
  - confirm the DB endpoint is the test instance before writing.

**Target:** version **1.30.1**, on branch `release/1.30.1`, branched from the 1.30.0 code. Make one commit per ticket. Don't push or merge.

## The three invariants this release must restore

Every change is judged against these. Each one gets an automated test that fails today:

1. **The browser and the server agree.** For any form and any set of answers, the browser and the server produce the same visible questions, the same route, and the same calculated values. The server is the authority (ADR-001).
2. **Anonymity holds.** On a fully anonymous form, no table and no page can link a person (user, panel member, email, name, signature, token, weight, or a timestamp more precise than a day) to an answer.
3. **Research methods are correct.** Randomisation is unbiased, independent across scopes and balanced under concurrency. Quotas count each complete exactly once. Scores treat valid answers such as "0" correctly.

## Step 0: Reproduce the version 3 findings at runtime

The version 3 review didn't run against HumHub. Before fixing anything, reproduce **all 8 Critical and all 28 High** V3 findings on 1.30.0 as deployed. Save each one in `~/thiscovery-review/evidence-v3/V3-<n>/`, with a README (verdict, steps, expected vs actual), a re-runnable script or spec, its output, and screenshots or DB snapshots where relevant. The verdicts are:

- `CONFIRMED-RUNTIME`
- `CONFIRMED-STATIC` (this needs a justification: the exact causal lines, and why a live test isn't feasible)
- `REFUTED` (with evidence)
- `DIFFERENT-SEVERITY`

Specific reproductions required:

- **V3-1:** in Playwright, a form with "go to page 5 if [q1] = 2". Show the browser displaying pages 2–4, and show that the server then drops their answers (DB before/after).
- **V3-2:** complete a fully anonymous panel form through a token link, then run a SQL join from `custom_form_email_send` (and anything else) that links the member to the answer.
- **V3-3:** post a forged calculated value that a later calculation reads. Show the stored result.
- **V3-4:** time a submit with an 8,000-digit number answer, and time the preview endpoint with nested `^`.
- **V3-5:** a distribution run over 100,000 seeds (reachable orders, position frequencies, correlation across scopes, block first-slot share).
- **V3-6:** 50 parallel starts on a block-randomised form, then report the balance per block and any rollbacks.
- **V3-7:** edit a completed response on a quota form without randomisation, then show the counter and outcome.
- **V3-8:** fetch the public dashboard URL for a form with a household-names roster, and show the names in the HTML.
- **V3-9 to V3-14:**
  - `today()` in the browser vs the server;
  - each browser operator listed in V3-10 vs PHP;
  - `concat` stopping visibility updates;
  - radio "0" in `is_answered`/`count_answered`;
  - `%`, `sqrt`, and `and`/`or` with three arguments;
  - `[var:]`/`[panel:]`/`[meta:]`/`fn:`/grid row references on the server;
  - URL parameters lost on resume and manager edit;
  - a calculated value lost in the browser visibility loop.
- **The rest of the Highs** follow the scenario in each V3 finding.

Write `evidence-v3/SUMMARY.md` with the verdict counts. **Don't fix a refuted finding; explain why it's refuted.** Post a short progress note, then continue.

## Step 1: Build the test gates first (before the fixes)

These gates must exist and **fail on 1.30.0** for the right reasons. Every ticket below must turn its part of them green. Put them all in the module under `tests/`, runnable through `tests/run.php` (PHP) plus a Node runner.

- **G1. Formula parity suite (PHP ↔ JS).**
  - Generate at least **1,500** vectors covering every operator and function in `docs/user/creators-formulas.md` and the Evaluator, on **typed contexts**: radio/dropdown with numeric and text codes (including `0`), checkbox lists, rating, number, date, grid rows, loop instances with numeric and text keys, variables, URL parameters, panel/meta, `fn:`.
  - Include edge cases: empty, `"0"`, negatives, huge and tiny numbers, reversed `between` bounds, leap years, month ends, the DST boundary, list arguments, and limits exceeded.
  - Write a generator script, so the vectors are reproducible.
  - Both runners must produce **identical** value and type for every vector.
- **G2. Routing parity suite.** Restore the logic and routing vectors (the old `operators.json`/`routing.json` were deleted). Convert them to the formula format, and add cases for:
  - formula go-to, go-to-end and skip-page, both on a field and on the page break;
  - branches, action go-tos and backward go-tos;
  - hidden-field go-tos (LOG-8);
  - loops, randomised page blocks and quota go-tos.

  PHP (`FormPager` + `LogicEngine`) and Node (the fill script's routing functions, extracted without changing their behaviour) must give identical routes. Also add a Playwright check on at least 10 of these forms: the route the browser shows must equal `visitedFieldIds` stored on submit.
- **G3. Randomisation statistics** (fast enough for CI: under 60 seconds):
  - uniform option positions (chi-square, p > 0.001, over 100,000 seeds for n = 2..8);
  - every one of the n! orders reachable for n ≤ 6;
  - independent orders across scopes (no fixed relationship between two questions' orders);
  - rotation and show-N are uniform;
  - block balance is exact within each block;
  - stratified block balance within each stratum.
- **G4. Concurrency.**
  - 50 parallel starts on block, stratified and least-filled allocation: balance is preserved, there are no rollbacks, and every slot is used exactly once.
  - 100 parallel submits against a quota with 10 places left: exactly 10 accepted.
  - Reservation expiry running during submits: the counters equal a recount afterwards.
  - Editing completed responses (with and without randomisation): no counter change.
  - Reconcile running during submits: no lost accepts.
- **G5. Privacy.** For fully anonymous forms, run a scripted end-to-end check:
  - fill through a signed-in user, a token link and a panel email;
  - include eConsent with typed, drawn and checkbox signatures, completion emails, action emails, integrity, quotas, randomisation, withdrawal, and erasure;
  - then search **every table** in the module (and HumHub `file`, `user`) for any column value or combination (ids, emails, names, hashes, weights, timestamps finer than a day) that links a person to the answer.

  Also fetch every public page (public dashboard, poll embed, thank-you) and assert there's no free text, name or email in the HTML.
- **G6. Release hygiene checks in CI:**
  - fail on `example.test` outside `tests/`;
  - fail on date literals such as `'20\d\d-\d\d-\d\d'` in `resources/js` and `services` (except the vectors);
  - fail on `routing_alignment`/`routingAligned` leftovers;
  - PHP lint with deprecations treated as errors;
  - `node --check` on all JS.
- **G7. CI.** Add a GitHub Actions workflow (or GitLab CI, whichever the repo uses; ask if neither is present). It runs:
  - G1, G3 and G6, plus the pure-PHP units, on every push, using PHP 8.2 and Node 20 with no database;
  - G2, G4 and G5 against a HumHub Docker image with MySQL 8, if that's practical. If it isn't, document how they run on the test server, and run them there.

Post the gate results on 1.30.0: the counts that fail, and which V3 findings each gate covers.

## Step 2: Fix the blockers (P0)

Write the test first, then the fix, then run the full suite. Each ticket lists the findings it closes and what counts as done.

| Ticket | Closes | Required change | Done when |
|---|---|---|---|
| S-1 | V3-1, LOG-1 | The browser `pageShouldSkip` and `resolveNavigation` evaluate `logic.when` (the same as `isLogicVisible`). Remove every leftover `rules.length` check and the dead `routingAligned` branches. Rename or remove the old compatibility functions `logicMet`/`ruleMatches` so there's one clear browser entry point. | G2 is green, including formula go-to and skip-page, and the Playwright route equals the stored route. |
| S-2 | V3-10, V3-12, V3-14, LOG-5 | **One function table.** Make the PHP `Evaluator` function list the single source: generate the browser evaluator's function and operator table from a shared spec (JSON), or port every function so it provably matches. Fix `concat` shadowing, date ordering, reversed `between`, `in`/`not_in`, and choice typing (the browser must learn field types from the page config by variable, not by numeric id; decide whether numeric choice codes compare as numbers and apply that on both sides). Fix `%` (floored), `sqrt` (a proper decimal method), and `and`/`or` with any number of arguments. Wrap browser evaluation per field, so one error can't stop other updates. Keep calculated values in the browser's visibility loop, and compute calculated fields with hidden display. | G1 at 100%. `concat` in the EQ-5D starter works. `show if [bmi] >= 30` behaves the same in both. |
| S-3 | V3-3, V3-13, LOG-4 gap, V3-38 (dependency part) | **Server owns calculated values and variables.** Before evaluating: clear every posted calculated value; build effective values (hidden → empty) and feed them into calculations too; evaluate calculated fields in **dependency order**, rejecting cycles at save and publish time. Capture declared URL parameters, panel/meta variables (following the anonymity policy) and action variables **once, at response start**, into typed `variables_json`, and build every server context from that: rules, calculations, quotas, strata, piping and actions. Populate `Context` vars, panel, meta, grid rows and named formulas (`fn:`). Give each calculated field its own step budget, and raise a visible error when it runs out. | The V3-3 forgery test stores 12. URL/var/fn/grid-row vectors are green on the server. Resume, manager edit and save-progress keep URL values. |
| S-4 | V3-9 | Freeze `today()`: set `formula_today` at response start (in the form's time zone) and read it in every server path (`LogicEngine`, `FormActionService`, `FormulaRuntime`, `_field_fill`). Send it to the browser as `__today` in the page config. Remove the `'2026-09-30'` fallback; with no date, the result is empty plus a console error. | A response started at 23:30 London time and resumed the next day evaluates `today()` identically in PHP and JS, with no date literal in the code (G6). |
| S-5 | V3-11 | Choice `"0"` is an answer. Remove the `"0"` special case from `answered()` and `Value::truth()`. Keep the unchecked-checkbox handling in `Context::leaf` only. | PHQ-9 with "Not at all" (0) answers gives the correct `count_answered`, pro-rated total and `is_answered`. |
| S-6 | V3-4 | Cap Decimal digits (for example 30 whole digits and 12 decimal places): parse and results overflow to empty with a warning. Validate the length of number answers before any calculation. Restrict the preview endpoint to managers of a specific form (with the form id), rate-limit it, and cap sample values. | The V3-4 payloads return in under 50 ms. G1 includes overflow vectors. |
| S-7 | V3-2, V3-26, V3-8, GOV-2 | **Anonymity.** On fully anonymous forms:<br>• the email-send log stores neither `answer_id` nor anything that links member to answer (decide between no `answer_id`, and no member plus a date-only timestamp; document it in ADR-004);<br>• don't copy the member's weight onto the answer;<br>• action emails follow the same rule;<br>• unlinked consent records allow **checkbox attestation only** (no typed or drawn name, no witness name, no IP/UA hashes, no signature file), store a date-only timestamp, and use random ids or a separate table;<br>• the loop dashboard aggregates only structured types, never labels rows with names, skips PII fields, and is hidden on public dashboards. | G5 green: the privacy search finds no link, and public pages contain no free text or names. |
| S-8 | V3-5, V3-6 | **Replace the random number generator.** Use Fisher–Yates driven by HMAC-SHA256 (keyed by the per-response seed and the scope name), with **rejection sampling** (no modulo bias), in `RandomisationEngine` and `FormField` option ordering. Remove the LCG and `crc32` seed derivation. Allocation: `INSERT … ON DUPLICATE KEY UPDATE` to create the row, then **`SELECT … FOR UPDATE` and use that row** for block, stratified block and rotation offsets. Retry once on deadlock. | G3 and G4 (allocation) green. The V3-5/V3-6 reproductions are fixed. |
| S-9 | V3-7 | Make quota accepts idempotent: increment only when the accept row is newly inserted. Skip the quota check for answers that were already complete (or already accepted) before this save. Always write `outcome=complete` on completion, whatever the randomisation state. | G4 (edits) green: edited completes never change counters or outcomes. |
| S-10 | V3-29 | Remove the three `@example.test` guards (`ConsentService.php:1104`, `QuotaService.php:48, :968`). Test isolation is Mailpit's job. | G6 green. Re-consent and quota-full emails arrive in Mailpit for real-format addresses. |
| S-11 | V3-31 | Validate the quota redirect URL on save **and** at runtime: https only (http only when a test setting is on), host on a **module-level, admin-only** allowlist, and no `javascript:`/`data:` schemes. The thank-you page renders only validated URLs. | A `javascript:` URL is rejected on save, and a URL already stored bypassing validation isn't rendered. |
| S-12 | V3-36, DAT-3 remainder, V3-37 | Never hard-delete a question that appears in any edition snapshot; soft-delete it. Wrap studio save, clone, import and restore in transactions, and **validate before writing**. A design that fails validation isn't persisted, and the studio shows the errors. | Removing a question from the draft never breaks a submit to the published edition. An invalid save leaves the DB unchanged. |

## Step 3: Fix what a study needs before using each feature (P1)

The same rules apply. Group the tickets by feature, and give each feature a gate: that feature's P1 tickets must be done before its flag can be recommended for studies.

**Loops (S-20 to S-26):**

- V3-15: detect instances from known instance paths, not from key shape; numeric and `"0"` codes work (vectors).
- V3-16: `splitPage` keeps group-end rows; nested-loop tests for mixed layouts.
- V3-17: file and HTML (and map) questions saved per instance, or blocked inside loops when authoring.
- V3-18: server visibility and required checks per instance path; browser piping and `readAnswers` instance-aware (or server-rendered text kept on loop pages); `ruleTruth` supports all aggregates.
- V3-19: export columns follow the instances actually saved; rosters get wide columns; the long export has a UI and a controller, respects column rules, PII scrubbing, CSV neutralisation and export logging.
- V3-20: memoise the column checks, compute `loopFieldIds` once per form, and use eager-loaded answers.
- V3-45, V3-46: the remaining collapsed read paths, clearing unticked repeats, per-instance "Other" text and validation, page-index agreement with quotas and resume, the dashboard's handling of hidden repeats, loop settings in question import/export and translation, translatable headings.

**Quotas (S-30 to S-34):**

- V3-21: run quotas after effective values, calculations and variables; match cells against effective (visible) values.
- V3-23: record quota diversions, and exclude them from re-checking and recounting.
- V3-24: expiry selects under the lock and subtracts the affected-row count; reconcile runs inside the locked transaction (or applies a difference), and rebuilds `accept`/`released`.
- V3-47: page-exit actions (end, goto) take effect in the browser; rule changes are audited; saving never reopens a closed quota by accident; the allocation CSV is fixed; integrity reinstatement returns the place.
- V3-35: `scopeIncludedInAnalysis` filters on outcome everywhere (charts, MaxDiff, Delphi, per-wave and per-round counts).

**Randomisation (S-40 to S-43):**

- V3-22: create the answer and seed on the first page view, before rendering, so what's shown equals what's stored (Playwright asserts the DOM order equals the stored order).
- V3-25: never delete an arm assignment. Over-quota respondents keep their arm, with an outcome recorded. Quota-driven arm choice either uses the configured method with its locks, or is logged honestly as "quota-directed".
- V3-48: validate block size against the weights; least-filled with locks and a random tiebreak; a per-response allocation log with timestamps and method (CONSORT-ready export); the admin override wired up and audited; preview doesn't advance live counters; test responses can be given an arm for preview.
- LOG-6 remainder: the recorded option order equals the order shown (covered by the V3-22 approach).

**eConsent and governance (S-50 to S-55):**

- V3-27: hash exactly the text rendered in the respondent's language (title, body, items, attachments); lock or version translations when publishing.
- V3-28: the signature method, witness and must-read rules come from config and are enforced on the server; the drawn-signature canvas works (keyboard and screen-reader alternative: typed); "read to end" is detected by scroll/IntersectionObserver as well as keyboard focus.
- V3-43: an unticked required consent item shows "needs an answer", not a refusal; decide and implement panel `consent_at` for non-eConsent forms; atomic withdrawal-token clearing, and withdrawal reaches non-panel users; the certificate shows names and item labels, with a PDF; signature images are validated and size-capped; consent controls have accessible names and grouping.
- V3-30: the identity repair keeps no identity links (or only an encrypted, time-limited undo copy), and also clears email-send rows, integrity hashes and consent links. Erasure covers answers linked by `panel_member_id`, consent records and signature files, withdrawals, email logs, integrity hashes, repair-log rows, and audit `old_value`. It runs in a transaction, and writes no PII into the audit.
- V3-44: the studio's edit-answer form requires a reason; the manager is recorded as actor on anonymous forms; adding a value to a blank answer is audited; add a read-only audit viewer (manage permission).

**Integrity and security (S-60 to S-66):**

- V3-32: consume a one-time token only after a successful save.
- V3-33: `shownFieldIds` uses live fields only.
- V3-34: the poll embed gets a working CAPTCHA and session, or polls are exempt, as documented.
- V3-51 and the SEC-8/INT-8 regressions: rate-limit by IP with an allowance per network, sized for NHS and university networks (make it configurable, with a sensible default); failed validation doesn't count; legacy speed thresholds are converted correctly; similarity is chance-adjusted, with the denominator limited to shown questions; loop attention checks judged per repeat.
- V3-40: the `run-actions` limit is configurable; no draft is created unless a send-email action will run; atomic counter.
- V3-41: never prefix a numeric value in CSV; neutralise the codebook and allocation CSVs.
- V3-50: container-check the send-email `template_id`; `library-insert` permission; folder ACL on every `folder_id` path; upload quota keyed by answer and field, checked server-side against real fields, with a per-question file-type allowlist; `other_text` can't override frozen answers; narrow `grantLegacy` to files uploaded through this module for this form.

**Formula remainder (S-70):** V3-38 (`in` list parsing; list arguments never cause a 500; per-field budgets; action formulas parse piped values as literals, so there's no formula injection), and V3-39 (compute effective values once per request, then benchmark the 300-question form).

## Out of scope for 1.30.1 (log each item as a 1.31 ticket)

- The remaining Medium/Low items not listed above: V3-42, V3-49, V3-52 to V3-57.
- The still-open original findings: DAT-11/12/14/15, SEC-11/15–20, INT-6/7/9, SCO-7/8/10/13–23, A11Y-6–9, GOV-7/8.
- Webhooks.
- If a P1 ticket turns out much bigger than expected, deliver the safe core, keep that feature's flag documented as "not for studies yet", and write the rest as a 1.31 ticket.

## Regression and release tasks

- **Full regression** on 1.30.0 (the baseline) and on 1.30.1, with the results in `~/thiscovery-review/fixes/final-results-1.30.1.md`:
  - gates G1 to G7;
  - `tests/run.php`;
  - the Playwright suite;
  - the 104-finding evidence runner;
  - the `evidence-v2` and `evidence-v3` reproductions.

  Every V3 finding you fixed must be **not reproducible**, and no other finding may change status unexpectedly.
- **Performance:** run `fixtures/pass2/volume_bench.php` on the 300-question form (with 50 calculated fields and 200 rules), on 10,000 responses, and on a loop form (1,000 responses × 10 instances × 20 questions). Measure fill render, page exit, submit, export, the answers list and the dashboard. There must be no regression over 20% against 1.30.0, and the V3-20 export query count must drop by orders of magnitude (report the numbers).
- **SPARCS:** import both definitions, run G2 on them, walk the main paths in Playwright, then delete them.
- **Version and docs:**
  - bump `module.json` to `1.30.1`;
  - add a `## 1.30.1` CHANGELOG entry per ticket, with the findings closed;
  - update `docs/user/*` wherever behaviour changed (formulas, randomisation, quotas, loops, eConsent);
  - update ADR-004 (the anonymity decisions from S-7) and ADR-010/011 (function table, dependency order, frozen date).

## Deliverables

- **Branch** `release/1.30.1`, with one commit per ticket. Include the test gates (G1–G7) and the vector generators in the module.
- **In `~/thiscovery-review/`:**
  - `evidence-v3/` with `SUMMARY.md`;
  - `fixes/FIX_REPORT_1.30.1.md`: for each ticket, the findings closed, the change, the tests (before and after), the risks, and anything deferred;
  - `fixes/final-results-1.30.1.md`;
  - an updated `VERIFICATION.md` with a `Fixed in` column covering V3-1 to V3-57 and the 126 earlier findings;
  - a rewritten `SUMMARY.md`, including a **feature readiness table**: which feature flags are safe to recommend for studies after 1.30.1, and why.

## How to work

- Work in this order:
  1. Step 0 (reproduce);
  2. Step 1 (gates, failing);
  3. Step 2 (P0, one ticket at a time);
  4. Step 3 (P1, one feature at a time);
  5. regression.

  Post a short progress note after each step and after each P0 ticket, then carry on.
- **The invariants come first:** never mark a ticket done while its gate is red.
- Stop and ask only for:
  - product decisions the ADRs don't settle: the S-7 email-log choice, whether numeric choice codes compare as numbers (S-2), the rate-limit defaults (V3-51), and panel `consent_at` behaviour;
  - a refuted Critical finding;
  - a guardrail conflict.
- **At the end:**
  - leave the test server on `release/1.30.1`, with the site loading and form 41 submittable;
  - keep a demo form for each feature in the Review Sandbox;
  - give me a summary covering Step 0 verdicts, gate results before and after, tickets done or deferred, the feature readiness table, and exactly what I need to review before pushing.

## Agreed formula decisions (from Phase 0, for reference)

1. Compute at scale 12, and store at the question's decimal places (at most 6), rounding half away from zero, identically in PHP and JS.
2. `sum`/`mean` over values that are all empty give empty. `+` with empty gives empty. `=` with empty is false (including empty = empty). `!=` is true if either side is empty.
3. Choice `=` compares option codes only. Labels are converted to codes on save and import.
4. `today()` is the calendar date in the form's time zone (default Europe/London), frozen at response start. `date_diff` counts completed periods, and month-end dates clamp.
5. No regex in this release.
6. No migration of old-format rules; the old rule shape is rejected.
7. On fully anonymous forms: no panel variables; `meta` only from a non-identifying allowlist; URL parameters only with declared allowed values.
8. `variables_json` is typed, and the browser can't post a declared variable.
9. Named formulas replace text templates.
10. A calculated field with hidden display is still stored. One hidden by logic is empty. Posted calculated values are ignored.
11. Loop aggregates: `any_eq`/`all_eq`/`count_answered`/`sum`/`count_eq` over `[q[*]]`; a row reference is `[q[key]]`.
12. EQ-5D: build the profile from codes; no index value; SCO-11 fixed.
