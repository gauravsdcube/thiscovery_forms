# Changelog

All notable changes to this module are documented in this file.

## 1.31.0 (October 2, 2026)

Production release. It includes everything since 1.28: loops, quotas, electronic consent, randomisation, the formula engine, and the studio. The numbered review fixes are in 1.30.1 through 1.30.7 below. This section is the description of what is being released.

### Formulas

Show, hide, skip, and go-to rules are formulas. The same decimal arithmetic runs in PHP and in the browser. A calculated question stores the server result. A value typed into that question is ignored.

Numbers are calculated at 12 decimal places and stored at the question’s decimal places, at most 6. Halves round away from zero. A number can have at most 30 whole digits. `0.1 + 0.2` is `0.3`. `pow` is available, including for body mass index.

Choice rules compare option codes. An option coded 0 counts as ticked. `today()` is the calendar date in the form’s time zone, Europe/London unless the form sets another zone, and it stays on the response. A calculated question that is only hidden by its display setting is still stored. A show/hide rule that hides it clears the stored value.

Starter formulas cover body mass index, age, PHQ-9, GAD-7, and an EQ-5D profile. There is no EQ-5D index. A named formula is written `fn:name`. Renaming a variable updates every formula that uses it.

A form authored before formulas, with a field, an operator, and a value, opens as a formula and keeps its show, hide, and routing. Saving the form stores the formula. Importing a JSON or CSV in the old shape does the same, including the old condition columns and page-break branches. A loop aggregate other than any, all, count, or sum cannot be converted and is refused.

The fill page applies formula go-to, go-to-end, and skip-page rules, so the route shown and the route stored are the same. A rule on a hidden question does not route. Publishing refuses a question shown by its own answer, a go-to target that does not exist, and a rule that reads a later page.

### Anonymity

On a fully anonymous form, the completion email log stores no member, no email address, and no answer. Only the calendar date is kept. A second completion for the same person is still skipped. The member’s weight is not copied onto the answer. A manager can correct an anonymous response. The edit is audited and the response stays anonymous.

Consent on a fully anonymous form is checkbox attestation only. The record keeps no name, witness, drawing, or time of day.

### Studio

Saving a form shows “Form saved.” Settings are grouped, and each group can be collapsed. Consent, loops, randomisation, and quotas each have their own page. Each setting has a ? guide. Help covers those areas, formulas, the status lock, trash, and the export analysis codes.

Incomplete responses are kept unless that setting is turned off. Status stays locked until an edition has been published. Removing a question asks for confirmation. Moving a form to the trash keeps it there.

### Loops

Loops are off unless an administrator turns them on and the form turns them on. A question group can repeat from a fixed list, from selected options, from a number, or from rows the person adds. That group can contain one other repeating group.

Each repeat is stored on its own answer cell. The long export includes the answers on a roster row. A loop item whose code is 0 can be translated. Logic can use any, all, count, or sum.

### Quotas

Quotas are off unless an administrator turns them on and the form turns them on. The counter is locked when a response is submitted. Partial answers are kept and the outcome is over quota. Editing a completed response does not take another place.

When the accepted count reaches the target, `quota.full` is written to the quota audit. If the form has an address for “Email when a quota fills”, that address is sent a message. The event is not posted to another system.

Reservations are off unless that quota turns them on. A full cell can end the survey, redirect to an allowlisted HTTPS address, go to another page, or mark the response and let it finish without taking a place.

### Electronic consent

Electronic consent is off unless an administrator turns it on and the form turns it on. A published information sheet is frozen. The record stores a hash of the text the person was shown. The server enforces the signature method, the witness details, and must-read. Refusing a required statement ends the form as not consented, and the completion email is not sent.

### Randomisation

Randomisation is off unless an administrator turns it on and the form turns it on. Shuffles use an unbiased draw, and each question’s seed is independent. The order stored with the first response is the order that was shown. Another response does not reuse it.

Arm assignment supports simple weighted, block, least filled, and stratified block. Parallel starts lock the allocation row and retry a deadlock. A finished response is recorded as complete whether or not arm assignment is on. An over-quota response keeps its arm.

### Responses, export, and editions

A response opened on an older signed edition is checked against that edition and stores it. The long export includes roster answers. Export can be limited to responses that are ready for analysis, and those labels stay on one line. A question with no variable binds to one live question of the same label and type when a snapshot is restored. A live question can keep its variable name when a removed question still has that name.

### Later

Webhooks are not in this release. `quota.full` is audited and, when an address is set, emailed. It is not posted to another system. Consent events are audited and are not posted either.

Resume codes are stored as a keyed hash. A code the respondent still has continues to work. The plain text cannot be read back from the database. No production form is carrying resume codes that need to be restored.

One live question on a form may use a variable name. Production has no pair of live questions that share a name.

## 1.30.7 (October 2, 2026)

Corrections from the full test suite.

- The BMI formula can use `pow`.
- Moving a form to the trash keeps it there when the form is closed.
- A response opened on an older signed edition is checked against that edition and stores it.
- The option order saved with the first response is the order that was shown, and another response does not reuse it.
- A question with no variable binds to one live question of the same label and type.
- A loop item whose code is 0 can be translated.
- A manager can correct an anonymous response. The edit is audited and the response stays anonymous.
- A select in creator HTML keeps a prefixed name, so it cannot post as a fill field.
- The long export includes the answers on a roster row.
- A live question can keep its variable name when a removed question still has that name. A new question is given the next free name.

## 1.30.6 (October 2, 2026)

Help and the export page, for release.

- Help covers consent, loops, randomisation, quotas, formulas, the status lock, trash, and the export analysis codes.
- Each setting has a ? guide.
- The Ready for analysis options stay on one line.
- The limits under 1.30.4 Later are unchanged: webhooks are not sent, an anonymous completion email log still stores the member and email, resume codes cannot be restored, and a shared variable name stays on the oldest question.

## 1.30.5 (October 2, 2026)

Corrections found when the database gates were run.

- Consent rules run even when a page posts no answers, so a missing signature is rejected.
- A finished response is recorded as complete whether or not arm randomisation is on, so editing it does not change the quota count.
- An anonymous completion does not keep the member's weight on the answer.
- Parallel arm assignment creates the allocation row in its own committed step and retries a deadlock, so every start is assigned and the arms stay balanced.
- A group end with no label is saved under the standard label.

## 1.30.4 (October 2, 2026)

Studio behaviour kept while applying the 1.30.3 review fixes.

- Saving a form shows “Form saved.” in the status bar and on the studio page.
- Settings are grouped in the studio navigation, and each group can be collapsed. Consent, loops, and randomisation each have their own page. The route map stays in that navigation.
- Incomplete responses are kept unless that setting is turned off.
- Status stays locked until an edition has been published.
- Removing a question asks for confirmation.

### Later

- Webhooks, including `quota.full`, are not sent.
- On a fully anonymous form the completion email log still stores the member and the email address, so one completion on a day can still be matched to that person.
- Resume codes already stored are hashed in place. The plain codes cannot be restored.
- When two live questions share a variable name, only the oldest keeps it. The studio asks for a new name on the next save.

## 1.30.3 (October 2, 2026)

Medium and Low fixes from the version 3 review (V3-37 to V3-57), and the earlier findings that were still open.

Remaining Open and Partial rows of the version 3 status table:

- Fix (LOG-6): option order is always per respondent (the off-by-default setting is gone); the order is seeded by the session so the order stored is the one first shown.
- Fix (LOG-8): go-to and skip-page rules on a hidden question don't route, on the server or in the browser.
- Fix (LOG-9): saving and publishing refuse duplicate page keys, go-to targets that don't exist, a question shown or hidden by its own answer, and rules that read a later page. The studio has a read-only Route map.
- Fix (LOG-10): page breaks have an "Otherwise go to" page; new formula functions `selected_all` and `selected_only`; an option coded 0 in a multiple-choice answer counts as ticked; "Skip this question" (identical to Hide) is no longer offered.
- Fix (LOG-11): every action can have an "Only if" condition; email actions send at page exit or submit, never while an answer is being changed.
- Fix (LOG-12): answer rules per question: text length and pattern, date range (with `today`), and a cross-answer check formula, enforced on submit and checked on the page. Text answers have a hard length cap. Migration `m261005_100000_field_validation`.
- Fix (DAT-8): export columns and the codebook use the published editions, not the draft.
- Fix (DAT-9): a new choice with no code gets a fixed number that never follows its label and is never reused.
- Fix (DAT-10): a chosen variable name already in use is refused instead of silently becoming `_2`; the database allows one live question per name. Migration `m261005_110000_unique_live_variable`.
- Fix (DAT-16): drafts resume by page key. Migration `m261005_120000_answer_page_key`.
- Fix (DAT-18): a stray group end is dropped at save.
- Fix (DAT-19): imports, clones, restores and translation imports record a revision.
- Fix (GOV-4): managers can pseudonymise or delete one response and erase a panel member (keeping or deleting their answers); every erasure is logged with mode, actor and reason. Migration `m261005_130000_erasure_modes`.
- Fix (SEC-11): respondents who may view answers see the public-safe dashboard.
- Fix (SEC-13): fill uploads are limited to known file types whose content matches the extension; each file question can narrow types and lower the size limit.
- Fix (INT-3): a similar pair is flagged on both responses; forms with more than 250 responses get a background comparison against up to 5,000.
- Fix (SCO-1): MaxDiff questions have several design versions; each respondent sees one, and the version and items shown are stored with the answer.
- Fix (SCO-3): MaxDiff answers give dashboard cells (best, worst, shown).
- Fix (SCO-5): per-question consensus bands, excluded codes and IQR limit; consensus out; median and IQR in the round summary.
- Fix (SCO-9): consensus items freeze when a round closes, whether or not its summary is published.
- Fix (SCO-12): exports default to complete responses, add a 0/1 column per multiple-choice option, and code empty answers (-99 skipped, -98 hidden, -97 not reached).
- Fix (A11Y-2): the file question is a named group, and every question exposes its required state.
- Fix (A11Y-4): one alert per failed page instead of one per question.
- Tests (NEW-22): `tests/support/seed.php` prepares a fresh HumHub for the suite; CI runs lint, JavaScript checks and the standalone suite, and a HumHub job runs the full suite once the sibling modules are configured.
- Already covered by earlier fixes, no change: LOG-5 (V3-10), DAT-3 (V3-37), SCO-7, SCO-16, SCO-25, A11Y-1 (V3-49).

"Fixed with gaps" rows of the version 3 status table:

- Fix (DAT-4): edition questions are never bound to a live row by label and type, so two questions with the same label can't be merged.
- Fix (INT-2): consistency rules apply only when the respondent was shown every question they read.
- Fix (SCO-4): a multi-select grid column coded 0 reaches the dashboard.
- Fix (SCO-6): adding an existing panel member again (people picker, studio "add member") keeps their weight; 0 is allowed.
- Fix (SCO-11): EQ-5D never scores untagged questions; an EQ-5D form needs all five dimensions tagged to publish.
- Fix (SEC-5): a guest's page-exit emails wait for the submission and its checks.
- Already closed by V3 fixes, no change: LOG-4 (V3-38), DAT-5 and DAT-6 (V3-52), DAT-7 (V3-52), GOV-3 (V3-44), SEC-1, SEC-2, SEC-12, SEC-14 and NEW-14 (V3-50), SEC-6 (V3-32), SEC-10 (V3-41), INT-4 (V3-51), SCO-2 (V3-42), A11Y-3 and NEW-16 (V3-49), A11Y-5, NEW-11 (V3-36), NEW-15 (V3-34), NEW-17 (V3-37), NEW-19 (SCO-18).

- Fix (INT-8, SEC-8, regressed in 1.30.0): a submit counts towards the rate limit only if it is kept. A save rejected for validation errors, a failed CAPTCHA or a failed write gives its count back, and an attempt already over the limit is not counted. Counting runs under a mutex, so concurrent submits cannot slip past. IPv4 is keyed by exact address and IPv6 by /64, and a submit that uses up the allowance is the one flagged.
- Fix (LOG-1, regressed in 1.30.0): if the formula engine isn't loaded in the browser, go-to and skip-page rules never fire, instead of firing for everyone. The server applies the real route on submit.
- Tests: the GOV-2 regression (V3-2) is covered: on a fully anonymous form, the completion email log has no answer id and keeps only the date.
- Fix (V3-37): saving questions is all or nothing. The rows are written in a transaction (a savepoint inside an outer one). The design checks run against the written structure: backward go-tos, loops, quotas, consent, formula policy and legacy rules. Any failure rolls every question write back, so an invalid design is never stored. The studio shows the reasons and keeps the author's unsaved edits on screen, and import reports the same reasons. Clone and snapshot restore also run in a single transaction (DAT-3).
- Fix (V3-43): a required tick box left unticked is a missing answer, not a refusal. Consent statements have accessible names, and Yes/No answers are grouped. Agreeing stamps the panel member's consent date. Withdrawal by token is atomic and scoped to the form, reaches the person's account and all their panel memberships, and un-satisfies the consent requirement. The certificate shows labels, the signer and the details, and can be printed or saved as a PDF.
- Fix (V3-44): a manager who changes someone else's completed response must give a reason, and is recorded as the actor even on anonymous forms. Filling in a blank is audited, and filling in a draft is not. The answer detail has a change history.
- Fix (V3-45): loop repeats are never collapsed (`getFieldValue`/`loopCells`, the record title, the project view, the dashboard provider). Unticking a repeat clears it, and "Please specify" text is kept per repeat. Quota page checks and resume use the expanded pages (`FormPager::fillPages`). The dashboard counts only shown repeats. Repeats are named by their labels, not raw keys.
- Fix (V3-46): loop, block-randomisation and consent settings survive question import/export (JSON and CSV), library insert and clone, and loop source keys are remapped. Fixed loop items can be translated. Loop headings are single translatable messages.
- Fix (V3-47): a page-exit quota check acts over AJAX: end/redirect goes to a closing page, and goto moves the page. Saving a quota keeps its status, and changes to its rules or action are audited (with a reason once anyone has been counted). The allocation CSV works and is the per-response allocation log. A reinstated response takes its quota place back.
- Fix (V3-48): block sizes must be a multiple of the total arm weight. Least-filled is locked minimisation with a random element and ignores abandoned assignments. The arm override has a manager UI and is logged. Preview never advances rotation counters.
- Fix (V3-51): rate limits are per address and per session, never per /24 network, so shared networks aren't blocked. Legacy speeding values are read correctly. Similarity is chance-adjusted. Loop attention checks need every repeat to pass.
- Fix (V3-38): `in [..]` and `not_in [..]` lists parse. A rule that fails to evaluate is false, not a 500 error. The dependency graph follows named formulas, and cycles are left empty in the browser too. Running out of steps is logged. Calculations read only visible answers. Action formulas reference answers rather than pasting them in.
- Perf (V3-39): visibility (the hidden-answer fixed point) and formula contexts are worked out once per set of answers.
- Fix (V3-40): run-actions with no actions needs no draft. The rate limit is per session and five times that per address, with IPv6 counted by /64, under a mutex. Action emails are capped per recipient and per network. Email templates from another space are ignored.
- Fix (V3-41): plain negative numbers export as numbers, and every export CSV row is neutralised.
- Fix (V3-42): MaxDiff sets are kept while the design is unchanged and cover every item. `score_of` on an unscored option is empty. The EQ-5D profile needs all five dimensions, and partly tagged EQ-5D never borrows untagged questions.
- Fix (V3-49): Best/Worst and MaxDiff radios are named and headers are scoped. Widget groups are named by the question. Loop error ids are unique per repeat. The error summary links to each question. Pages have accessible names. Stacked grids keep one named copy, mirrored and re-synced when the layout changes.
- Fix (V3-50): library insert needs create permission, folder moves respect the folder ACL, uploads need a real file question and are capped per network, frozen answers ignore "specify" text, `grantLegacy` grants only attached files, and respondent-only viewers see only their own responses (SEC-11).
- Fix (V3-52): renaming a variable rewrites every reference. Import renames references in every formula, and clone rewrites piped labels. A response opened on an older edition must be re-checked, and `id5`-style variable names are not allowed.
- Fix (V3-53): the eConsent backfill is linear, the unique key is swapped without a gap, the condition-column drop is documented as irreversible, and a new migration adds consent member/user indexes.
- Fix (V3-54): real date literals only, known functions only, and unknown question references are refused at publish. Operator chains don't count as depth. `concat` truncates by character. Variables are stored with their type. `contains_text` on a list matches whole items. `1e5` is refused as a number answer. The formula guide documents all of this.
- Fix (V3-55): repeat codes are validated on every loop. Input ids are unique, there are at most 100 repeats, and removed roster rows are capped.
- Fix (V3-56): a quota's parent and wave must be on the same form. Previews and test fills get an arm that isn't counted. Stored orders are locked. Page blocks can show N pages.
- Fix (V3-57): the `toFolderDelete` deprecation is fixed. The consent audit records refusals as "refused", and consent times are UTC. The routing-alignment flag and its dead code are removed. A hard delete cleans up dependent rows and refuses when real consent records exist.
- Feature (GOV-7): deleting a form moves it to a trash. Answers are kept, it can be restored, and every trash, restore and permanent delete is logged. A permanent delete happens only from the trash, after typing the title, and never while consent records exist.
- Feature (GOV-8): IP metadata is truncated, and fully anonymous forms store no IP or browser string. A daily retention job removes old email logs (365 days), abandoned drafts (180 days) and integrity client hashes (90 days); each period is configurable. `php yii thiscovery-forms/retention/run`.
- Fix (DAT-11): a per-page submit token stops duplicate responses from double submits, and single-response forms refuse a second completed response under a lock.
- Fix (DAT-12): drafts keep answers that logic hides; only the final submit removes them.
- Fix (DAT-14): resume codes are stored as a keyed hash, expire after 60 days, and failed lookups are rate-limited. Emailing a code needs the code. The fill page sends no referrer.
- Perf (DAT-15): exports are streamed rather than built in memory, and the similarity check skips forms with nothing to compare.
- Fix (SEC-15 to 20): form files are served safely; panel links are signed and expire; brief documents have decompression caps; LLM briefs are redacted and the API key goes only to allowed hosts; creator HTML can't overwrite form fields; panel admin needs Manage forms.
- Fix (INT-6, INT-7, INT-9): straight-lining respects reverse-keyed items and opt-outs; a shared IP address alone is information only; consistency rules accept labels and survive cloning.
- Fix (SCO-8, SCO-10, SCO-13 to SCO-25): Delphi summaries show labels and percentages that sum to 100; multi-select charts use respondents as the base; wave invites send once, retry failures and respect withdrawal; question segments and weights are available to the dashboard module; Other detection is stricter; analytics fixes (response rates, unique respondents, rating steps, number statistics, codes, option parsing, EQ-5D VAS, time zones). Analyst notes are added.
- Fix (A11Y-6 to 9): focus styles and contrast, accessible ranking, labelled drilldown levels, VAS value text, and reduced-motion support.
- Migrations: `m261003_100000_consent_member_indexes`, `m261004_100000_form_lifecycle_log` and `m261004_110000_hash_resume_codes`.
- Tests: `StudioAtomicSaveTest`, `AnswerAuditTest`, `QuotaFixesTest`, `IntegrityScoringTest`, `RespondentVisibilityTest`, `VariableRenameTest`, `FormTrashTest`, `RetentionTest`, `DuplicateSubmitTest`, `DraftHiddenTest`, `ResumeCodeTest` and `SecurityLowTest`. Standalone tests: `FormulaEdgeTest` and `AnalyticsTest`. `ConsentHardeningTest`, `EconsentTest`, `LoopHighsTest`, `RandomisationTest`, `PollSubmitTest` and the standalone randomisation statistics are extended.

## 1.30.2 (September 30, 2026)

High fixes from the version 3 review (V3-9 to V3-36). Nothing is in production, so no data repair is needed.

### Formulas
- Fix (V3-9): `today()` is frozen when the response starts, in the form's time zone, and the browser gets the same date.
- Fix (V3-10): the browser evaluator is a full port of the PHP one. A generated parity test (over 43,000 formula trees on typed questions, loops included) requires identical results from PHP and Node.
- Fix (V3-11): a choice coded "0" is an answer. Ordering comparisons read numeric codes as numbers.
- Fix (V3-12): `%` is floored, `sqrt` is exact to 12 places, and `and()`/`or()` take any number of arguments.
- Fix (V3-13): named formulas, grid rows, action variables and panel values reach server formulas. Declared URL parameters are stored on the response.
- Fix (V3-14): calculated questions are recomputed whenever answers are read in the browser, so show/hide and routing see them.

### Loops
- Fix (V3-15): repeat codes 0..n and 1..n are instance answers, decided by loop membership rather than key shape, in PHP and in the browser.
- Fix (V3-16): nested loops render the right questions. A question after an inner loop shows once per outer repeat, and a plain group before an inner loop no longer flattens it.
- Fix (V3-17): every question type in a loop is parsed per repeat, including file, HTML, ranking, map and grid.
- Fix (V3-18): visibility, required and piping work per repeat on the server and in the browser. `{{loop.*}}`, `{{loop.parent.*}}` and `{{answer:q[code]}}` are piped in the browser.
- Fix (V3-19): choices loops export a column for every option. The long export (one row per repeat, which includes rosters) is on the dashboard and uses the wide export's permission check, logging, column rules, PII scrubbing and CSV neutralisation.
- Fix (V3-20): schema checks run once per request, and values maps use eager-loaded answers, so exports and similarity checks no longer run a query per field per answer.

### Quotas and randomisation
- Fix (V3-21): quota cells and arm strata read calculated questions, variables and URL values, and match visible answers only.
- Fix (V3-22): a new respondent's orders are drawn from a seed kept in the session and stored at first save, so the recorded order is the order shown.
- Fix (V3-23): a quota's go-to records the diversion, so the respondent can finish.
- Fix (V3-24): expiry and reconcile work under the counter lock.
- Fix (V3-25): an over-quota respondent keeps their arm. Quota-directed arm choice is locked and logged.
- Fix (V3-31): quota redirects must be https URLs on the allowlist, and are checked at save, at runtime and on the thank-you page. Only a network administrator can allowlist a host.

### eConsent and governance
- Fix (V3-26): on fully anonymous forms, consent is checkbox attestation only. The record keeps no name, witness, drawing, client hash or time of day.
- Fix (V3-27): the page shows, and the record hashes, one server-side presentation of the sheet (language, title, body and items). A changed sheet is re-shown. Translations of a published version are locked.
- Fix (V3-28): the server enforces the configured signature method, the witness details and must-read. The drawn signature works, and read-to-end is recorded on scroll.
- Fix (V3-29): re-consent and quota-full emails go to real addresses. A hygiene test forbids test-only addresses and hard-coded dates.
- Fix (V3-30): identity repair clears client hashes and the email-log and consent links, and keeps the removed identities for only 14 days (`repair-identity/finalise` removes them at once). User erasure finds token and invite answers, and clears the consent, email-log, integrity, repair-log and audit traces, in one transaction.

### Integrity and studio
- Fix (V3-32): a one-time invitation is given back when the submission fails.
- Fix (V3-33): a removed question counts as shown only when this response answered it.
- Fix (V3-34): polls are not blocked by the CAPTCHA setting.
- Fix (V3-35): screened-out, over-quota and not-consented responses are excluded from every analysis scope, Delphi included.
- Fix (V3-36): a question removed after publishing is kept as removed, never hard-deleted.
- Tests: standalone `FormulaParityTest` and `HygieneTest`. HumHub tests `ConsentHardeningTest`, `ErasureCompletenessTest` and `LoopHighsTest`, with `EconsentTest` and `IdentityRepairTest` extended.

## 1.30.1 (September 30, 2026)

Critical fixes from the version 3 review (V3-1 to V3-8). Nothing is in production, so no data repair is needed.

- Fix (V3-1): the fill page applies formula go-to, go-to-end and skip-page rules. It previously ignored them while the server applied them, so the route shown and the route stored disagreed (LOG-1).
- Fix (V3-2): on a fully anonymous form, completion and action emails are logged without the answer id and with the calendar date only, and the member's weight is not copied onto the answer (GOV-2).
- Fix (V3-3): calculated values are always calculated by the server. A posted value is discarded before any formula runs, calculated questions run in dependency order, and a cycle blocks publishing.
- Fix (V3-4): a number can have at most 30 whole digits, in PHP and in the browser. The formula preview needs POST, checks form management and is rate limited.
- Fix (V3-5): shuffles and arm draws use an unbiased SHA-256 stream with rejection sampling, and each question's seed is independent (HMAC-SHA256). Every order is now reachable and orders are no longer correlated across questions.
- Fix (V3-6): block, stratified-block and rotation allocation lock the allocation row before reading it, so parallel starts stay balanced and cannot collide.
- Fix (V3-7): editing a completed response never counts it again for a quota or turns it into over-quota. A completed questionnaire always records outcome complete.
- Fix (V3-8): dashboards aggregate only structured loop answers and never show roster names. The public dashboard has no loop breakdown.
- Tests: `tests/standalone/run.php` runs pure-PHP checks without HumHub (calculated fields, number limits, randomisation statistics). `AllocationConcurrencyTest` and `QuotaEditTest` run on the HumHub test server.

## 1.30.0

### Formula release

- A label typed as an expected value is saved as the option code, and an unknown value is rejected.
- A fully anonymous form cannot be opened or published when a formula uses a panel value. Identity meta keys are refused on every form.
- Formula text, depth, node count, and evaluation steps share one limits check.
- The browser preview implements score_of.
- The quota editor describes a formula.

### High findings

- A restored snapshot no longer binds two questions to the same live field.
- An imported question whose variable is already on the form gets its own variable, and its rule points at that question.
- Clone and restore rewrite `{{answer:id}}` and `{{field:id}}` to the new question ids.
- Editing an answer records the old value, the new value, the actor, and the reason.
- Deleting a user keeps the research answers, clears identity, files, and panel contact, and records the erasure.
- Similarity compares choice values, not question ids.
- Best scores positive and worst scores negative. A multi-select grid chart uses the column code.
- Each grid choice has an accessible name from its row and column. A choice question is a group labelled by the question, and required is exposed.

### Formulas

Show, hide, skip, and go-to rules use one formula. The same decimal arithmetic runs in PHP and in the browser. A calculated question stores the server result. A value typed into that question is ignored.

- Numbers are calculated at 12 decimal places and stored at the question’s decimal places, at most 6. Halves round away from zero. `0.1 + 0.2` is `0.3`.
- A sum or mean of values that are all empty stays empty. A comparison with an empty answer is false, except “is not equal”.
- Choice rules compare option codes. `today()` is the calendar date in the form’s time zone, Europe/London unless the form sets another zone, and it stays on the response as `formula_today`.
- A calculated question that is only hidden by its display setting is still stored. A show/hide rule that hides it clears the stored value.
- EQ-5D dimension levels come from option codes 1 to 5. Any other code, including 9, is missing. There is no EQ-5D index.
- The studio writes a formula. A saved rule in the old field, operator, and value shape is rejected. The unused condition columns are dropped.
- A formula box lists the question and variable names it uses.
- A URL parameter is available to a formula only when the form declares it and lists the allowed values. Any other value is empty.
- Page routing no longer has a second, older walk.
- Starter formulas cover body mass index, age, PHQ-9, GAD-7, and an EQ-5D profile. A named formula is written `fn:name`.

## 1.29.0

### Loops

Loops are off unless an administrator turns them on and the form turns them on. A question group can repeat from a fixed list, from selected options, from a number, or from rows the person adds. That group can contain one other repeating group. A third level is not in this version.

- Each repeat is stored on its own answer cell. Unselected repeats stay in the table. The default export leaves them blank. “These answers are kept but not shown.”
- Wide export columns look like `symptom__asthma`. A nested repeat looks like `symptom__alex__asthma`. The codebook lists the same names. Logic can use any, all, count, or sum. An unknown aggregate cannot be published.
- A list the person adds to uses Add another. Each row’s key starts with r and is created by the server. Removing a row keeps the answers. The long export lists the rows that were shown.
- Reducing a number hides the later repeats and does not renumber the ones that remain.
- The dashboard adds every repeat together. A control shows one repeat label at a time. Resume opens the repeat that was open.

### Quotas

Quotas are off unless an administrator turns them on and the form turns them on. The counter is locked when a response is submitted, so two people cannot take the last place. Partial answers are kept and the outcome is over quota.

- Reservations are off unless that quota turns them on. The hold then lasts 60 minutes. `quota/expire` clears expired holds. `quota/reconcile` is a dry run until `--apply=1`.
- A required full cell can end the survey, redirect to an allowlisted address, go to another page, or mark the response and let it finish without taking a place.
- `quota.full` is not sent. Webhooks are still deferred. The audit row is written when the target is reached.

### Consent

Electronic consent is off unless an administrator turns it on and the form turns it on. Completing a form no longer stamps a panel member’s consent time.

- A published information sheet and its statements are frozen. The next edit is a new version. The record stores the hash of the text the person was shown.
- Refusing a required statement ends the form as not consented. That response is not a completed questionnaire, and the completion email is not sent. Optional statements may be no.
- Fully anonymous consent is not linked to the answer. Withdrawal uses a one-time code. Asking to delete data opens an admin task and does not delete answers.
- Older consent timestamps are listed as legacy and do not count as consent for a new version.

### Randomisation

The module version stays 1.28.7 until the 1.29.0 release. Randomisation is off unless an administrator turns it on and the form turns it on.

- Server-side shuffle, rotate, and show-N for options, questions in a group, and pages inside a randomisation block. The order is stored on the response. A later view uses that order, including after the feature is turned off for a response that already has one.
- Arm assignment: simple weighted, block, least filled, and stratified block. Definitions live on the form. The assigned code and name are copied onto the response. Test responses are not assigned. A fully anonymous form cannot stratify on a panel attribute.
- Screened-out closes the response and is not counted as a completed questionnaire.
- Export adds the outcome, the arm, and the stored orders. The dashboard can download a codebook and an allocation log.

## 1.28.7 (September 29, 2026)

1.28.7 follows 1.28.6. Migrations are unchanged.

- Fix (LOG-4): a hidden question's answer is treated as empty before later rules run, on the server and on the fill page. A leftover answer cannot keep the next question visible.

- Fix (SCO-1): MaxDiff sets walk the item list instead of repeating the first slice. Existing stored sets are left as they are.

- Fix: the between operator matches an inclusive min,max range. The value is written as `1,10`. A list matches when any value is inside the range. Text that is not a number does not match.

- Fix (SCO-2): a MaxDiff score is (best − worst) / times shown. The result includes how many sets included the item. A stored answer that only has best and worst counts those items as shown.

## 1.28.6 (September 29, 2026)

1.28.6 follows 1.28.5.

- Fix (DAT-7, DAT-8): the fill posts a signed edition id, and that is the edition stored on the response. If the edition cannot be loaded, the working draft is not shown. The answers CSV has an Edition ID column and includes questions from the editions responses used. Migrations are unchanged for this fix.

- Fix (INT-2, INT-4, INT-5): attention, straight-lining, and free-text checks score only questions on the respondent's route. Speeding is seconds per question shown. A stored minimum of 15 seconds, the old total-time default, is treated as 2 seconds per question. The browser cannot set the start time. Migration `m260929_140000_shown_question_count`.

- Fix (SCO-5): a Delphi item freezes when the agree band reaches the threshold. Codes in the disagree band do not freeze the item when that band also reaches the threshold. Excluded codes are left out of the share. With no band set, the single most common code is still used.

- Fix (GOV-6): the answers CSV is a POST, and only someone allowed to export can download it. A respondent who can see answers only because they submitted one cannot export the file. Each download is written to `custom_form_export_log`. Scrubbing is on when a question is marked personal, unless the form turns it off. Migration `m260929_141000_export_log`.

- Fix (A11Y-4, A11Y-5): a field error is linked to its control and focus moves to the first error. Page changes and newly shown questions are announced. On the fill page, the unnamed menu and search controls have names, and the breadcrumb and space initials meet the contrast check.

## 1.28.5 (September 29, 2026)

1.28.5 follows 1.28.4. It does not replace the 1.28.4 fixes.

- Fix (SEC-1, SEC-9, SEC-14): a panel, folder, email template, or library item is kept only when it belongs to this form's container. A global template may still be used from a space. `settings_json` is no longer mass-assignable.

- Fix (SEC-5): run-actions no longer runs submit actions. Posted variables cannot replace built-in mail tokens and must be names declared on the form. Email HTML escapes substituted values. The endpoint is rate limited by address.

- Fix (SEC-6): the invitation token is read once, from `access` and then `access_token`. A panel token is not an access token. The use count increases only while the token is still under its limit and has not expired.

- Fix (SEC-7, SEC-8): JSON submit is only accepted for polls, and it uses the same submit gate as the fill page. The submit rate-limit key is the network address, so a new session does not reset it.

- Fix (SEC-10): answer, question, and translation CSV exports prefix a cell that starts with `=`, `+`, `-`, `@`, tab, or a carriage return. Import strips that prefix.

- Fix (SEC-12): frozen questions keep the previous round's answer. Hidden questions keep their default. Panel-attribute questions keep the member's value. A posted value for those fields is discarded.

- Fix (SEC-13): each question accepts at most 10 files and 50 MB, and one file must be 10 MB or smaller. Guest uploads from one network are limited to 20 in 10 minutes.

- Fix (LOG-7): run-actions saves a draft before an action email. The duplicate check stores an actor key, so one anonymous respondent does not block another. Migration `m260929_100000_email_send_actor`.

- Fix (SCO-6): completing a response or importing a panel again does not replace a weight the file did not set. A weight of 0 stays 0, including on a fully anonymous form.

- Fix (LOG-6): per-response option order is off unless `option_order_per_response` is `1`, `true`, or `on`. When it is on, the order is seeded from the answer or resume code, exclusive options and Other stay in place, and the order shown is stored on the response.

## 1.28.4 (September 28, 2026)

1.28.4 replaces 1.28.3 for production. It keeps the 1.28.3 fixes and adds the following.

- Fix (NEW-10): a variable name cannot be reused by a new question when a removed question still has it. Export columns are keyed by field id, and a repeated header is suffixed. Detection: `php yii thiscovery-forms/detect-duplicate-variables` (counts only).

- Fix (NEW-11): removing a question that has no answers deletes it. A question that already has answers stays removed, keeps its original removal time, sorts after live questions, and is labelled "(removed)" in the answer, the export, and the dashboard.

- Fix (NEW-12): scoring, the project record, round summaries, and the completion rate include questions that were removed after people answered. The completion rate uses that same set on both sides. A fully anonymous form does not show a unique-respondent count.

- Fix (NEW-13): migration `m260928_200000_answer_field_fk_guard` checks `information_schema` and does nothing when the answer-field foreign key is already ON DELETE RESTRICT. On the test table (102,859 rows) the original swap copied the table: dropping the key took 0.074s and adding RESTRICT took 1.052s. Run that swap in a maintenance window, or with `pt-online-schema-change` or `gh-ost`, on a larger table. The application tolerates `deleted_at` not existing yet.

- Fix (NEW-14): a stored file is accepted only when it is attached to the answer being edited. The upload grant is removed once the file is attached and when the answer is completed. A completed answer cannot lose its file unless editing is allowed. The grant list holds 50 files. Detection: `php yii thiscovery-forms/detect-unattached-files` (counts only).

- Fix (NEW-15): a captcha result is stored as shown and passed by the submit gate, and a failed check is not left in the session. A poll submit discards any stored result, so it cannot record a failure.

- Fix (NEW-16): returning to a page only re-enables inputs that were disabled because the page was unreachable. An "Other, please specify" box and the hidden copy of a grid stay disabled.

- Fix (NEW-17): the studio rejects a go-to that points at an earlier page. While the form is being filled, that go-to continues forward and is logged, in the browser and on the server. Flag `routing_alignment` still defaults to on. LOG-4 chained visibility and the missing `between` operator are unchanged.

- Fix (NEW-18): an anonymous completion row stores the calendar date only. The date can still be compared with answers from that day; the row does not store the answer id.

- Fix (NEW-19): stylesheet validation runs when the CSS changes, so an existing form can be saved. Migration `m260928_210000_strip_css_markup` removes `<` from stored stylesheets on forms, themes, and the stylesheet inside a snapshot. Restoring a snapshot strips it as well. The removed characters cannot be put back.

- Fix (NEW-20): deleting a file can name the answer being edited, and that lookup does not load the answer into the form. A cleared file is deleted after the save commits. A failed save is logged as an error. A file uploaded before grants existed is kept when it was uploaded by the same user and is still unattached, or when the draft already stores it.

## 1.28.3 (September 28, 2026)

- Fix (GOV-1, GOV-2): a fully anonymous form no longer stores `created_by`, `updated_by`, `panel_member_id`, or a completed `resume_email`, and panel completion is a counter with no answer link. Submission notifications are skipped. Flag `identity_enforce_mode` defaults to on; set it to `0` to restore the previous behaviour. Repair: `php yii thiscovery-forms/repair-identity` (dry-run; `--apply=1` writes). Migration `m260926_120000_anonymous_completion_wave` adds `form_panel_activity.wave_id`.

- Fix (DAT-1): removing a question soft-deletes it (`custom_form_field.deleted_at`) and the answer foreign key is ON DELETE RESTRICT, so stored answers survive. Migration `m260926_130000_answer_field_restrict`. No feature flag: a question delete no longer destroys data.

- Fix (DAT-2): choice translations keep the option code and change only the label. Old positional lists are mapped by position; a count mismatch keeps the source label and logs a warning. Detection: `php yii thiscovery-forms/detect-translation-loss` (counts only).

- Fix (DAT-3): the answer header and answer rows commit together. Status becomes complete and the resume code is cleared only after the rows are written. Dashboard enqueue and file attachment run after commit. A database error rolls the submission back. Detection: `php yii thiscovery-forms/detect-partial-completes` (counts only).

- Fix (LOG-1, LOG-3): a skipped page follows its go-to and its page-break rules, including skip-page on the page break that opens it. The 80-step cap is replaced by the page count plus a cycle warning. Flag `routing_alignment` defaults to on; set it to `0` to restore the 1.28.2 walk. LOG-4 chained visibility and the missing `between` operator stay for a later release.

- Fix (INT-1): the captcha result from the submit check is stored for that form and reused when the response is scored. It is cleared after completion, so a solved check is recorded as passed and is not verified a second time. No flag.

- Fix (SEC-4): an answer piped into a required label is inserted as text, including when the label also shows the required marker. No flag.

- Fix (SEC-3): custom CSS that contains `<` is rejected on save. CSS already stored is rendered with `<` removed, so it cannot close the style element. No flag.

- Fix (SEC-2): clearing a file answer deletes the file only when it is attached to that answer. A posted file id is kept only when it was uploaded in this fill session or is already attached to the answer being edited. The same rule applies when the respondent removes a file. No flag.

- Fix (LOG-2): a Go to page or Go to end action is applied when Next is pressed, after the current page is valid, not while the respondent is still answering. An answered question's action is used before a page-break action. Page-break actions have no conditions. The landing page is no longer cleared. Covered by the `routing_alignment` flag.

## 1.28.2 (September 25, 2026)

- Fix: Remove UAT tester form and UAT results from the published module (routes, buttons, and table)

## 1.28.1 (September 25, 2026)

- Fix: Publish UAT trait, views, and migration so the module loads (v1.28.0 referenced `UatTrait` but omitted the file)

## 1.28.0 (September 25, 2026)

- Fix: JSON question export rewrites skip-logic `fieldKey`s to portable variable/`key` values (same as CSV), so import no longer leaves studio keys such as `id11`
- Fix: Checkbox exclusive options and “select up to N” are enforced when the box is ticked, not only on the click event
- Enh: Number questions support optional min/max (shown on the input and validated on Next/submit)
- Enh: Choice questions can turn off the inline Other “Please specify” box when a later question already collects the detail
- Enh: Screen-out “go to end” uses a **Finish** button instead of **Submit**
- Fix: SPARCS2 110926 — A1 multi-child message when more than one child; A1/A6 cannot be negative; B7–B10 need A5 = Yes plus age ≥ 2 / ≥ 5 years; C2/C4 Other no longer blocks on inline specify

## 1.27.0 (September 12, 2026)

- Enh: Forms list uses the same folder sidebar as Page Builder: top-level (unfiled) forms, then folders, then templates

## 1.26.0 (September 8, 2026)

- Enh: Registers as a **Thiscovery Dashboard** data provider (question-type contract, snapshot ingest)
- Enh: Form submit enqueues dashboard incremental aggregation only (`queue->push`) so live fill is not slowed by dashboarding

## 1.25.1 (September 10, 2026)

- Fix: Form rich-text blocks use the full Thiscovery Editor profile so **Upload image** is available (requires Editor 1.4.3)

## 1.25.0 (September 8, 2026)

- Enh: **Settings → Export** — choose which answers-CSV columns to include; new questions stay included until excluded
- Enh: **Scrub PII** on Export omits identity/personal-data columns, redacts emails, phones, and IPs in remaining cells, and names the file `*-scrubbed.csv`
- Enh: Builder **Contains personal data (PII)** on questions (on by default for email, IP, and panel name/email)
- Change: Answers, Dashboard, and Integrity **Export CSV** always use the saved Export settings (header labels still chosen on Answers)

## 1.24.2 (September 8, 2026)

- Fix: Filling a published edition no longer crashes when snapshot question IDs were replaced (import/restore); answers map onto the live questions

## 1.24.1 (September 8, 2026)

- Fix: **Publish current draft** now saves the studio first, so the live form uses the questions you just edited instead of the previous saved snapshot
- Fix: Fill uses the current published edition (not a previous response’s old edition) unless the respondent is still in progress or editing that response
- Fix: Published fill applies snapshot settings (theme, display, style) as well as questions
- Fix: Question and translation import messages stay visible on Share and Languages

## 1.24.0 (September 7, 2026)

- Enh: Studio **Settings** tab with palette-style left nav (form, programme, quality, publish); Form builder stays a separate top tab
- Enh: **End of survey** settings — thank-you message/button or redirect; already-submitted message/button
- Enh: Form versioning UI (revisions/editions) when Thiscovery Versioning is enabled
- Enh: Appearance themes and style colour controls
- Fix: Studio save no longer wipes questions on empty field payload (Settings-only saves)
- Fix: Languages settings layout; Settings **?** guides; Participant display help

## 1.23.5 (September 5, 2026)

- Fix: Languages settings — “Enabled languages” no longer overlaps the availability hint

## 1.23.4 (September 5, 2026)

- Fix: Studio save no longer wipes all questions when an empty field payload is posted (Settings-only saves). Clear-all still works when confirmed
- Note: SPARCS2 form #17 restored to 69 fields from the original import definition

## 1.23.3 (September 5, 2026)

- Change: Studio keeps **two top tabs** — Form builder and Settings. Form builder is unchanged (field palette + canvas)
- Enh: Settings tab owns the palette-style left nav for form settings plus integrity, translations, CSS, share, versions, and programme tools

## 1.23.2 (September 5, 2026)

- Enh: Studio left rail (palette-style groups/icons) replaces the top tabs — Form builder, settings sections, programme, quality, and publish are all in one place
- Change: Settings no longer use a nested second left nav; sections live in the shared studio rail

## 1.23.1 (September 5, 2026)

- Fix: Settings **?** guidance toggles work again (were opening and immediately closing)
- Enh: Settings left nav matches the studio field palette (grouped labels, icons, short descriptions)

## 1.23.0 (September 5, 2026)

- Enh: Settings tab uses a studio-style left navigation with one section at a time in the main pane
- Enh: New **End of survey** settings — thank-you message with configurable button, or redirect to a URL; already-submitted message (rich text) with optional configurable button
- Change: Thank-you and already-submitted controls moved out of Basics into End of survey

## 1.22.1 (September 5, 2026)

- Fix: Versions tab layout — full-width panel (was capped at 860px), real table styling matching Forms lists, Actions column nowrap so Preview/Publish/Restore/Delete stay on one row
- Fix: Versions tab table layout (aligned columns, Actions header, horizontal action buttons); panel moved outside the studio form to avoid nested-form breakage
- Note: Versioning product toggles live under Administration → Modules → Thiscovery Versioning

## 1.22.0 (September 5, 2026)

- Enh: Form versioning via shared **Thiscovery Versioning** module — every studio save creates a **revision**; **Publish** freezes an **edition** that participants use
- Enh: Versions studio tab (preview, restore, publish, delete); Publish current draft on Share; cannot Open until an edition is published
- Enh: Answers stamp `edition_id`; fill uses published/historical edition snapshot (in-progress keep start edition)
- Enh: Open-period history; Help page **Versions and publishing**
- Note: Enable **thiscovery-versioning** before or with this update; Page Builder adapter comes later

## 1.21.16 (September 4, 2026)

- Fix: Appearance colour controls use a compact swatch + value field; opacity and Transparent live inside the colour picker panel (no more overlapping α / Transparent / Clear buttons in the grid)

## 1.21.15 (September 4, 2026)

- Fix: Scroll-mode grids stay as horizontal tables on mobile (form custom CSS can no longer force a broken card stack that hid column labels after internal codes changed)
- Fix: Stacked mobile grid options show participant labels again; harden stack fieldset/legend styles against theme CSS

## 1.21.14 (September 4, 2026)

- Enh: Named appearance themes (create, edit, delete, import, export, set default) with per-form theme selection or Custom (detached) overrides
- Enh: Per-field variable name and internal label; unique variables; piping/logic resolve by variable; CSV export header mode (label / variable / both)
- Enh: Choice and grid options use separate internal code and participant label fields; if any code is set, all options in that list require codes
- Enh: Grid mobile stacked layout option (single- and multi-select grids)
- Enh: Fill page max width default 1800px (theme/CSS can override)
- Enh: Show/hide title, description, progress, and page numbers — global defaults plus per-form inherit/show/hide
- Enh: Colour pickers support alpha and transparent
- Change: Wave settings are per form (use waves + wave scope); remove global wave toggles; waves optional for longitudinal and EQ-5D
- Fix: Admin module configuration “?” guidance toggles now work outside the form studio

## 1.21.13 (September 4, 2026)

- Fix: Server page skip / go-to-end / go-to-page logic now matches coded choice options (labels vs internal codes), so early exits reach the thank-you page instead of failing validation and restarting the survey
- Fix: Off-path and newly hidden answers are cleared in the fill UI when branching changes; save already dropped unreachable fields once path matching worked
- Fix: Settings “Form type” value no longer overlaps the label / guidance control

## 1.21.12 (September 4, 2026)

- Change: Remove study-specific SPARCS2 survey definition and import-sparcs2 console command from the module
- Fix: Fill view scrolls newly shown conditional content into view (helps mobile)
- Fix: Choice label taps re-run show/hide logic more reliably on mobile
- Enh: Help docs — variables/functions examples; EQ-5D references removed from Help

## 1.21.11 (September 3, 2026)

- Fix: Serve unattached rich-text editor images on anonymous fills (GUID referenced on the form, not only fileManager attachments)

## 1.21.10 (September 3, 2026)

- Fix: Anonymous rich-text/image images by rewriting both `&` and `&amp;` in core file-download URLs

## 1.21.9 (September 3, 2026)

- Fix: Rich text and image area images now load on anonymous form fills via a public form-file endpoint
- Enh: Fill view rewrites core file download URLs to the forms module endpoint for guest respondents

## 1.21.8 (September 3, 2026)

- Enh: CAPTCHA settings (submit and open-rate) work independently of integrity scoring toggle
- Enh: SPARCS2 survey definition with console import command (import-sparcs2)
- Enh: Internal codes on all choice options (radio, dropdown, checkbox) using `code | Label` format
- Enh: Internal codes on grid rows/columns using `[code] Label` format, hidden from respondents
- Enh: Grid fill view strips bracketed code prefixes so respondents see clean labels

## 1.21.7 (September 3, 2026)

- Enh: HumHub Altcha is the default Forms integrity CAPTCHA; Cloudflare Turnstile remains optional via CAPTCHA provider
- Enh: Optional open-rate CAPTCHA gate before the fill page (global and per-form), with open_rate_count / open_rate_window
- Enh: CAPTCHA provider selectable globally and per form (inherit/override); Turnstile keys stay administration-only

## 1.21.6 (September 2, 2026)

- Enh: Align form language catalogue and Settings language grid with Thiscovery Translate when that module is enabled
- Enh: Studio Translations and fill/answer views integrate with Thiscovery Translate for machine translation and response language display
- Fix: Soft-call Translate from fill, resume, and programme flows without requiring the module

## 1.21.5 (September 1, 2026)

- Enh: Map questions can set a basemap style (street, satellite, and other Stadia styles) instead of always using the site default

## 1.21.4 (September 1, 2026)

- Change: When Thiscovery Navigation is enabled, forms are added to the top bar there instead of from this module

## 1.21.3 (August 30, 2026)

- Fix: Headerless fill pages keep top padding (including iOS safe area) so the title is not cut off
- Fix: Grid column headings keep equal width and wrap on words instead of hyphenating mid-word
- Fix: Overflowing grid questions show a scroll hint, edge fade, and arrow so people can see there are more options

## 1.21.2 (August 29, 2026)

- Change: Map question type appears in the builder only when Thiscovery Mapping is installed and enabled
- Fix: New map questions and CSV/JSON imports are rejected when Mapping is off; existing map fields are kept

## 1.21.1 (August 29, 2026)

- Fix: CAPTCHA “only when suspicious” now challenges on the next attempt and requires a pass before accept
- Fix: Automatic exclusion writes a system reason and an audit log entry
- Fix: Form results charts and dashboard totals omit responses excluded from analysis
- Enh: Straight-lining detects consecutive radio/dropdown Likert sets with the same options
- Enh: Integrity dashboard lists similar-response groups (clusters)
- Enh: Free-text quality flags near-identical answers pasted across two questions on the same response
- Enh: Duplicate detection soft-matches nearby network hashes; invitation links support optional expiry
- Enh: Audit history shows who made the change; question timings show field labels

## 1.21.0 (August 28, 2026)

- Enh: Response integrity tab — bot protection, quality scoring, review, quarantine, and unique invitation links
- Enh: On-screen Guidance (?) on each Response integrity setting, plus a Help page for the feature
- Fix: Integrity ethics pass — one signal cannot mark Suspicious/Excluded; human overrides survive rescore; Hash IP Off stores no IP hashes; exact attention-check matching; technical flags hidden from CSV and non-managers
- Enh: Each answer shows quality score and Include in analysis on the response itself; Answers list adds Analysis column and sortable scores
- Change: Integrity checks are off until you tick Enable integrity checks (site admin and per survey). Scores are not recorded when it is off

## 1.20.8 (August 22, 2026)

- Fix: Back is hidden on the first page. Next and Submit stay on the right

## 1.20.7 (August 22, 2026)

- Fix: Fill navigation keeps Back on the left and Next/Submit on the right on every page. Back stays in place on the first page (disabled) so the bar does not jump

## 1.20.6 (August 22, 2026)

- Fix: Missing required answers no longer show a browser dialog or leave Next/Submit spinning. Errors appear on the questions, and the buttons stay usable

## 1.20.5 (August 22, 2026)

- Fix: Email questions now check that the answer is a valid email address when the person continues or submits. The builder states this on the field

## 1.20.4 (August 22, 2026)

- Fix: Completing a form with an email did not add the person to the panel, because the answer’s field values were still empty when enrolment ran. Preview fills now record on the panel as well

## 1.20.3 (August 21, 2026)

- Fix: Completing a form that uses a panel now adds the person to that panel when they give an email (email question, panel email field, or signed-in account). You do not need a separate enrolment setting for the same panel

## 1.20.2 (August 21, 2026)

- Fix: Form builder could not be used because a JavaScript error in the studio script stopped the editor from loading

## 1.20.1 (August 21, 2026)

- Fix: Completing a form that uses a panel now records the completion on the matching member (invite link, signed-in email, or email on the form), including Preview. You no longer need a separate “record completions” tick just because the panel was attached on Panel & waves

## 1.20.0 (August 21, 2026)

- Enh: Panels can have extra member fields. Search them, import them in CSV, pipe `{{member.field_key}}` into surveys and email, and drop them from Add fields
- Enh: The panel and member pages list what is stored on a member and what is recorded when they complete a form

## 1.19.4 (August 21, 2026)

- Fix: Questions inside a group can be opened and edited again

## 1.19.3 (August 21, 2026)

- Fix: Logic on a question group now shows and hides the questions inside it, not only the group title
- Fix: Groups have a Logic control on the group itself (Show this group if / Hide this group if)

## 1.19.2 (August 21, 2026)

- Enh: Respondent metadata is a section in Add fields. Drop IP, browser, operating system, device, screen size, language, time zone, or user agent as separate hidden fields so each is its own analysis column

## 1.19.1 (August 21, 2026)

- Fix: Respondent metadata now records the respondent’s IP address

## 1.19.0 (August 21, 2026)

- Enh: Respondent metadata question captures device, browser, screen, language, and time zone without showing anything to participants
- Enh: Any question can be hidden from respondents so it can store an internal variable
- Enh: Choice options support an internal code plus a participant-facing label (`code | Label`). Answers store the code; people only see the label

## 1.18.20 (August 21, 2026)

- Fix: Deleting a library item removes it from the list

## 1.18.19 (August 21, 2026)

- Fix: A question group saved to the library can be dropped onto the form, including the questions inside it. Groups are not inserted inside another group

## 1.18.18 (August 21, 2026)

- Fix: Saved library questions can be clicked or dragged onto the form, matching Add fields

## 1.18.17 (August 21, 2026)

- Fix: Required questions on pages the respondent never visits no longer block Submit. A later page with nothing visible is skipped, so a closing page that only shows for one branch does not appear on the other branch

## 1.18.16 (August 21, 2026)

- Fix: Fill, preview, and test no longer show question numbers. Question groups no longer have a side bar

## 1.18.15 (August 21, 2026)

- Fix: Save and resume only runs when that setting is on and the person chooses to continue. Preview and signed-in fill no longer autosave or restore a draft when resume is off

## 1.18.14 (August 21, 2026)

- Fix: Studio clicks and adding fields work again. Logic on a question group no longer copies rules from the questions inside it

## 1.18.13 (August 21, 2026)

- Fix: Next stays on screen when an answer hides later questions. Submit only appears for Go to end, or when there is no later page. A question group without a closing marker no longer hides the rest of the page

## 1.18.12 (August 21, 2026)

- Enh: Question groups let you nest questions in the builder and show or hide the whole block with one Logic rule

## 1.18.11 (August 21, 2026)

- Fix: “Go to page” from a page break now opens that page. Fill no longer treats the jump as the end of the form and swap Next for Submit. Surrounding quotation marks in logic values are ignored

## 1.18.10 (August 21, 2026)

- Fix: Logic on a page break (go to page, go to end, skip page) is applied when the respondent clicks Next, matching the studio Logic panel. Previously only branch rules on the break were used

## 1.18.9 (August 21, 2026)

- Enh: Checkbox questions can require a minimum number of selections, or every option, before the respondent can continue. An exclusive choice such as “None of these” still counts as a complete answer on its own

## 1.18.8 (August 21, 2026)

- Fix: Field, page, and submit action emails are not sent to a save-and-resume address. That address is only used for the resume code. Action emails still send when the person gave an email on the form, is a panel member, has a registered account, or is signed in

## 1.18.7 (August 21, 2026)

- Fix: Respondent emails (completion, field/page/submit actions) send only when there is a valid address from an email question, save-and-resume, a panel member, or a registered account. Anonymous replies with no address are skipped and are not added to a panel

## 1.18.6 (August 21, 2026)

- Fix: Creating or saving an email template no longer returns 403 Forbidden. Network templates save on the same route as form editing, and editor HTML plus placeholders are posted in a WAF-safe encoding

## 1.18.5 (August 21, 2026)

- Fix: Question actions default to None. Send email and other actions are only used when you add them

## 1.18.4 (August 21, 2026)

- Fix: File upload questions store the file and show it in answers. Anonymous participants can upload without a “not allowed” error

## 1.18.3 (August 21, 2026)

- Fix: Multiple choice and rating questions start unanswered. Options are not pre-ticked, do not look selected, and are not stored until the participant chooses

## 1.18.2 (August 21, 2026)

- Fix: Show/hide logic is kept when you edit other questions. The builder remembers the source question, does not rebuild those lists on every label keystroke, and save falls back to a stored snapshot if a dropdown is briefly empty

## 1.18.1 (August 21, 2026)

- Fix: Conditional display (and carry-forward / page-branch sources) stopped working after the form was saved again, because the builder rebuilt those dropdowns with different question ids

## 1.18.0 (August 21, 2026)

- Enh: Question import can replace every field or append (unticked). CSV supports all question types, page breaks, and extra columns
- Enh: Help page for CSV field types and spreadsheet layout, linked from the Share tab

## 1.17.1 (August 20, 2026)

- Fix: Related Help pages are clickable links

## 1.17.0 (August 20, 2026)

- Enh: In-product Help with sections for administrators and form creators. Open it from the forms list, studio, dashboard, panels, email templates, and module configuration

## 1.16.3 (August 20, 2026)

- Enh: Administration menu lists the module as Thiscovery Forms

## 1.16.2 (August 20, 2026)

- Enh: Form builder Add fields / Library and the question canvas each scroll on their own, so one list no longer pushes the other off the screen
- Fix: Forms can have any number of fields. Saving no longer stops around 20 questions because of PHP’s input variable limit

## 1.16.1 (August 19, 2026)

- Fix: Autosave with Keep incomplete responses updates one draft instead of creating a new response per field
- Fix: Answers and the dashboard only list genuine in-progress drafts, not autosave snapshots from a completed fill

## 1.16.0 (August 19, 2026)

- Enh: Form email templates use Thiscovery Editor for header, body, and footer, with the same branded layout as other Thiscovery emails

## 1.15.6 (August 19, 2026)

- Enh: Form Settings is organised into collapsible sections (Basics open first). Field help is a compact question mark

## 1.15.5 (August 19, 2026)

- Enh: Each setting on the form Settings tab has collapsible Guidance the form creator can expand

## 1.15.4 (August 19, 2026)

- Fix: Opening a headerless form from the list (and Edit back to studio) does a full page load so the HumHub header hides or returns correctly

## 1.15.3 (August 19, 2026)

- Enh: Form setting to run fill, preview, and thank-you pages without the HumHub header or space menu

## 1.15.2 (August 19, 2026)

- Fix: Deleting a form from the list actually removes it instead of only hiding it in the stream
- Enh: Delete form is available in the form builder header and footer

## 1.15.1 (August 19, 2026)

- Fix: Saving a form keeps you in the studio instead of opening the fill page
- Enh: Preview in the studio header and footer saves first, then opens the test form

## 1.15.0 (August 19, 2026)

- Enh: EQ-5D is a standalone form type (five one-question pages plus thermometer). Paste licensed wording; official EuroQol text is not shipped
- Enh: Module configuration chooses whether waves live per survey or per panel, and whether ordinary surveys can use waves
- Enh: Wave 1 of an EQ-5D (or other wave) form can be filled without a panel token when anonymous fill is on; later waves still need an invitation
- Enh: EQ-5D CSV export adds a 5-digit health profile and VAS with blank stored as 999

## 1.14.0 (August 18, 2026)

- Enh: Field, page, and submit actions replace the on-form email button — run standard functions (send email, set variable, go to page, go to end) or named custom functions, several in order
- Enh: Custom functions/variables on form settings can be reused as `{{var:name}}` in emails and action values
- Enh: Settings and action rows explain how custom functions work, including name, formula, and `{{var:name}}`
- Enh: Existing action-button fields are converted to submit email actions

## 1.13.0 (August 18, 2026)

- Enh: Panel list, member, and edit screens use the same list tables, Open actions, and form cards as the rest of the module
- Enh: Each panel member is a record you can open, edit, and view completions from
- Enh: Email templates are a separate entity, written with Thiscovery Editor, and chosen on forms for invites, waves, reminders, and post-completion emails
- Enh: Action button field sends a chosen email template from the fill page

## 1.12.0 (August 18, 2026)

- Enh: Panels are a standalone module entity with their own list and member screens (not only inside longitudinal studio)
- Enh: Add panel members by selecting people already on the site, by email with first and last name, or by CSV upload
- Enh: Form setting to add completers to an existing or new panel, and an option to record each completion on that panel
- Enh: One person can belong to several panels; longitudinal waves still attach a shared panel for invites

## 1.11.1 (August 18, 2026)

- Enh: Fill and thank-you pages use right-to-left layout for Arabic and Urdu (`dir="rtl"`), including a mirrored thermometer scale. Urdu is available on the language list.

## 1.9.0 (August 18, 2026)

- Enh: Preview / test mode with a shareable link — test answers are not counted as participant submissions
- Enh: Save as template remains on the Share tab and is labelled by form type for reuse
- Enh: Optional public dashboard link (enable per form) so external people can view aggregate results without signing in
- Enh: Optional keep-incomplete-responses setting stores in-progress answers, includes them in CSV export, and shows an in-progress count on the dashboard

## 1.8.0 (August 15, 2026)

- Enh: Module settings page to enable or disable form types (Administration → Modules → Thiscovery Forms → Configure)
- Enh: CSS tab accordion for page, card, questions, buttons, and each question type, plus a custom CSS box; blank values keep the site theme
- Enh: Dropdown, radio, and checkbox options named Other show a text box so respondents can type their own answer

## 1.7.0 (August 15, 2026)

- Enh: Save and resume is now a per-form setting on every form type; it is off unless enabled
- Enh: When resume is on, opening the form asks whether to continue a saved response or start a new one (anonymous respondents always see this choice)
- Enh: When multiple submissions are not allowed and the person has already submitted, a configurable message is shown instead of the resume choice

## 1.6.0 (August 15, 2026)

- Enh: **Project** form kind — structured records with a published catalogue
- Enh: Configurable approval stages; each stage can assign users and/or groups, with any-one or all-must-approve
- Enh: Submit for review, request changes, publish, archive, and a decision log on each record

## 1.5.1 (August 15, 2026)

- Enh: Rich text blocks, thank-you messages, and consensus round summaries use Thiscovery Editor instead of HumHub markup

## 1.5.0 (August 15, 2026)

- Git release of form kinds and studio (library, templates, question import/export), compound logic and research types, longitudinal and consensus programmes, and translation overlay with export/import (1.2.0–1.4.3)

## 1.4.3 (August 14, 2026)

- Enh: Export all source questions for translation (CSV or JSON) and import overlays back in multiple languages from the Translations tab

## 1.4.2 (August 14, 2026)

- Enh: Sample JSON and CSV question files can be downloaded from the Share tab
- Fix: CSV question import keeps options that span more than one line

## 1.4.1 (August 14, 2026)

- Enh: Email panel members automatically when a later wave opens (Wave 2 onwards), including scheduled start times

## 1.4.0 (August 14, 2026)

- Enh: Longitudinal surveys — hybrid panel (signed-in users or email tokens), waves on the same form, invite emails, wave completion and drop-off on the dashboard
- Enh: Consensus / Delphi — rounds, published summaries, identity modes, optional/required comments, weighted votes, freeze items that reach a threshold
- Enh: Translation overlay with a language switcher on the fill page (source language plus extra locales such as Welsh)

## 1.3.0 (August 14, 2026)

- Enh: Compound logic (AND/OR) with show, hide, skip question, skip page, go to page, and go to end
- Enh: Answer piping on labels, help text, page titles, rich text, and HTML; carry-forward choices from earlier questions
- Enh: Research question types — grid (single/multi), best–worst, MaxDiff, drill-down, and image area select/evaluate
- Enh: Dashboards and CSV export cover the new structured answer types

## 1.2.0 (August 14, 2026)

- Enh: Form kinds — Survey, Quick poll, Feedback, Longitudinal, Consensus — chosen on create
- Enh: Quick polls are a one-question form, vote inline on the stream, and embed on engagement pages
- Enh: Question and block library, save a form as a template, create from a template
- Enh: Import and export questions as JSON or CSV

## 1.1.0 (August 13, 2026)

- Enh: Per-form setting to allow or prevent respondents editing a completed submission (managers can still update answers)
- Enh: Checkbox fields support a maximum number of selections and exclusive options (for example "None of these")
- Enh: Text fields can be pre-filled from a user profile attribute
- Enh: Fill page shows the form title and description
- Enh: Guests opening a signed-in form are redirected to login
- Fix: Completing a question no longer scrolls the fill page back to the top
- Fix: Builder field order is preserved (posted sort order is no longer rewritten by PHP numeric array keys)

## 1.0.0 (August 13, 2026)

- Initial release of Thiscovery Forms for space and network-level forms, including multi-page surveys, anonymous fill, save-and-resume, dashboards, and CSV export
