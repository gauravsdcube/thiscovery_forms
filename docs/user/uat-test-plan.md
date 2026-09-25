# Thiscovery Forms — User Acceptance Test (UAT) Plan

Use this plan to verify builders, variables, logic, settings, fill experience, participant journeys, mobile surveys, results, and related features.

## How to use

This page is the tester handout. Work through scenarios here, or download the CSV tracker:

**[Download UAT scenarios CSV](uat-scenarios.csv)** (login required — same permission as Help).

You should also see a blue **UAT scenarios CSV** button above this article and in the page header.

1. **Assign work** — give testers one or more **Test IDs**, or ask them to run all **Must** scenarios first, then **Should**. Prefer a **Participant** pass (including **Mobile surveys**) on real phones before sign-off.
2. **Prepare** — use a dedicated Draft survey (or a copy of a real form). Keep it Draft until a scenario needs Open / publish / guest fill.
3. **Run a scenario** — open the matching **Test ID** section below. Check preconditions, follow the numbered steps, then compare with **Expected behaviour**.
4. **Record the result** — tick Pass / Fail / Blocked, write tester name, date, and notes. For Excel/Sheets, use the CSV download above, then fill the Result / Tester / Date / Notes columns.
5. **Report defects** — note the Test ID, what you saw, and a screenshot or URL when something fails.
6. **Sign off** — when Must scenarios are done, complete the sign-off table at the bottom.

## Environment & roles

| Role | Needs |
| --- | --- |
| Admin | Module config, permissions, enable form types |
| Creator | Create/edit forms in a space or globally |
| Participant | Fill forms (member and/or guest as scenarios require); include phone and tablet where marked Mobile |
| Reviewer | Results / integrity review where applicable |

**Suggested test form:** a dedicated Draft survey (a copy of a real form is fine). Keep status Draft until publish/open scenarios.

**Total scenarios:** 181
**Priority mix:** 119 Must · 62 Should

## Feature index

1. **Form types** — 6 scenarios (`UAT-TYP-*`)
2. **Builder** — 9 scenarios (`UAT-BLD-*`)
3. **Variables & piping** — 5 scenarios (`UAT-VAR-*`)
4. **Logic** — 7 scenarios (`UAT-LOG-*`)
5. **Settings — Basics** — 2 scenarios (`UAT-SET-*`)
6. **Settings — Access** — 4 scenarios (`UAT-SET-*`)
7. **Settings — Display** — 2 scenarios (`UAT-SET-*`)
8. **Settings — Sharing** — 2 scenarios (`UAT-SET-*`)
9. **End of survey** — 3 scenarios (`UAT-END-*`)
10. **Response integrity** — 7 scenarios (`UAT-INT-*`)
11. **Panels & waves** — 4 scenarios (`UAT-PNL-*`)
12. **Consensus rounds** — 1 scenarios (`UAT-RND-*`)
13. **Project approval** — 2 scenarios (`UAT-APR-*`)
14. **Translations** — 3 scenarios (`UAT-TRN-*`)
15. **Share & preview** — 6 scenarios (`UAT-SHR-*`)
16. **Versions** — 3 scenarios (`UAT-VER-*`)
17. **Fill experience** — 4 scenarios (`UAT-FIL-*`)
18. **Participant journey** — 22 scenarios (`UAT-PAR-*`)
19. **Mobile surveys** — 20 scenarios (`UAT-MOB-*`)
20. **Usability** — 2 scenarios (`UAT-UX-*`)
21. **Field types** — 8 scenarios (`UAT-FLD-*`)
22. **Pages & navigation** — 4 scenarios (`UAT-PG-*`)
23. **Save & resume** — 3 scenarios (`UAT-RES-*`)
24. **Answers & dashboard** — 4 scenarios (`UAT-ANS-*`)
25. **Survey responses** — 7 scenarios (`UAT-RSP-*`)
26. **Permissions** — 3 scenarios (`UAT-PER-*`)
27. **Studio UI** — 4 scenarios (`UAT-UI-*`)
28. **CSS & themes** — 5 scenarios (`UAT-CSS-*`)
29. **Context** — 2 scenarios (`UAT-CTX-*`)
30. **Email** — 2 scenarios (`UAT-EML-*`)
31. **Export** — 11 scenarios (`UAT-EXP-*`)
32. **Import** — 6 scenarios (`UAT-IMP-*`)
33. **Functions & actions** — 5 scenarios (`UAT-FN-*`)
34. **Organisation** — 3 scenarios (`UAT-ORG-*`)

---

## Form types

### UAT-TYP-001 — Create a survey

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Surveys are the default multi-question form type. |
| **Preconditions** | User can create forms (space or global). |

**Steps**

1. Open Forms → New form
2. Choose Survey
3. Enter a title and save

**Expected behaviour:** Studio opens on Form builder; form appears in list as Draft.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TYP-002 — Create a quick poll

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Polls are limited to one answerable question. |
| **Preconditions** | Poll type enabled in module settings. |

**Steps**

1. New form → Quick poll
2. Add one radio/checkbox question
3. Try adding a second question

**Expected behaviour:** Second answerable question is blocked or warned; poll can be embedded / show results after vote.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TYP-003 — Create from template

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Templates seed structure without answers. |
| **Preconditions** | At least one saved template exists. |

**Steps**

1. New form → choose template
2. Save and open builder

**Expected behaviour:** Fields match template; no answers copied; type label may show Template.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TYP-004 — Longitudinal form uses waves

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Longitudinal forms always use waves. |
| **Preconditions** | Longitudinal type enabled. |

**Steps**

1. Create Longitudinal form
2. Open Settings / Panel & waves

**Expected behaviour:** Waves are required; Panel & waves available after save.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TYP-005 — Consensus form has Rounds

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Consensus collects one response per round. |
| **Preconditions** | Consensus type enabled. |

**Steps**

1. Create Consensus form
2. Open Rounds in Settings rail

**Expected behaviour:** Rounds section is available; one submission per person per round.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TYP-006 — Project form has Approval

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Project records go through approval before catalogue. |
| **Preconditions** | Project type enabled. |

**Steps**

1. Create Project form
2. Open Approval in Settings rail
3. Configure a stage

**Expected behaviour:** Approval stages can be saved; submissions require review before catalogue.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Builder

### UAT-BLD-001 — Add and reorder fields

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Palette adds fields; drag reorders canvas. |
| **Preconditions** | Open studio on Form builder. |

**Steps**

1. Click Text, Textarea, Radio from palette
2. Drag to reorder
3. Save

**Expected behaviour:** Order persists after reload; labels editable.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-002 — Required field validation

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Required fields block submit when empty. |
| **Preconditions** | Form Open with published edition; one required text field. |

**Steps**

1. Open fill link
2. Leave required blank and submit/next

**Expected behaviour:** Error shown; cannot continue until filled.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-003 — Choice options with codes

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Each choice has Internal code and Participant label. Fill shows labels; answers CSV stores codes. |
| **Preconditions** | Radio or dropdown on canvas. |

**Steps**

1. Set Internal code yes with Participant label Yes, and no with No
2. Save, fill, choose Yes
3. Answers → Export CSV

**Expected behaviour:** Respondent sees Yes; answers CSV cell is yes (the code). Settings → Export column ticks do not change stored codes.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-004 — Page breaks and multi-page fill

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Page breaks split fill into pages. |
| **Preconditions** | Form with 2+ page breaks and questions on each page. |

**Steps**

1. Preview or fill
2. Use Next / Back
3. Submit on last page

**Expected behaviour:** Pages advance correctly; Back restores prior answers.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-005 — Question group nesting

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Groups nest questions; page break cannot sit inside a group. |
| **Preconditions** | Builder open. |

**Steps**

1. Add Question group
2. Add fields inside group
3. Attempt page break inside group

**Expected behaviour:** Fields nest; page break inside group prevented or moved outside.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-006 — Library save and reuse

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Library stores reusable blocks across forms. |
| **Preconditions** | Two forms available. |

**Steps**

1. Save a field/group to Library
2. Open second form → Library tab
3. Insert item

**Expected behaviour:** Block appears with same type/options; can edit independently after insert.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-007 — Rich text / HTML blocks

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Instruction blocks do not collect answers. |
| **Preconditions** | Builder open. |

**Steps**

1. Add Rich text with instructions
2. Fill and submit
3. Check answers CSV

**Expected behaviour:** Instructions visible on fill; no answer column for that block.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-008 — Clear all fields requires confirmation

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Clear all must not wipe silently. |
| **Preconditions** | Form with several fields. |

**Steps**

1. Click Clear all fields
2. Cancel confirm
3. Confirm, then Save

**Expected behaviour:** Cancel keeps fields; confirm empties canvas; Save persists empty only after clear confirm.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-BLD-009 — Contains personal data (PII) flag

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Answer-collecting questions can be tagged Contains personal data so Scrub PII omits them from the answers CSV. |
| **Preconditions** | Studio Form builder open. |

**Steps**

1. Add Email — Contains personal data is on
2. Add Text — it is off
3. Tick PII on the text field
4. Add respondent-meta IP — PII is on
5. Save and reload builder

**Expected behaviour:** Email and IP default on; ordinary text defaults off until ticked; ticks persist. Panel first-name/email also default on if you add those fields. User-agent metadata does not default on. Flag is hidden on page breaks and rich text.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Variables & piping

### UAT-VAR-001 — Pipe previous answer into later label

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Use {{answer:variable}} or field tokens in later questions. |
| **Preconditions** | Q1 text with variable name; Q2 label contains pipe token. |

**Steps**

1. Answer Q1 as 'Alex'
2. Go to Q2

**Expected behaviour:** Q2 label shows Alex (or configured token output).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VAR-002 — Pipe option label vs code

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Label piping shows human text for choices. |
| **Preconditions** | Dropdown with codes; later rich text uses :label pipe. |

**Steps**

1. Select an option
2. View later page with pipe

**Expected behaviour:** Piped text shows option label, not internal code.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VAR-003 — User and form tokens

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | {{user.*}} and {{form.title}} resolve for signed-in users. |
| **Preconditions** | Signed-in user; label with {{form.title}} and {{user.firstname}} if supported. |

**Steps**

1. Open fill while logged in
2. Open same as guest if anonymous allowed

**Expected behaviour:** Logged-in sees values; guest user tokens empty; form title still resolves.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VAR-004 — Set variable action

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Field/page/submit actions can set variables for later pipes/emails. |
| **Preconditions** | Action 'set variable' on submit or field. |

**Steps**

1. Configure set variable name=test_score value=1
2. Complete form
3. Check thank-you or email using {{var:test_score}}

**Expected behaviour:** Variable appears in thank-you/email content.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VAR-005 — Carry-forward options

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Later choice list can reuse earlier selections. |
| **Preconditions** | Two choice questions; second carries selected from first. |

**Steps**

1. Select A and C on Q1
2. Open Q2

**Expected behaviour:** Q2 options reflect carry-forward rule (selected/unselected/all).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Logic

### UAT-LOG-001 — Show if equals

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Question appears only when condition matches. |
| **Preconditions** | Q1 radio Yes/No; Q2 show if Q1=Yes. |

**Steps**

1. Choose No → Q2 hidden
2. Choose Yes → Q2 shown
3. Submit with Yes path

**Expected behaviour:** Q2 only required/visible on Yes; No path submits without Q2.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-002 — Hide if equals

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Hide action removes question from path. |
| **Preconditions** | Q2 hide if Q1=No. |

**Steps**

1. Choose No
2. Confirm Q2 not shown

**Expected behaviour:** Hidden question not required.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-003 — Page branch go to page

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Page break branches skip pages. |
| **Preconditions** | Pages A→B→C; branch on A end: if X go to C. |

**Steps**

1. Answer to trigger branch
2. Click Next

**Expected behaviour:** Land on C, not B; Back behaviour is sensible.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-004 — Go to end if

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Branch can finish early. |
| **Preconditions** | Eligibility question with go-to-end if ineligible. |

**Steps**

1. Choose ineligible
2. Next

**Expected behaviour:** Form ends (thank-you/redirect); later pages not shown.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-005 — Group-level show/hide

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Entire group visibility follows logic. |
| **Preconditions** | Question group with show-if rule. |

**Steps**

1. Fail condition → group hidden
2. Pass condition → all group fields shown

**Expected behaviour:** Group shows/hides together.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-006 — AND / OR combinators

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Multiple rules combine as configured. |
| **Preconditions** | Two conditions with AND and a second test with OR. |

**Steps**

1. Satisfy only one rule under AND → stay hidden
2. Satisfy both → show
3. Repeat for OR

**Expected behaviour:** Combinator behaviour matches settings.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-LOG-007 — Submit actions run once

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | On-submit actions fire after successful submit. |
| **Preconditions** | Submit action: send email or set variable. |

**Steps**

1. Complete form
2. Check email/variable side effect

**Expected behaviour:** Action runs once per successful submit; not on preview if documented as excluded.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Settings — Basics

### UAT-SET-001 — Draft vs Open vs Closed

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Open needs published edition when versioning on. |
| **Preconditions** | Versioning enabled; form with fields. |

**Steps**

1. Try Open without publish → blocked/warned
2. Publish edition
3. Set Open and save
4. Set Closed

**Expected behaviour:** Open only after publish; Closed stops new submits; draft only managers/preview.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-002 — Who can view answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Answers visibility modes restrict access. |
| **Preconditions** | Form with completed answer; second user without manage. |

**Steps**

1. Set Author and managers only
2. As respondent try Answers
3. Switch to Managers and respondents

**Expected behaviour:** Access matches selected mode.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Settings — Access

### UAT-SET-003 — Allow multiple submissions

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Off blocks second submit. |
| **Preconditions** | Multiple off; user completed once. |

**Steps**

1. Submit once
2. Open fill again

**Expected behaviour:** Already-submitted message (and optional button) shown.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-004 — Anonymous submissions

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Guests can fill when anonymous on and form Open. |
| **Preconditions** | Anonymous on; Open; published. |

**Steps**

1. Open fill in private window logged out
2. Submit

**Expected behaviour:** Submit succeeds; identity not stored as HumHub user.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-005 — Save and resume

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | In-progress can be resumed with code. |
| **Preconditions** | Save & resume on; multi-page form. |

**Steps**

1. Fill page 1, use save/continue later
2. Note code
3. Return later and resume

**Expected behaviour:** Answers restored; can finish submit.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-006 — Edit after submit

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | When allow edit on, respondent can change own answer. |
| **Preconditions** | Allow edit on; user has completed. |

**Steps**

1. Open form again
2. Edit and save

**Expected behaviour:** Changes stored; when off, confirmation only.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Settings — Display

### UAT-SET-007 — Hide title and progress

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Participant display overrides site defaults. |
| **Preconditions** | Display section available. |

**Steps**

1. Set Show form title = Hide
2. Set progress = Hide
3. Preview fill

**Expected behaviour:** Title/progress not shown on fill; other chrome unchanged.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-008 — Use site default inherit

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Empty overlay inherits admin defaults. |
| **Preconditions** | Admin default show title on. |

**Steps**

1. Set form to Use site default
2. Preview

**Expected behaviour:** Matches Administration → Modules → Thiscovery Forms display defaults.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Settings — Sharing

### UAT-SET-009 — Hide HumHub header

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Headerless fill for kiosk-style links. |
| **Preconditions** | Run without HumHub header on. |

**Steps**

1. Open fill link

**Expected behaviour:** Site/space chrome hidden; form usable.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SET-010 — Show in side menu

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Form appears in space/network menu when enabled. |
| **Preconditions** | Show in side menu on; form Open. |

**Steps**

1. Save
2. Check space menu / navigation

**Expected behaviour:** Menu entry opens fill.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## End of survey

### UAT-END-001 — Thank-you message and button

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Message mode shows configurable thank-you + button. |
| **Preconditions** | Completion = Show message; custom HTML; button on with label/URL. |

**Steps**

1. Submit form
2. Read thank-you
3. Click button

**Expected behaviour:** Custom message shown; button label correct; URL opens (blank URL → form).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-END-002 — Redirect after submit

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Redirect mode skips thank-you for live submits. |
| **Preconditions** | Completion = Redirect; valid https URL. |

**Steps**

1. Submit (not preview)
2. Observe navigation

**Expected behaviour:** Browser goes to redirect URL; preview still shows message note if designed so.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-END-003 — Already submitted message and button

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Blocked repeat submit uses custom copy + optional button. |
| **Preconditions** | Multiple off; custom already-submitted message; button enabled. |

**Steps**

1. Submit once
2. Open fill again

**Expected behaviour:** Custom message (with {formName} if used); button works.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Response integrity

### UAT-INT-001 — Enable integrity scoring

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | When enabled, completed responses get quality metadata. |
| **Preconditions** | Integrity on for form. |

**Steps**

1. Complete a fill
2. Open Answers / integrity view

**Expected behaviour:** Score/state present; preview/test may be unscored per product rules.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-002 — Honeypot / bot signal

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Filled honeypot contributes to review signals. |
| **Preconditions** | Integrity on with bot protection. |

**Steps**

1. As tester, fill honeypot via DOM if possible or use tool guidance
2. Submit

**Expected behaviour:** Response flagged for review; not auto-deleted.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-003 — Attention check

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Wrong attention answer affects score. |
| **Preconditions** | Hidden attention-check field configured. |

**Steps**

1. Answer incorrectly
2. Submit and review

**Expected behaviour:** Signal recorded; single signal alone cannot force Excluded if ethics rules apply.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-004 — Invitation access mode

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Unique invitation links gate fill. |
| **Preconditions** | Access mode = invitation links; generate token. |

**Steps**

1. Open form without token → denied
2. Open with ?access=token → allowed

**Expected behaviour:** Only valid token works; reuse rules per settings.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-005 — Manager override analysis state

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Managers can override with reason. |
| **Preconditions** | Completed answer with Review state. |

**Steps**

1. Open answer integrity
2. Override to Trusted with reason
3. Save

**Expected behaviour:** State updates; reason stored; reversible.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-006 — CAPTCHA / Turnstile on submit

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Bot protection when enabled. |
| **Preconditions** | CAPTCHA enabled for form. |

**Steps**

1. Open fill as participant
2. Submit without completing CAPTCHA
3. Complete and submit

**Expected behaviour:** Blocked then allowed after CAPTCHA.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-INT-007 — Quality score on answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Scores appear for managers when enabled. |
| **Preconditions** | Integrity scoring on; ≥1 answer. |

**Steps**

1. Submit
2. Open integrity / answer detail

**Expected behaviour:** Score/flags visible; can mark exclude.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Panels & waves

### UAT-PNL-001 — Create panel and add members

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Panels hold members for wave invites. |
| **Preconditions** | Panels permission. |

**Steps**

1. Open Panels
2. Create panel
3. Add member by email/account

**Expected behaviour:** Member listed; can receive invites.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PNL-002 — Open wave and invite

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Wave open sends/allows invite links. |
| **Preconditions** | Longitudinal or survey with Use waves; panel attached. |

**Steps**

1. Create wave
2. Open wave
3. Invite member
4. Member opens personal link

**Expected behaviour:** Member can fill for that wave only once.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PNL-003 — Wave 2 requires panel token

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Later waves are not open public fill. |
| **Preconditions** | Wave 1 complete; Wave 2 open. |

**Steps**

1. Try public form URL for wave 2
2. Use invite token

**Expected behaviour:** Public path blocked or incomplete; token works.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PNL-004 — Panel enrolment on complete

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Completers can be added to enrolment panel. |
| **Preconditions** | Enrol settings: existing panel; form collects email. |

**Steps**

1. Submit with email
2. Check panel membership

**Expected behaviour:** Member appears / completion recorded per settings.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Consensus rounds

### UAT-RND-001 — One response per round

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Cannot submit twice in same round. |
| **Preconditions** | Consensus form; round open. |

**Steps**

1. Submit in round 1
2. Open again

**Expected behaviour:** Blocked or edit rules per settings; round advances only when configured.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Project approval

### UAT-APR-001 — Submit for review and approve

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Stages gate catalogue publication. |
| **Preconditions** | Project form with one approval stage; approver user. |

**Steps**

1. Respondent submits record
2. Approver approves
3. Check catalogue

**Expected behaviour:** Appears in catalogue only after approval; decision log updated.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-APR-002 — Request changes

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Approver can send back for edits. |
| **Preconditions** | Item in review. |

**Steps**

1. Request changes with comment
2. Author edits and resubmits

**Expected behaviour:** Status reflects changes requested; comment visible.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Translations

### UAT-TRN-001 — Enable second language and translate

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Fill language switcher uses enabled languages. |
| **Preconditions** | Languages: en-GB + fr (or available). |

**Steps**

1. Enable FR on Settings → Languages
2. Translations tab: translate a label
3. Fill and switch language

**Expected behaviour:** Switcher shows FR; translated label appears; missing strings fall back to source.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TRN-002 — RTL language

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Arabic/Urdu enable RTL layout. |
| **Preconditions** | Arabic or Urdu enabled and partially translated. |

**Steps**

1. Switch fill language to AR/UR

**Expected behaviour:** Page direction RTL; content readable.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-TRN-003 — Export/import translation CSV

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Overlays import without replacing questions. |
| **Preconditions** | Form with fields; export translations. |

**Steps**

1. Export translation file
2. Edit a string
3. Import

**Expected behaviour:** Overlay updates; question structure unchanged.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Share & preview

### UAT-SHR-001 — Live link vs preview link

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Preview answers are tests excluded from results. |
| **Preconditions** | Form saved Open. |

**Steps**

1. Submit via Preview link
2. Submit via live link
3. Check dashboard counts

**Expected behaviour:** Preview not in totals; live counted.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SHR-002 — Copy share URL

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Share tab provides copyable https URL. |
| **Preconditions** | Form saved. |

**Steps**

1. Settings → Share
2. Copy link
3. Open in private window

**Expected behaviour:** Link opens fill; guest rules apply.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SHR-006 — Question CSV import append vs replace

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Import can append or replace-all. |
| **Preconditions** | Form with fields; sample CSV available. |

**Steps**

1. Export questions
2. Import append → count increases
3. Import replace (confirm) → replaced

**Expected behaviour:** Append adds; replace wipes previous questions after confirm.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SHR-007 — Public dashboard link

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Optional public aggregate dashboard. |
| **Preconditions** | Share dashboard without sign-in on. |

**Steps**

1. Enable and save
2. Open dashboard URL logged out

**Expected behaviour:** Aggregates visible; individual answers/CSV still protected.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SHR-008 — Regenerate preview link

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Old preview token invalidated when regenerated. |
| **Preconditions** | Preview link exists. |

**Steps**

1. Copy old link
2. Regenerate
3. Try old and new

**Expected behaviour:** New works; old fails or revoked.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-SHR-009 — Embed / share poll

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator,Participant |
| **Explanation** | Poll embed path works when applicable. |
| **Preconditions** | Quick poll Open. |

**Steps**

1. Use share/embed snippet
2. Vote

**Expected behaviour:** Vote recorded; results display per settings.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Versions

### UAT-VER-001 — Save creates revision

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Every studio save creates a revision. |
| **Preconditions** | Versioning module enabled for Forms. |

**Steps**

1. Change a label
2. Save
3. Open Versions

**Expected behaviour:** New revision listed with timestamp.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VER-002 — Publish edition required for Open

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Participants use published edition. |
| **Preconditions** | Versioning on; unpublished changes. |

**Steps**

1. Edit draft fields
2. Do not publish
3. Fill live link
4. Publish then fill again

**Expected behaviour:** Live fill uses last published edition until new publish.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-VER-003 — Restore revision to draft

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Restore does not change live edition until publish. |
| **Preconditions** | Two revisions exist. |

**Steps**

1. Restore older revision
2. Check builder
3. Confirm live fill unchanged until publish

**Expected behaviour:** Draft matches restored snapshot; live edition unchanged.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Fill experience

### UAT-FIL-001 — Multi-page progress and page numbers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Display settings control chrome. |
| **Preconditions** | Multi-page; progress and page numbers Show. |

**Steps**

1. Open fill
2. Move between pages

**Expected behaviour:** Progress and page indicator update.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FIL-002 — File upload field

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Respondent |
| **Explanation** | Uploaded files stored and linked from answers. |
| **Preconditions** | File field on form; Open. |

**Steps**

1. Upload allowed file type
2. Submit
3. Open answer detail

**Expected behaviour:** File accessible to managers; CSV references file not binary.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FIL-003 — Grid / ranking / rating types

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Research/choice advanced types collect structured answers. |
| **Preconditions** | Form includes grid_single, ranking, rating. |

**Steps**

1. Complete each type
2. Submit
3. View answer / CSV

**Expected behaviour:** Values stored coherently; no JS console errors.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FIL-004 — Closed form rejects submit

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Respondent |
| **Explanation** | Closed status stops new responses. |
| **Preconditions** | Form Closed. |

**Steps**

1. Open fill link
2. Attempt submit

**Expected behaviour:** Message that form is closed; no new complete answer.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Participant journey

### UAT-PAR-001 — Open live share link as signed-in member

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Members reach the published survey from the share/live URL. |
| **Preconditions** | Form Open with published edition; participant has Answer permission where required. |

**Steps**

1. Sign in as participant
2. Open live share URL
3. Confirm questions load
4. Submit

**Expected behaviour:** Published edition shown (not unpublished draft-only content); submission stored against user.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-002 — Find and open form from space side menu

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | When Show in side menu is on, participants discover the form in the space. |
| **Preconditions** | Space form; Show in side menu on; Open; participant is space member. |

**Steps**

1. Enter space as participant
2. Open form from side menu
3. Fill and submit

**Expected behaviour:** Menu entry visible; fill opens; submit succeeds.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-003 — Guest cannot use draft-only unpublished form

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants must not fill unpublished working drafts via live link. |
| **Preconditions** | Form Draft or Open without required published edition as configured; guest or participant live URL. |

**Steps**

1. Open live URL while form is not properly Open/published
2. Observe message

**Expected behaviour:** Fill blocked or unavailable with clear status; no silent accept of answers against wrong edition.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-004 — Continue saved response vs start new

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | When resume is on, opening the form offers continue or start new. |
| **Preconditions** | Save & resume on; participant has in-progress saved response. |

**Steps**

1. Open form
2. Choose continue
3. Confirm restored answers
4. Re-open and choose start new if offered

**Expected behaviour:** Continue restores progress; start new begins empty (and does not mix old answers unexpectedly).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-005 — Required validation messages are clear

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants understand what to fix when submit/Next fails. |
| **Preconditions** | Page with required fields left empty; Open. |

**Steps**

1. Leave required empty
2. Click Next or Submit
3. Fix highlighted fields
4. Continue

**Expected behaviour:** Clear required messaging; focus/scroll to issues on mobile and desktop; proceeds after fix.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-006 — Conditional question appears after answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Show-if logic from the participant’s perspective. |
| **Preconditions** | Field B show-if equals answer on A; Open. |

**Steps**

1. Answer A with non-trigger value — B hidden
2. Change A to trigger — B appears
3. Answer B and submit

**Expected behaviour:** B visibility follows A immediately; hidden B not required; submit stores visible answers correctly.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-007 — Piped text shows participant’s earlier answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Labels that pipe prior answers must reflect what the participant typed/chose. |
| **Preconditions** | Later question label pipes earlier answer; Open. |

**Steps**

1. Answer source question
2. Go to piped question
3. Confirm label includes their answer

**Expected behaviour:** Piped text matches participant input (label vs code per configuration).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-008 — Page branch skips irrelevant pages

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants should land on the correct next page after a branch. |
| **Preconditions** | Page branch go-to configured; Open multi-page form. |

**Steps**

1. Choose branch answer
2. Next
3. Confirm skipped pages not shown
4. Finish

**Expected behaviour:** Correct page sequence; Back behaves sensibly; final submit stores path taken.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-009 — Already submitted message for participant

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | When multiple submissions are off, returning participants see the configured message. |
| **Preconditions** | Multiple submissions off; participant already submitted; already-submitted message configured. |

**Steps**

1. Open form again as same participant
2. Read message
3. Use button if shown

**Expected behaviour:** Already-submitted content shown instead of a blank new form; button works if configured.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-010 — Edit after submit when allowed

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Participants can reopen and change answers if Edit after submit is on. |
| **Preconditions** | Edit after submit on; prior submission exists. |

**Steps**

1. Open form
2. Change an answer
3. Save/submit again
4. Confirm updated answers in results (creator check)

**Expected behaviour:** Prior answers loaded; update accepted; results reflect change.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-011 — Second submission when multiples allowed

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants can submit again when Allow multiple submissions is on. |
| **Preconditions** | Multiple submissions on; one submission already done. |

**Steps**

1. Open form again
2. Complete a new response
3. Submit

**Expected behaviour:** New response accepted; both appear in answers list.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-012 — Closed form while participant is mid-fill

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Closing the form should stop new completion cleanly. |
| **Preconditions** | Participant has fill open; creator sets status Closed (or window ends). |

**Steps**

1. Start filling
2. Creator closes form
3. Participant tries Next/Submit

**Expected behaviour:** Submit rejected with clear closed message; no corrupt partial as completed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-013 — Invitation-only access without token

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants without a valid invite cannot fill invitation-gated forms. |
| **Preconditions** | Invitation access mode on; Open form. |

**Steps**

1. Open form URL without invite token
2. Observe
3. Open with valid invite link

**Expected behaviour:** Blocked without token; allowed with valid invite; submit works on invite path.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-014 — Panel wave 2 invite on phone

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Longitudinal participants open wave invites from mobile email. |
| **Preconditions** | Panel wave 2 open; participant has invite link/token; phone. |

**Steps**

1. Open wave invite on phone
2. Complete wave form
3. Submit

**Expected behaviour:** Token accepted; correct wave form shown; response tied to panel member/wave.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-015 — Progress and page numbers guide completion

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Participants rely on progress chrome to know how far they are. |
| **Preconditions** | Multi-page; progress and/or page numbers set to Show. |

**Steps**

1. Open fill
2. Advance pages
3. Note progress/page indicator

**Expected behaviour:** Indicator updates each page; does not show misleading 100% before last page.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-016 — Back preserves answers across pages

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants often revise earlier pages before submit. |
| **Preconditions** | Multi-page Open form. |

**Steps**

1. Answer page 1
2. Next
3. Answer page 2
4. Back
5. Confirm page 1 answers
6. Forward and submit

**Expected behaviour:** Answers on both pages retained; no silent wipe.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-017 — Anonymous submission does not expose identity UI

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | When anonymous is allowed, guests should not be forced through account UI unnecessarily. |
| **Preconditions** | Anonymous on; guest fill permitted by site; Open. |

**Steps**

1. Sign out
2. Open share link
3. Complete and submit

**Expected behaviour:** Survey completable as guest; thank-you shown; answer stored as anonymous per settings.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-018 — Incomplete leave without saving

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant,Creator |
| **Explanation** | Leaving without Save & continue later should not claim a finished response. |
| **Preconditions** | Resume optional; multi-page form; keep incomplete setting known. |

**Steps**

1. Fill partial page
2. Close tab without save/submit
3. Creator checks answers list

**Expected behaviour:** No completed response created; incomplete only appears if product intentionally keeps it.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-019 — Switch language mid-survey then finish

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Participants may change language after starting. |
| **Preconditions** | Two languages with translations; Open multi-page. |

**Steps**

1. Start in default language
2. Answer page 1
3. Switch language
4. Continue and submit

**Expected behaviour:** Prior answers kept; later chrome/questions in new language; submit succeeds.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-020 — Go-to-end skips remaining questions

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Early exit logic should end the survey from the participant’s view. |
| **Preconditions** | Go to end if configured; Open. |

**Steps**

1. Choose triggering answer
2. Next/Submit path
3. Land on end/thank-you

**Expected behaviour:** Remaining pages skipped; completion recorded per rules; no forced empty required pages.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-021 — Network/global form fill outside a space

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Global forms are opened from network share links, not only space menus. |
| **Preconditions** | Network/global Open form; participant account allowed. |

**Steps**

1. Open global live URL
2. Complete
3. Submit

**Expected behaviour:** Form loads outside space context; submission appears in global answers.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PAR-022 — Attention check as participant

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Integrity attention checks must be understandable and enforceable. |
| **Preconditions** | Attention check configured; Open. |

**Steps**

1. Reach attention item
2. Answer incorrectly
3. Retry or finish per rules
4. Answer correctly and submit

**Expected behaviour:** Incorrect handling matches integrity settings (flag/block); correct path can complete.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Mobile surveys

### UAT-MOB-001 — Portrait phone completes a multi-page survey

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Primary mobile path: narrow portrait viewport through Next/Back to submit. |
| **Preconditions** | Open multi-page survey; share/live link; phone or DevTools ~375×812. |

**Steps**

1. Open live fill link in portrait
2. Answer required fields on each page
3. Use Next/Back
4. Submit

**Expected behaviour:** All controls usable without horizontal page scroll; progress updates; submission succeeds and end screen appears.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-002 — Landscape phone fill remains usable

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Shorter viewport height must not hide Next/Submit behind the keyboard or chrome. |
| **Preconditions** | Same Open form as MOB-001; device or emulator in landscape. |

**Steps**

1. Rotate to landscape
2. Focus a text field (soft keyboard if real device)
3. Scroll to Next/Submit
4. Complete and submit

**Expected behaviour:** Primary actions reachable; answers not lost on rotate; submit completes.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-003 — Tablet-width fill layout

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Mid-size viewports should not look broken or oversized. |
| **Preconditions** | Open multi-field form; viewport ~768px wide. |

**Steps**

1. Open fill at tablet width
2. Complete one page of mixed field types
3. Submit or Next

**Expected behaviour:** Readable column width; no clipped labels/options; touch/mouse both work.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-004 — Touch targets for choices and actions

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Radios, checkboxes, Next, and Submit must be easy to tap without mis-hits. |
| **Preconditions** | Form with radio, checkbox, and at least one page action; phone portrait. |

**Steps**

1. Tap each option deliberately
2. Tap Next
3. Tap Submit

**Expected behaviour:** Selection changes reliably; accidental double-taps do not corrupt answers; buttons respond once.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-005 — Soft keyboard does not block Next or Submit

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | On-screen keyboard must not permanently hide the page footer actions. |
| **Preconditions** | Page with text/email field near bottom; real phone or accurate emulator. |

**Steps**

1. Focus last text field
2. Type an answer
3. Dismiss or scroll to find Next/Submit
4. Continue

**Expected behaviour:** Participant can reach Next/Submit without losing typed text; no unusable stuck state.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-006 — No essential horizontal scrolling

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Labels, options, and buttons stay within the viewport width at default zoom. |
| **Preconditions** | Form with long labels and a matrix or multi-column-looking field if available; ~375px width. |

**Steps**

1. Open fill at default zoom
2. Scan each field type on the page
3. Answer without pinching

**Expected behaviour:** No need to pan sideways for primary content; long text wraps; matrix scrolls inside its own area if needed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-007 — File upload from phone camera or gallery

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants on phones often attach photos from camera roll. |
| **Preconditions** | Open form with allowed image upload field; phone browser. |

**Steps**

1. Tap upload
2. Choose gallery or camera photo
3. Confirm attachment
4. Submit

**Expected behaviour:** File attaches and is listed; submit stores the file; clear error if type/size disallowed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-008 — Matrix or grid on a narrow screen

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Wide field types must remain answerable on phones. |
| **Preconditions** | Form includes matrix/table or grid; Open; phone portrait. |

**Steps**

1. Open the matrix page
2. Select cells/options for each row
3. Next or Submit

**Expected behaviour:** All rows reachable; selections stick; no clipped radio that cannot be tapped.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-009 — Ranking or drag-order on touch

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Touch drag (or mobile-friendly control) must reorder items. |
| **Preconditions** | Form with ranking field; phone; Open. |

**Steps**

1. Reorder items with finger
2. Confirm new order visible
3. Submit and check stored order

**Expected behaviour:** Order changes and persists after submit; works without a mouse.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-010 — Thermometer / VAS on touch

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Slider-like scales must be adjustable with a finger. |
| **Preconditions** | Form with thermometer/VAS; phone. |

**Steps**

1. Drag or tap to set a value
2. Adjust again
3. Submit

**Expected behaviour:** Value updates smoothly enough to choose; stored answer matches final position.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-011 — Date picker on mobile browsers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Native or widget date entry must work on iOS/Android browsers. |
| **Preconditions** | Date or datetime field; phone browser. |

**Steps**

1. Open date control
2. Pick a date
3. Submit

**Expected behaviour:** Date accepted and shown; invalid/empty required date blocks with clear message.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-012 — Language switcher on mobile fill

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants change language from the fill chrome on a small screen. |
| **Preconditions** | Second language enabled with translations; Open form; phone. |

**Steps**

1. Open fill
2. Switch language
3. Confirm labels/options update
4. Answer and submit

**Expected behaviour:** Switcher tappable; content switches; RTL applies when applicable; submit succeeds.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-013 — Save and resume code on mobile

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Resume code must be copyable or emailable on a phone. |
| **Preconditions** | Save & resume on; multi-page Open form; phone. |

**Steps**

1. Fill page 1
2. Save & continue later
3. Copy or note code on phone
4. Leave and resume with code
5. Finish submit

**Expected behaviour:** Code visible and usable; answers restored; completion works.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-014 — Guest share link on mobile

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Anonymous/guest participants often arrive from SMS or email on a phone. |
| **Preconditions** | Anonymous allowed (or guest permitted); Open; share URL; signed-out phone browser. |

**Steps**

1. Open share URL while signed out
2. Complete survey
3. Submit

**Expected behaviour:** Fill loads without forcing unnecessary login; submission recorded; thank-you or redirect shown.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-015 — Thank-you and redirect on mobile

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | End-of-survey paths must work in mobile browsers (including in-app browsers). |
| **Preconditions** | One form in message mode with button; another or setting for redirect mode; phone. |

**Steps**

1. Submit message-mode form
2. Read thank-you and tap button
3. Submit redirect-mode form

**Expected behaviour:** Thank-you readable; button works; redirect lands on configured URL without desktop-only breakage.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-016 — Orientation change keeps answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Rotating the device mid-page must not wipe in-progress answers. |
| **Preconditions** | Multi-field page; phone. |

**Steps**

1. Answer several fields
2. Rotate portrait↔landscape
3. Confirm answers still present
4. Submit

**Expected behaviour:** Answers preserved across orientation change; no forced restart.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-017 — Map question usable on mobile

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | If Mapping is enabled, pin/search must work with touch. |
| **Preconditions** | Thiscovery Mapping installed/enabled; Open form with map field; phone. |

**Steps**

1. Open map question
2. Pan/zoom and place or search a location
3. Submit

**Expected behaviour:** Map interacts with touch; location saved; clear error if required and empty. Skip if Mapping off.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-018 — CAPTCHA or Turnstile on mobile submit

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Bot checks must be completable on a phone screen. |
| **Preconditions** | CAPTCHA/Turnstile enabled on form; phone. |

**Steps**

1. Fill required answers
2. Attempt submit before challenge
3. Complete challenge
4. Submit

**Expected behaviour:** Blocked until challenge done; after success, submission accepted.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-019 — Hide HumHub header focused mobile fill

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Headerless fill is common for email/SMS and kiosk-like mobile tasks. |
| **Preconditions** | Hide HumHub header on; Open form; phone. |

**Steps**

1. Open live fill link
2. Confirm site header/space menu absent
3. Complete and submit

**Expected behaviour:** Focused fill chrome only; survey fully usable; submit and end screen work.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-MOB-020 — Poll embed or share on mobile

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Quick polls embedded or shared must be tappable on phones. |
| **Preconditions** | Open poll; embed or share path available; phone. |

**Steps**

1. Open poll on mobile
2. Select option
3. Submit/vote
4. View results if enabled

**Expected behaviour:** Vote registers once (unless multiple allowed); results readable on narrow screen.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Usability

### UAT-UX-001 — Mobile fill layout

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Fill usable on narrow viewport. |
| **Preconditions** | Open multi-field form. |

**Steps**

1. Open fill at ~375px width
2. Complete submit

**Expected behaviour:** No horizontal clip of primary controls; submit works.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-UX-002 — Keyboard reachable controls

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Main controls reachable without mouse. |
| **Preconditions** | Simple form. |

**Steps**

1. Tab through fields
2. Submit with keyboard

**Expected behaviour:** Focus order sensible; submit activates.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Field types

### UAT-FLD-001 — File upload field

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Participants can attach files. |
| **Preconditions** | Form with file field; Open. |

**Steps**

1. Upload allowed file type
2. Submit
3. Open answer

**Expected behaviour:** File attached and downloadable by manager.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-002 — Reject disallowed file type

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant |
| **Explanation** | Validation blocks wrong MIME/extension. |
| **Preconditions** | File field with type limits. |

**Steps**

1. Upload disallowed type

**Expected behaviour:** Error shown; submit blocked until fixed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-003 — Thermometer / VAS

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Vertical scale stores integer value. |
| **Preconditions** | Thermometer field configured. |

**Steps**

1. Set value via UI
2. Submit
3. Check answer

**Expected behaviour:** Stored value matches selection.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-004 — Date and datetime fields

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant,Creator |
| **Explanation** | Date values format correctly in export. |
| **Preconditions** | Date field on form. |

**Steps**

1. Pick date
2. Submit
3. Export

**Expected behaviour:** Date readable and correct timezone/format.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-005 — Rich text / HTML content blocks

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Content blocks show formatting, not as questions. |
| **Preconditions** | HTML/rich content field. |

**Steps**

1. Preview fill

**Expected behaviour:** Content renders; not counted as answerable required Q.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-006 — Required field validation

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Required empty fields block Next/Submit. |
| **Preconditions** | Required text field. |

**Steps**

1. Leave empty
2. Next/Submit

**Expected behaviour:** Validation message; cannot proceed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-007 — Checkbox multi-select

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Multiple options stored. |
| **Preconditions** | Checkbox field. |

**Steps**

1. Select 2+ options
2. Submit
3. Detail/export

**Expected behaviour:** All selected options present.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FLD-008 — Matrix / table field

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Participant,Creator |
| **Explanation** | Row-column answers store correctly. |
| **Preconditions** | Matrix field configured. |

**Steps**

1. Answer each row
2. Submit

**Expected behaviour:** Each cell/row value in answer detail.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Pages & navigation

### UAT-PG-001 — Progress bar visibility

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Show progress setting respected. |
| **Preconditions** | Multi-page; show_progress on/off. |

**Steps**

1. Toggle setting
2. Preview each mode

**Expected behaviour:** Bar visible only when enabled.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PG-002 — Page indicator

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Page X of Y when enabled. |
| **Preconditions** | show_page_indicator on. |

**Steps**

1. Preview multi-page

**Expected behaviour:** Indicator matches current page.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PG-003 — Back button preserves answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Going back keeps entered values. |
| **Preconditions** | Multi-page form. |

**Steps**

1. Fill page 1
2. Next
3. Back

**Expected behaviour:** Page 1 values still present.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PG-004 — Skip page via logic

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant,Creator |
| **Explanation** | Branching can skip pages. |
| **Preconditions** | Page logic configured. |

**Steps**

1. Choose branch answer
2. Next

**Expected behaviour:** Skipped page not shown; totals/progress sensible.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Save & resume

### UAT-RES-001 — Save progress mid-survey

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Participants can leave and continue. |
| **Preconditions** | Resume enabled; multi-page form. |

**Steps**

1. Fill page 1
2. Save progress
3. Leave
4. Resume

**Expected behaviour:** Answers restored; continue from correct page.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RES-002 — Resume via email/code

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Participant |
| **Explanation** | Guests can resume with code/email when enabled. |
| **Preconditions** | Anonymous + resume enabled. |

**Steps**

1. Save as guest
2. Use resume link/code

**Expected behaviour:** Draft loads; can complete submit.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RES-003 — Resume disabled blocks save

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator,Participant |
| **Explanation** | When off, save UI hidden or rejected. |
| **Preconditions** | Resume disabled. |

**Steps**

1. Open fill
2. Look for save/resume

**Expected behaviour:** No save-progress path (or clear message).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Answers & dashboard

### UAT-ANS-001 — Answers list and detail

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Managers with view permission see answers. |
| **Preconditions** | At least one complete non-test answer. |

**Steps**

1. Open Answers
2. Open detail

**Expected behaviour:** Fields shown; test answers excluded from default counts.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-ANS-002 — Dashboard totals

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Dashboard shows completes (and incompletes if kept). |
| **Preconditions** | Several completes; one incomplete if keep-incomplete on. |

**Steps**

1. Open Dashboard

**Expected behaviour:** Counts match; charts render for poll/waves when applicable.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-ANS-003 — CSV export answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Answers CSV uses Settings → Export (columns and Scrub PII). Export headers (label vs variable) stay on the Answers page. |
| **Preconditions** | Completed live answers exist. |

**Steps**

1. Answers → Export CSV
2. Open in a spreadsheet
3. If Scrub PII is on, confirm the button reads Export CSV (PII scrubbed) and the filename ends with -scrubbed.csv

**Expected behaviour:** Live rows only (preview excluded). Quality/integrity columns are always present (often blank if integrity is off). File questions export the filename, not the file. Omitted columns match Settings → Export. Header mode is chosen only on Answers.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-ANS-004 — Keep incomplete responses

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | In-progress answers are stored when Keep incomplete is on, and they appear in the normal answers CSV. |
| **Preconditions** | Keep incomplete on; abandon mid-form. |

**Steps**

1. Start fill, leave
2. Check dashboard in-progress count
3. Answers → Export CSV

**Expected behaviour:** In-progress appears on the dashboard and in the CSV Status column as In progress. There is no separate incompletes export. Export including excluded is for integrity exclusions only.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Survey responses

### UAT-RSP-001 — Open answer detail

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Managers can inspect a single submission. |
| **Preconditions** | ≥1 complete answer. |

**Steps**

1. Answers list
2. Open an answer

**Expected behaviour:** All field values shown; metadata (time, user/guest) visible.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-002 — Edit a submitted answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Corrections are possible where permitted. |
| **Preconditions** | Manage permission; complete answer. |

**Steps**

1. Edit answer
2. Change a value
3. Save

**Expected behaviour:** Updated value persists in detail and export.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-003 — Delete an answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Deletion removes submission from results. |
| **Preconditions** | Manage permission. |

**Steps**

1. Delete answer
2. Confirm
3. Refresh list/export

**Expected behaviour:** Answer gone from list, dashboard, export.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-004 — Distinguish test vs live answers

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Preview/test submissions must not pollute live stats if marked test. |
| **Preconditions** | Submit once via Preview and once live. |

**Steps**

1. Compare Answers / dashboard filters

**Expected behaviour:** Test answers labelled or excluded from live metrics per product rules.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-005 — In-progress answers listed

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Save-and-resume drafts are visible to managers. |
| **Preconditions** | Resume enabled; incomplete draft exists. |

**Steps**

1. Open Answers
2. Find in-progress

**Expected behaviour:** Draft visible with status In progress.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-006 — Answer notification

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Creators can be notified on submit. |
| **Preconditions** | Notify-on-submit enabled. |

**Steps**

1. Submit as participant
2. Check creator notifications/email

**Expected behaviour:** Notification received for live submit.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-RSP-007 — Dashboard reflects new answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Dashboard aggregates update after submit. |
| **Preconditions** | Open form with dashboard. |

**Steps**

1. Note counts
2. Submit new answer
3. Refresh dashboard

**Expected behaviour:** Counts/charts include new response.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Permissions

### UAT-PER-001 — Space Create vs Manage vs Answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Admin |
| **Explanation** | Permissions separate create, manage, answer, view answers. |
| **Preconditions** | Two test users with different space perms. |

**Steps**

1. User A Create only — can create, limited manage
2. User B Answer only — can fill not edit studio
3. User C View answers — answers/dashboard only

**Expected behaviour:** Each role matches permission matrix.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PER-002 — Global vs space forms

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Admin |
| **Explanation** | Global forms use network permissions. |
| **Preconditions** | Global forms enabled. |

**Steps**

1. Create global form
2. Fill as user with global answer perm
3. User without perm denied

**Expected behaviour:** Access follows global permissions, not space.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-PER-003 — Folder does not grant access

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Admin |
| **Explanation** | Folders organise; ACL is separate. |
| **Preconditions** | Form in folder; user without view answers. |

**Steps**

1. Place form in folder
2. User without permission tries Answers

**Expected behaviour:** Still denied despite folder membership.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Studio UI

### UAT-UI-001 — Two tabs Form builder and Settings

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Settings left rail holds all config sections. |
| **Preconditions** | Any form studio. |

**Steps**

1. Confirm only Form builder and Settings top tabs
2. Open Settings → End of survey, Response integrity, Share, Export, Versions

**Expected behaviour:** All reachable from Settings rail; Export is in the Publish group next to Share; builder still has the field palette.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-UI-002 — Settings ? guides work

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Guidance toggles open help text. |
| **Preconditions** | Settings → Basics and Participant display. |

**Steps**

1. Click ? on several fields
2. Click again to close

**Expected behaviour:** Panel opens/closes; no double-toggle flash.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-UI-003 — Settings save does not wipe fields

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Saving from Settings must keep all builder fields. |
| **Preconditions** | Form with many fields (e.g. 20+). |

**Steps**

1. Note field count
2. Change a setting only
3. Save
4. Return to builder

**Expected behaviour:** Field count unchanged; content intact.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-UI-004 — Export in Settings Publish group

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Answers CSV column choice lives under Settings → Export, in the Publish group next to Share. |
| **Preconditions** | Any saved form studio. |

**Steps**

1. Open Settings
2. In Publish group open Export (next to Share)
3. Confirm Share still present
4. Untick one metadata column, leave Scrub PII off, Save form
5. Reopen Export

**Expected behaviour:** Export pane opens from the rail. Saved ticks persist. The pane hint says this applies to Answers, Dashboard, and Response integrity, and that header labels are still chosen on Answers.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## CSS & themes

### UAT-CSS-001 — Apply a named theme to a form

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Themes set shared colours and typography. |
| **Preconditions** | At least one theme exists in module settings. |

**Steps**

1. Open Settings → Quality & style / CSS
2. Choose a theme
3. Save
4. Open Preview

**Expected behaviour:** Fill page uses theme colours; no console errors.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-CSS-002 — Custom CSS overrides

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Per-form CSS can override theme rules. |
| **Preconditions** | Form with a theme applied. |

**Steps**

1. Add custom CSS that changes a visible element (e.g. question label colour)
2. Save
3. Preview

**Expected behaviour:** Custom rule wins over theme for that selector.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-CSS-003 — Style colour pickers

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Studio style fields map to CSS variables / selectors. |
| **Preconditions** | Form open in Settings → style. |

**Steps**

1. Change primary / help text colour
2. Save
3. Preview

**Expected behaviour:** Colours match on fill page.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-CSS-004 — Update shared theme

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Admin,Creator |
| **Explanation** | Editing a theme updates forms using it. |
| **Preconditions** | Two forms use the same theme. |

**Steps**

1. Edit theme in Administration → Forms settings
2. Change a colour
3. Preview both forms

**Expected behaviour:** Both forms reflect the new theme colour (unless form override).

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-CSS-005 — Clear custom CSS

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Removing CSS restores theme/default look. |
| **Preconditions** | Form has custom CSS. |

**Steps**

1. Clear CSS box
2. Save
3. Preview

**Expected behaviour:** Custom styles gone; theme/default remains.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Context

### UAT-CTX-001 — Space form permissions

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Admin,Participant |
| **Explanation** | Space Create/Manage/Answer permissions enforce access. |
| **Preconditions** | Space with restricted permissions. |

**Steps**

1. User without Create tries New form
2. User without Answer opens fill

**Expected behaviour:** Forbidden or hidden as designed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-CTX-002 — Network/global form

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator,Participant |
| **Explanation** | Global forms managed under Administration / network Forms. |
| **Preconditions** | CreateGlobalForm permission. |

**Steps**

1. Create global form
2. Open fill URL
3. Submit

**Expected behaviour:** Works without space membership when allowed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Email

### UAT-EML-001 — Email template CRUD

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Templates reusable for panel invites. |
| **Preconditions** | Manage panels/email permission. |

**Steps**

1. Create template
2. Edit
3. Delete

**Expected behaviour:** CRUD works; placeholders documented.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EML-002 — Panel invite email

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator,Participant |
| **Explanation** | Invite sends with form link. |
| **Preconditions** | Panel with member email; wave open. |

**Steps**

1. Send invite
2. Open link from email body

**Expected behaviour:** Land on correct form; token accepted.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Export

### UAT-EXP-001 — Export answers CSV

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Default answers CSV includes metadata and every question until Settings → Export excludes columns. |
| **Preconditions** | Form with ≥2 completed live answers; Scrub PII off; no columns excluded. |

**Steps**

1. Answers → Export CSV
2. Open file

**Expected behaviour:** One row per live answer. Columns include Answer ID, Status, User, timestamps, quality/integrity fields, and each question (plus Comment columns where justification is on). Filename is form-{id}-{timestamp}.csv without -scrubbed.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-002 — Export answers respects filters

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Answers Export CSV applies integrity and minimum-score filters from the list. Status and search are not passed to the download. The file includes every matching row, not only the current page. |
| **Preconditions** | Several answers with different quality statuses or scores. |

**Steps**

1. On Answers, set Integrity or Score n+
2. Export CSV
3. Optionally filter Status or Search and export again

**Expected behaviour:** Integrity/score filters change the row count to match that filtered set (all pages). Status and search do not change the download. Pagination does not limit the file.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-003 — Export questions JSON

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Question JSON is a structure export from Settings → Share, not Settings → Export. |
| **Preconditions** | Form with pages and option fields. |

**Steps**

1. Settings → Share → Export JSON (questions)
2. Inspect

**Expected behaviour:** Types, options, pages, logic present. Answers column ticks and Scrub PII do not change this file.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-004 — Export questions CSV

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Question CSV is a structure export from Settings → Share, not Settings → Export. |
| **Preconditions** | Form with mixed field types. |

**Steps**

1. Settings → Share → Export CSV (questions)

**Expected behaviour:** Columns match import docs (including pii). Special characters escaped. Settings → Export does not change this file.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-005 — Export translations CSV

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Translation CSV comes from Settings → Translations, not Settings → Export. |
| **Preconditions** | Multi-language form. |

**Steps**

1. Settings → Translations → Export CSV

**Expected behaviour:** Keys for labels/help present per language. Settings → Export (answers columns / PII) does not change this file.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-006 — Exclude answers-CSV columns

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Unticked columns are omitted from every answers CSV. New questions stay included until excluded. |
| **Preconditions** | Form with metadata plus ≥2 questions that have live answers. Scrub PII off. |

**Steps**

1. Settings → Export
2. Untick User and one question
3. Save
4. Answers → Export CSV
5. Add a new question, save, submit one answer, export again

**Expected behaviour:** First CSV omits User and the excluded question. New question appears in the second CSV without being excluded automatically. Justification Comment columns can be unticked separately.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-007 — Scrub PII drops identity columns

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | When Scrub PII is on, identity and PII-tagged columns are omitted even if left ticked. |
| **Preconditions** | Form with an Email question, respondent-meta IP, a non-PII text question, and live answers. Leave identity columns ticked. |

**Steps**

1. Settings → Export → turn Scrub PII on
2. Save
3. Answers → Export CSV
4. Check filename, button label, and Export settings link

**Expected behaviour:** CSV omits User, Email, and IP. Non-PII text remains. Button reads Export CSV (PII scrubbed) and links to Export settings. Filename ends with -scrubbed.csv. User-agent, panel name/email, and PII-tagged fields (including file names) are also omitted if present. Email/IP still drop if you unticked Contains personal data.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-008 — Scrub PII redacts remaining free text

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Remaining cells have emails, phones, and IPs replaced; names, postcodes, and NHS numbers are left. Stored answers are unchanged. |
| **Preconditions** | Scrub PII on. A text question with Contains personal data off. One answer whose text includes jane.doe@example.org, 07700 900123, 192.168.0.1, Jane Smith, SW1A 1AA, and NHS 943 476 5919. |

**Steps**

1. Answers → Export CSV
2. Open that text cell
3. Open the same answer in Answers detail

**Expected behaviour:** CSV shows [redacted] in place of the email, UK phone, and IPv4. Jane Smith, SW1A 1AA, and 943 476 5919 remain. Answers detail still shows the original stored text.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-009 — Scrub PII locks identity columns in settings

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | While scrubbing is on, identity/PII columns show locked off with a note that they will be omitted. |
| **Preconditions** | Settings → Export open; User and other identity columns were included. |

**Steps**

1. Turn Scrub PII on
2. Observe User/email/PII ticks
3. Turn Scrub PII off without saving

**Expected behaviour:** Locked columns appear off and disabled, tagged PII, with the omit note. Turning scrub off restores the previous include ticks. Select all does not re-enable locked ticks while scrub is on.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-010 — Dashboard and Integrity use the same CSV settings

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | There is no per-download column picker. Answers, Dashboard, and the Response integrity dashboard share Settings → Export. |
| **Preconditions** | A column excluded and Scrub PII on; live answers exist. |

**Steps**

1. Answers → Export CSV
2. Dashboard → Export CSV
3. Dashboard → Response integrity → Export CSV
4. On Answers, Export headers → Variable names, then download again

**Expected behaviour:** All three default downloads omit the same columns and use -scrubbed.csv. Dashboard and Integrity buttons also say Export CSV (PII scrubbed). Header mode exists only on Answers; Dashboard and Integrity always use Participant labels.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-EXP-011 — Default answers CSV until ticks or Scrub PII change

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Existing forms keep a full answers CSV until a creator changes column ticks or Scrub PII. |
| **Preconditions** | Scrub PII off and no columns excluded (the default). |

**Steps**

1. Answers → Export CSV without changing Export
2. Open Settings → Export and confirm all columns ticked and Scrub PII off

**Expected behaviour:** Full column set; filename without -scrubbed. Saving from Builder or other Settings panes keeps that default unless you change ticks. Question JSON/CSV on Share and translation exports are unchanged.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Import

### UAT-IMP-001 — Import questions CSV (append)

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Append adds questions without deleting existing ones. |
| **Preconditions** | Form with ≥1 existing field; valid questions CSV. |

**Steps**

1. Studio → Import
2. Upload CSV
3. Append
4. Save

**Expected behaviour:** Existing fields remain; new fields added in order.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-IMP-002 — Import questions CSV (replace)

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Replace requires confirmation and wipes prior fields. |
| **Preconditions** | Form with existing fields. |

**Steps**

1. Import → Replace
2. Confirm
3. Save

**Expected behaviour:** Only imported fields remain; count matches CSV.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-IMP-003 — Import questions JSON

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | JSON export can be re-imported. |
| **Preconditions** | Exported questions JSON available. |

**Steps**

1. Import JSON
2. Save

**Expected behaviour:** Fields and options restored.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-IMP-004 — Import translations CSV

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Translation import updates language strings. |
| **Preconditions** | Form with ≥2 languages enabled. |

**Steps**

1. Export translations
2. Edit a label
3. Re-import
4. Switch language on fill

**Expected behaviour:** Updated translation appears.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-IMP-005 — Invalid import shows errors

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Bad rows must not silently wipe the form. |
| **Preconditions** | CSV with unknown type or missing label. |

**Steps**

1. Import invalid file
2. Observe messages

**Expected behaviour:** Clear error; existing fields unchanged if import aborted.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-IMP-006 — Download sample questions file

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Sample helps authors format imports. |
| **Preconditions** | Creator access to studio. |

**Steps**

1. Download sample CSV/JSON
2. Open in spreadsheet

**Expected behaviour:** Sample has expected columns (including pii) and example rows.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Functions & actions

### UAT-FN-001 — Field action runs on answer

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Actions can set variables or trigger logic when a field changes. |
| **Preconditions** | Form with a field action configured. |

**Steps**

1. Configure action on a radio field
2. Preview
3. Choose the triggering option

**Expected behaviour:** Action runs (variable set / UI change) without full page error.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FN-002 — Page action on Next

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Page break actions run when leaving a page. |
| **Preconditions** | Multi-page form with page action. |

**Steps**

1. Fill page 1
2. Click Next

**Expected behaviour:** Action runs; next page loads as expected.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FN-003 — Submit action

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Submit actions run after successful submit. |
| **Preconditions** | Form with submit action (e.g. set var / message). |

**Steps**

1. Complete and submit
2. Observe thank-you / redirect

**Expected behaviour:** Submit action completed; submission saved.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FN-004 — Action sets a variable

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Variables from actions are available to later piping/logic. |
| **Preconditions** | Field action sets a variable. |

**Steps**

1. Trigger action
2. Open a later page that pipes the variable

**Expected behaviour:** Piped value shows the action result.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-FN-005 — Invalid action config fails safely

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Bad action config must not break fill. |
| **Preconditions** | Ability to enter incomplete action JSON/config. |

**Steps**

1. Save incomplete action
2. Preview and fill

**Expected behaviour:** Error handled; fill still usable or clear validation on save.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Organisation

### UAT-ORG-001 — Folders for forms

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Forms can be grouped in folders. |
| **Preconditions** | Create permission. |

**Steps**

1. Create folder
2. Move form into folder
3. Browse

**Expected behaviour:** Form appears under folder; still editable.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-ORG-002 — Question library insert

| | |
| --- | --- |
| **Priority** | Should |
| **Roles** | Creator |
| **Explanation** | Library items insert into builder. |
| **Preconditions** | Saved library question exists. |

**Steps**

1. Insert from library
2. Save

**Expected behaviour:** Field appears on canvas with expected type.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

### UAT-ORG-003 — Save form as template

| | |
| --- | --- |
| **Priority** | Must |
| **Roles** | Creator |
| **Explanation** | Template reusable for new forms. |
| **Preconditions** | Completed form structure. |

**Steps**

1. Save as template
2. New form from template

**Expected behaviour:** New form gets fields; no answers.

**Result:** ☐ Pass &nbsp; ☐ Fail &nbsp; ☐ Blocked &nbsp; &nbsp; **Tester:** ________ &nbsp; **Date:** ________

**Notes:**

---

## Sign-off

| | |
| --- | --- |
| UAT round | ________ |
| Build / module version | ________ |
| Environment URL | ________ |
| Devices used (desktop / iOS / Android) | ________ |
| Must scenarios passed | ____ / ____ |
| Participant + mobile Must passed | ____ / ____ |
| Open defects | ________ |
| Signed off by | ________ |
| Date | ________ |
