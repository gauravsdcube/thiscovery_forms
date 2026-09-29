# Changelog

All notable changes to this module are documented in this file.

## 1.29.0

### Loops

Loops are off unless an administrator turns them on and the form turns them on. A question group can repeat once, from a fixed list, from selected options, or from a number. Nested loops and rosters are not in this version.

- Each repeat is stored on its own answer cell. Unselected repeats stay in the table. The default export leaves them blank. “These answers are kept but not shown.”
- Wide export columns look like `symptom__asthma`. The codebook lists the same names. Logic can use any, all, count, or sum. An unknown aggregate cannot be published.
- Reducing a number hides the later repeats and does not renumber the ones that remain.

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
