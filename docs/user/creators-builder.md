# Builder and questions

**Form builder** is where you add questions, split the form into pages, and attach logic or actions. Changes are not live until you **Save form**. The field list on the left and the canvas on the right scroll separately. There is no limit on how many questions a form can contain.

Everything else (status, share links, integrity, CSS, versions, panels) lives under **Settings** — see [Form settings](creators-settings.md).

## Adding questions

Use **Add fields** (or the equivalent control on the builder) and choose a type. Drag to reorder. Open a question to edit label, help text, required, options, and logic. Removing a question asks you to confirm, so a click does not delete it by mistake. A published question is hidden from new responses; stored answers keep that question.

Each question can also have:

- **Internal label** — name shown in the studio only
- **Variable name** — stable id for piping, logic, and CSV export (must be unique; auto-fills from the label)

### Question types

| Type | Use for |
| --- | --- |
| Text | Short free text |
| Textarea | Longer free text |
| Number | Numeric values. Optional min and max reject e.g. negatives. |
| Email | An email address |
| Date | A calendar date |
| Dropdown | One choice from a list |
| Radio | One choice, all options visible |
| Checkbox | One or more choices |
| Rating scale | Stars, numbers, or a **vertical thermometer** (0–100) |
| Ranking (drag & drop) | Put items in order |
| File upload | Attach a file |
| Page break | Start a new page (title and page key) |
| Question group | A nest of questions shown or hidden together by Logic |
| Rich text section | Instructions or content; not an answer |
| HTML / custom block | Custom markup when you need more than rich text |
| Grid (single) | Matrix: one answer per row |
| Grid (multi) | Matrix: several answers per row |
| Best–worst | Pick best and worst from a set |
| MaxDiff | MaxDiff tasks |
| Drill-down | Nested choices (for example country then region) |
| Image area | Click regions on an image |
| Map | Draw a pin, line, or area as the answer (needs Thiscovery Mapping). Set starting view, drawing types, and basemap style on the question |

**Respondent metadata** (Add fields): drop each item you need — IP address, browser, operating system, device, screen size, browser language, time zone, or user agent. Each is a hidden field and becomes its own column in answers and CSV export. Drop only the ones you will analyse.

**Hidden from respondents:** tick this on any question to store an internal variable. People filling the form never see it. Set a **Stored value** if you want a fixed code on every response. Logic, piping, and export still use the stored value.

**Contains personal data (PII):** tick this on an answer-collecting question so **Settings → Export → Scrub PII** will omit that column from the answers CSV. Email questions, respondent IP, and panel name/email fields are tagged by default. You can tag any other question (for example a free-text name). This does not change what is stored, only what the CSV includes when scrubbing is on.

**Attention check:** tick this on an instructed-response question (for example “Please select Agree”) and type the expected answer. Pass and fail are stored on the response. They add to the quality score; they do not auto-reject. Turn on **Attention checks** under **Settings → Response integrity**.

**Choices:** in the studio, each option has an optional **Internal code** and a required **Participant label**. Respondents only see the label. Answers, logic, and CSV export use the code when you set one. If you set a code on any choice, every choice on that question needs a code. In CSV import you can still write `code | Label` on one line.

**Other:** on a dropdown, a radio, or a checkbox, name one choice `Other`. That can be the participant label or the internal code. The fill page then opens a text box when that choice is selected. The box is not shown in the studio; it appears while someone is answering.

The choice counts as Other when the label or the code is exactly `Other`, or a phrase such as `Other (please specify)`, `Other, please describe`, or `Other - please state`. `Something else` does not open the box.

Under the choices, for dropdown, radio, and checkbox:

- **Ask for extra text when Other is selected** is on by default. Untick it when a later question already collects that detail. Other can still be selected, and no box opens.
- **Extra text is required** is on by default, and only while the box is on. Next and Submit wait until the person types something. Untick it to let them select Other and continue with the box empty.

A typed answer is stored as the choice plus the text, for example `Other: my own answer`. If the extra text is not required and the box is left empty, the stored answer is the choice on its own.

Ranking, grids, MaxDiff, and best-worst do not open this box.

**Checkboxes:** set **Maximum selections** (for example 3) and an **Exclusive option** such as `None of these`. Exclusive choices clear the others when ticked.

**Page breaks** split the fill experience. Give each page a clear title. The **page key** is used by skip logic and “go to page” actions — keep keys stable once people have started filling. Logic or branch rules on a page break run when the person clicks **Next** on the page before that break.

Show or hide a later page with **Go to page if**. That still shows **Next** so the respondent can open that page. **Go to end if** is the action that replaces Next with **Finish**.

A later page with nothing visible is skipped, so respondents are not shown a blank page after the last page that still has something to show. Questions on pages they never visit are not required and do not block Submit.

If one branch should see a closing page (for example a thank-you for people who are not eligible) and the other branch should not, put **Show if** on that closing page’s content so it only appears for that branch. The other branch then finishes on the last page that still has questions. You can also put **Go to end if** on the last page break of the branch that should skip the closing page.

If a page break already sends “No” to another page, do **not** also put **Show if … equals No** on the questions on the page that “Yes” should open. Those questions would stay hidden when the answer is Yes, and that page would look empty. Leave Logic off those questions, or use **Show if … equals Yes**.

**Rich text** and **HTML** blocks are for explanation, consent, or licensed wording. They are not stored as answers.

## Required questions

Tick **Required** when the person must answer before they can continue or submit. Hidden questions, and questions on pages the person never visits, are not required.

## Options and grids

For choice questions, add rows under **Choices** (internal code + participant label). Grids need row labels and column labels. Ranking and MaxDiff need a complete set of items.

**Randomise options** gives each response its own order. There is no switch that shows every respondent the same shuffled order. Other and exclusive options stay at the end. The order stored is the order first shown.

On a **grid**, you can tick **On mobile, show each row as a stacked list of options**. When that is off, mobile keeps the horizontal scroll table.

On a **checkbox** question you can set a **minimum** number of selections, or tick **Require every option to be selected**. Respondents cannot continue or submit until that rule is met. An exclusive option such as “None of these” still counts as a complete answer on its own.

## Logic, skip, and piping

You can show, hide, skip, or jump based on earlier answers.

To show several questions only when an earlier answer matches, add a **Question group**, put those questions inside it, and click **Logic** on the group (for example **Show this group if** the screening question equals `Yes`). You do not need the same rule on every question in the group. A page break cannot sit inside a group.

Typical operators include equals, does not equal, contains, and similar comparisons. Combine conditions with **AND** or **OR**.

**Build visually**, next to the formula box, opens a window for the same rule. Pick each question, choose equals, is one of, or a number comparison, and join the rows with **All of these** (`and`) or **Any of these** (`or`). **Add group** is a bracket. **Use this formula** writes the text into the box. You can edit that text by hand, or open the window again and it loads what is in the box. The same button is on show/hide rules, calculated questions, answer checks, and page-break branches. Sums, dates, and other functions stay in the box until you choose to replace them. See [Formulas](creators-formulas.md).

Actions on a question or page often include:

- Skip this question or page
- Go to a named page
- Go to the end (thank you). Screen-out pages show **Finish** rather than **Submit**.

**Answer piping** (carry-forward) inserts a previous answer into later labels or text using placeholders such as `{{answer:Question label}}`. Match the question label carefully. Preview after you rename a question — piping uses the label you configured.

### Placeholders you can use

| Placeholder | Meaning |
| --- | --- |
| `{{user.displayname}}` | Signed-in display name |
| `{{user.firstname}}` / `{{user.lastname}}` | Profile first / last name |
| `{{user.email}}` | Account email |
| `{{form.title}}` | Form title |
| `{{answer:Question label}}` | Answer to that question (use the label exactly, or the field id) |
| `{{answer:Question label:label}}` | Same answer shown as the option label when the choice has an internal code |
| `{{field:Question label}}` | Same idea as answer-as-label |
| `{{var:name}}` | A variable stored on this response (see Actions below) |

Guests have empty `{{user.*}}` values. Unanswered questions and unset variables insert nothing.

### Worked examples — piping

Rich text on page 1:

```text
Hello {{user.firstname}}, thank you for starting {{form.title}}.
```

Signed in as Alex → `Hello Alex, thank you for starting Feedback survey.`

Later question label after “Child's first name”:

```text
You told us your child is called {{answer:Child's first name}}.
```

If they typed `Maya` → `You told us your child is called Maya.`

Keep logic simple. Deep trees of skips are hard to test. Prefer a few clear branches over many overlapping rules.

## Answer rules

Each question can carry rules that are checked on the page and again by the server when the form is submitted:

- **Text and long text:** shortest and longest answer in characters, and an optional **answer pattern** (a regular expression for the whole answer, for example `[A-Z]{2}[0-9]{4}` for a study code) with your own message. Even without a limit, a text answer is capped at 2,000 characters and a long-text answer at 16,000.
- **Date:** the answer must be a real date. **Earliest date** and **latest date** take `YYYY-MM-DD` or `today` (in the form's time zone).
- **Answer check (any question):** a formula that must be true once the question is answered, with your own message. Use it to compare answers, for example `[end_date] >= [start_date]`, or `sum([home], [work], [other]) = 100` on the last of a set of percentages.

A rule that cannot work (a broken pattern, a shortest length above the longest, an impossible date, a check that is not a valid formula) is refused when you save.

## Actions (field, page, and submit)

Actions run **standard functions** in order. They are not buttons the respondent taps on the page (except that reaching the end of a page or submitting can trigger them).

| Function | What it does |
| --- | --- |
| Send email | Sends a chosen **email template** |
| Set variable | Stores a name and value on this response |
| Go to page | Jumps to a page key |
| Go to end | Finishes the form |
| Custom function | Runs a named formula from **Settings → Actions and functions** |

You can add several actions on one field, page, or on submit. They run in the order listed.

Each action can have an **Only if** condition, written as a formula (for example `[q5] = "yes"`). The action runs only when the condition is true; leave it empty to always run. A go-to action with a condition only changes the route when the condition holds. A condition that is not a valid formula is refused when you save.

**Send email** on a question runs when the respondent leaves the page (clicks Next), not while they are still answering, so changing an answer before moving on never sends a second, wrong email. For someone who is not signed in, those emails wait until the response is submitted, and are sent then if their conditions still hold. On submit, emails run once the response is saved.

**Set variable** and **custom function** values can use `{{answer:…}}` and later emails can use `{{var:name}}`. Define reusable formulas on Settings (see [Form settings](creators-settings.md)).

Use **Send email** for “email me a copy” or “notify the team when this page is completed” instead of putting a mail button on the form.

### Worked examples — variables and functions

**Set a fixed study code on submit**

| Function | Name | Value |
| --- | --- | --- |
| Set variable | `siteCode` | `DEMO-01` |

Email or thank-you text: `Study code: {{var:siteCode}}` → `Study code: DEMO-01`

**Copy an answer into a variable**

| Function | Name | Value |
| --- | --- | --- |
| Set variable | `childName` | `{{answer:Child's first name}}` |

Then `{{var:childName}}` is `Maya` after that action has run.

**Reusable custom function (define once on Settings)**

| Name | Formula |
| --- | --- |
| `summaryLine` | `Thanks {{user.firstname}} — response for {{var:siteCode}}` |

On submit, run **Custom function** named `summaryLine`, then **Send email** with body `{{var:summaryLine}}`.

**Page complete flow**

When page `eligibility` finishes:

1. Set variable `eligStatus` = `{{answer:Are you eligible?}}`
2. Send email “Eligibility notify” (template can use `{{var:eligStatus}}`)
3. Go to page `main_survey` (use field **Logic** separately if “No” should go to end)

Variables belong to **this response**. Custom functions are named formulas you reuse; running one writes into `{{var:thatName}}`.

## Question library and templates

- Save a question or block to the **library** to reuse it on other forms. Open the **Library** tab, then click the saved item or drag it onto the form. Saving a **question group** stores the group and the questions inside it.
- **Save as template** (**Settings → Share**) stores the whole form as a starting point, labelled by type.
- **Import / export** questions as JSON or CSV when you need to move a questionnaire between forms or edit options in a spreadsheet. CSV can include every question type, including page breaks. JSON skip logic now uses the same portable `key` / variable names as CSV. On **Settings → Share** you can **append** or **replace** all questions. See [Import questions from CSV](creators-csv-import.md).

Templates do not copy live answers. They copy structure.

## Appearance while building

The builder shows the structure you will get on the fill page. Final colours and spacing are on **Settings → CSS**. Leave theme colours blank to keep the selected theme or site default.

Arabic and Urdu fill pages use right-to-left layout, including a mirrored thermometer. Enable those languages on **Settings → Languages** and translate them under **Settings → Translations**.

## Flow chart

**Settings → Flow chart** draws the saved form. Pages run from **Start** to **End of survey**, and every question is listed on its page. A plain line is the path when no branch matches. An amber line is a go-to, a page branch, a skip, or a screen-out, with the rule beside it. A show or hide rule is written under that question. The revision number is the latest save. Save before you read the chart: unsaved builder edits are not drawn.

## Checking your work

1. Save.
2. Open **Settings → Flow chart** and check each branch, then preview and complete the form as a respondent.
3. Try each skip path, including “back” if you allow editing or resume.
4. Confirm required questions cannot be skipped unless logic hides them.
5. Confirm emails and `{{var:…}}` text look right (use a test inbox).

## Related pages

- [Getting started](creators-getting-started.md)
- [Form settings](creators-settings.md)
- [Response integrity](creators-response-integrity.md)
- [Import questions from CSV](creators-csv-import.md)
- [Form types](creators-form-types.md)
