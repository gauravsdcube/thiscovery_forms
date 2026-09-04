# Changelog

All notable changes to this module are documented in this file.

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
