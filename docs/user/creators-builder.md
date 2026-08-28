# Builder and questions

The **Builder** tab is where you add questions, split the form into pages, and attach logic or actions. Changes are not live until you **Save form**. The field list on the left and the canvas on the right scroll separately. There is no limit on how many questions a form can contain.

## Adding questions

Use **Add question** (or the equivalent control on the builder) and choose a type. Drag to reorder. Open a question to edit label, help text, required, options, and logic.

### Question types

| Type | Use for |
| --- | --- |
| Text | Short free text |
| Textarea | Longer free text |
| Number | Numeric values |
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

**Respondent metadata** (Add fields): drop each item you need — IP address, browser, operating system, device, screen size, browser language, time zone, or user agent. Each is a hidden field and becomes its own column in answers and CSV export. Drop only the ones you will analyse.

**Hidden from respondents:** tick this on any question to store an internal variable. People filling the form never see it. Set a **Stored value** if you want a fixed code on every response. Logic, piping, and export still use the stored value.

**Attention check:** tick this on an instructed-response question (for example “Please select Agree”) and type the expected answer. Pass and fail are stored on the response. They add to the quality score; they do not auto-reject. Turn on **Attention checks** on the [Response integrity](creators-response-integrity.md) tab.

**Choice codes:** each option can be `code | Label`. Respondents only see the label. Answers, logic, and CSV export use the code. A line without `|` keeps the same text for both.

**Other:** if a dropdown, radio, or checkbox option is named exactly `Other` (the label), the fill page shows a text box so the person can type their own answer.

**Page breaks** split the fill experience. Give each page a clear title. The **page key** is used by skip logic and “go to page” actions — keep keys stable once people have started filling. Logic or branch rules on a page break run when the person clicks **Next** on the page before that break.

Show or hide a later page with **Go to page if**. That still shows **Next** so the respondent can open that page. **Go to end if** is the action that replaces Next with **Submit**.

A later page with nothing visible is skipped, so respondents are not shown a blank page after the last page that still has something to show. Questions on pages they never visit are not required and do not block Submit.

If one branch should see a closing page (for example a thank-you for people who are not eligible) and the other branch should not, put **Show if** on that closing page’s content so it only appears for that branch. The other branch then finishes on the last page that still has questions. You can also put **Go to end if** on the last page break of the branch that should skip the closing page.

If a page break already sends “No” to another page, do **not** also put **Show if … equals No** on the questions on the page that “Yes” should open. Those questions would stay hidden when the answer is Yes, and that page would look empty. Leave Logic off those questions, or use **Show if … equals Yes**.

**Rich text** and **HTML** blocks are for explanation, consent, or licensed wording. They are not stored as answers.

## Required questions

Tick **Required** when the person must answer before they can continue or submit. Hidden questions, and questions on pages the person never visits, are not required.

## Options and grids

For choice questions, add one option per line. Use `code | Label` when you want an internal code. Grids need row labels and column labels. Ranking and MaxDiff need a complete set of items.

On a **checkbox** question you can set a **minimum** number of selections, or tick **Require every option to be selected**. Respondents cannot continue or submit until that rule is met. An exclusive option such as “None of these” still counts as a complete answer on its own.

## Logic, skip, and piping

You can show, hide, skip, or jump based on earlier answers.

To show several questions only when an earlier answer matches, add a **Question group**, put those questions inside it, and click **Logic** on the group (for example **Show this group if** the screening question equals `Yes`). You do not need the same rule on every question in the group. A page break cannot sit inside a group.

Typical operators include equals, does not equal, contains, and similar comparisons. Combine conditions with **AND** or **OR**.

Actions on a question or page often include:

- Skip this question or page
- Go to a named page
- Go to the end (submit / thank you)

**Answer piping** (carry-forward) inserts a previous answer into later labels or text using placeholders such as `{{answer:Question label}}`. Match the question label carefully. Preview after you rename a question — piping uses the label you configured.

Keep logic simple. Deep trees of skips are hard to test. Prefer a few clear branches over many overlapping rules.

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

**Set variable** and **custom function** values can use `{{answer:…}}` and later emails can use `{{var:name}}`. Define reusable formulas on Settings (see [Form settings](creators-settings.md)).

Use **Send email** for “email me a copy” or “notify the team when this page is completed” instead of putting a mail button on the form.

## Question library and templates

- Save a question or block to the **library** to reuse it on other forms. Open the **Library** tab, then click the saved item or drag it onto the form. Saving a **question group** stores the group and the questions inside it.
- **Save as template** (Share tab) stores the whole form as a starting point, labelled by type.
- **Import / export** questions as JSON or CSV when you need to move a questionnaire between forms or edit options in a spreadsheet. CSV can include every question type, including page breaks. On Share you can **append** or **replace** all questions. See [Import questions from CSV](creators-csv-import.md).

Templates do not copy live answers. They copy structure.

## Appearance while building

The builder shows the structure you will get on the fill page. Final colours and spacing are on the **CSS** tab. Leave CSS blank to keep the site theme.

Arabic and Urdu fill pages use right-to-left layout, including a mirrored thermometer. Add those languages on Settings if you need them.

## Checking your work

1. Save.
2. Preview and complete the form as a respondent.
3. Try each skip path, including “back” if you allow editing or resume.
4. Confirm required questions cannot be skipped unless logic hides them.
5. Confirm emails and `{{var:…}}` text look right (use a test inbox).

## Related pages

- [Getting started](creators-getting-started.md)
- [Form settings](creators-settings.md)
- [Response integrity](creators-response-integrity.md)
- [Import questions from CSV](creators-csv-import.md)
- [Form types](creators-form-types.md)
