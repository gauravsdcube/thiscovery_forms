# Import questions from CSV

Use a spreadsheet to build or update a long questionnaire, then import it from **Settings → Share**. Excel is fine: save or export as **CSV UTF-8** (`.csv`). Do not upload `.xlsx`.

There is no limit on how many questions you can import. Empty rows and unknown types are skipped.

## Replace or append

On **Settings → Share**, choose the CSV (or JSON) file, then:

- Leave **Replace all existing questions** **unticked** to **append**. Imported questions are added after the ones already on the form.
- Tick **Replace all existing questions** to **remove every question** on the form and use only the file. You will be asked to confirm. Answers already collected will no longer match the old questions.

Save the form at least once before you import.

Download **Sample CSV** from Share if you want a working file to copy.

## Excel tips

1. Put the column names on the first row. Names are not case-sensitive (`Type` is the same as `type`).
2. One row is one question or page break.
3. For lists (options, grid rows, ranking items), put **each item on its own line inside the same cell**. In Excel: Alt+Enter (Windows) or Control+Option+Return (Mac).
4. Save as **CSV UTF-8**. If Excel only offers “CSV”, that usually still works.
5. You do not need every column. Keep `type` and `label`. Add extra columns only where that question needs them.

## Columns

| Column | What to enter |
| --- | --- |
| `type` | Question type code (see below). Display names such as `Page break` also work. |
| `key` | Optional short id (`age`, `hear_about`). Use this when skip logic, carry-forward, or page-break branches need to point at another row. Letters, numbers, and underscore only. |
| `variable` | Stable variable name for export, piping, and logic (letters, numbers, underscore; unique in the form). |
| `internal_label` | Studio-only name for the question card. |
| `label` | Question text. For a page break this can be left blank. |
| `help` | Help text under the question. |
| `required` | `1` / `yes` if required, otherwise `0` or blank. |
| `options` | One choice per line in the cell. Used by radio, checkbox, dropdown, and ranking. Use `code \| Label` for an internal code (matches studio Internal code + Participant label). |
| `hidden` | `1` to hide the question from respondents (internal variable). |
| `default_value` | Value stored when the question is hidden. |
| `meta_key` | For `respondent_meta`: `ip`, `browser`, `os`, `device`, `screen`, `language`, `timezone`, or `userAgent`. |
| `page_key` | Stable id for this **page** (page break rows). Used by skip logic and “go to page”. |
| `page_title` | Title shown at the start of that page. |
| `branches` | Optional JSON for page-break routing (see Page breaks below). |
| `rating_min`, `rating_max`, `rating_step` | Rating scale numbers. Typical 1–5, step 1. Thermometer: 0–100, step 1. |
| `rating_low_label`, `rating_high_label` | Labels under the lowest and highest values. |
| `rating_display` | `pills` (horizontal) or `thermometer` (vertical). |
| `grid_rows`, `grid_columns` | One label per line. Grids only. |
| `grid_mobile_layout` | Grids: `scroll` (default) or `stack` (each row as a stacked list on mobile). |
| `items` | One item per line for ranking, best–worst, and MaxDiff. If empty, `options` is used. |
| `maxdiff_set_size`, `maxdiff_set_count` | MaxDiff set size and how many sets to show. |
| `exclusive_option` | Checkbox label that clears the other ticks (for example `None of these`). Several labels can be separated with `\|`. |
| `max_select` | Maximum number of checkbox ticks. |
| `min_select` | Minimum number of checkbox ticks. |
| `min_select_all` | `1` to require every checkbox option (except an exclusive choice). |
| `randomize` | `1` to shuffle choice order. |
| `rich_content` | HTML for a rich text section. |
| `html_content` | Markup for an HTML block. |
| `html_collect` | `1` if the HTML block also collects a value. |
| `html_variable` | Variable name when collecting (`value` by default). |
| `html_instructions`, `html_required` | Instructions and required flag for HTML collection. |
| `drilldown_tree` | Nested list: two spaces indent each child level (see Drill-down below). |
| `image_url` | Image URL for an image-area question. Regions are easier to draw in the builder. |
| `image_mode` | `select` or `evaluate`. |
| `image_multi` | `1` to allow more than one region. |
| `image_regions` | JSON array of regions (`label`, `x`, `y`, `w`, `h` as percentages). Prefer the builder. |
| `carry_from` | `key` of an earlier choice question whose options should be reused. |
| `carry_mode` | `selected`, `unselected`, or `all`. |
| `prefill_profile` | Optional profile field name to pre-fill a text-like question. |
| `logic_action` | `show`, `hide`, `skip`, `skip_page`, `goto_page`, or `goto_end`. |
| `logic_combinator` | `and` or `or`. |
| `logic_goto` | Target `page_key` when the action is `goto_page`. |
| `logic_rules` | JSON array of rules (see Skip logic below). |

Boolean columns also accept `true`, `yes`, and `y`.

## Field types

Put the **code** in the `type` column.

| Code | Builder name | Extra columns |
| --- | --- | --- |
| `text` | Text | Optional `prefill_profile` |
| `textarea` | Textarea | — |
| `number` | Number | — |
| `email` | Email | Optional `prefill_profile` |
| `date` | Date | — |
| `dropdown` | Dropdown | `options` |
| `radio` | Radio | `options` |
| `checkbox` | Checkbox | `options`, optional `exclusive_option`, `max_select`, `min_select`, `min_select_all`, `randomize` |
| `rating` | Rating scale | `rating_min`, `rating_max`, `rating_step`, labels, `rating_display` |
| `ranking` | Ranking (drag & drop) | `options` or `items` |
| `file` | File upload | — |
| `page_break` | Page break | `page_key`, `page_title`, optional `branches` |
| `question_group` | Question group | Label is optional. Place the grouped questions after this row. |
| `group_end` | Group end | Closes the previous `question_group`. |
| `rich_text` | Rich text section | `rich_content` (HTML). Not an answer. |
| `html` | HTML / custom block | `html_content` and optional collect columns. Not an answer unless you collect. |
| `grid_single` | Grid (single) | `grid_rows`, `grid_columns` |
| `grid_multi` | Grid (multi) | `grid_rows`, `grid_columns` |
| `best_worst` | Best–worst | `items` |
| `maxdiff` | MaxDiff | `items`, `maxdiff_set_size`, `maxdiff_set_count` |
| `drilldown` | Drill-down | `drilldown_tree` |
| `image_area` | Image area | `image_url`, optional regions JSON |
| `respondent_meta` | Respondent metadata | Invisible. Set `meta_key` to the value to store (`ip`, `browser`, `os`, `device`, `screen`, `language`, `timezone`, `userAgent`). One row per value. |

If an option is named exactly `Other`, the fill page shows a box for a typed answer.

## Page breaks

A page break **starts the next page**. Put it on its own row, **before** the questions that belong on that page. The first questions (before any page break) are page 1.

Example:

```csv
type,key,label,required,page_key,page_title
radio,consent,Do you agree to take part?,1,,
page_break,page_about,Page break,0,about-you,About you
dropdown,age,Age group,0,,
```

- `page_key` should stay stable once people have started filling (for example `about-you`).
- `page_title` is what respondents see.
- `label` can be `Page break` or blank.

To jump from this break to another page when an earlier answer matches, put JSON in `branches`. `fieldKey` is the `key` of the question to test:

```json
[{"fieldKey":"consent","operator":"equals","value":"No","gotoPageKey":"end-page"}]
```

Give the destination page its own page-break row with that `page_key`. Operators are `equals`, `not_equals`, `contains`, and `checked`.

## Options, grids, and lists

In one cell:

```
Strongly disagree
Disagree
Neutral
Agree
Strongly agree
```

Use that pattern for `options`, `grid_rows`, `grid_columns`, and `items`.

## Rating

Horizontal 1–5:

| type | label | required | rating_min | rating_max | rating_step | rating_low_label | rating_high_label | rating_display |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `rating` | Overall, how would you rate this service? | 1 | 1 | 5 | 1 | Poor | Excellent | `pills` |

Vertical 0–100 thermometer: `rating_min` 0, `rating_max` 100, `rating_step` 1, `rating_display` `thermometer`.

## Drill-down

In `drilldown_tree`, indent children with **two spaces**:

```
England
  London
  Manchester
Wales
  Cardiff
```

## Skip logic

You can add show/hide and jumps in the builder after import. To include them in CSV:

1. Give source questions a `key`.
2. Set `logic_action` (and `logic_goto` when using `goto_page`).
3. Put rules in `logic_rules` as JSON:

```json
[{"fieldKey":"hear_about","operator":"equals","value":"Other"}]
```

`fieldKey` must match another row’s `key`. Export CSV from an existing form fills `key` and these columns for you.

Complex actions, field emails, and image hotspots are easier in the builder, or import **JSON** from Share.

## After import

Open **Form builder** and check order, page titles, and required flags. Use **Preview** before you open the form to respondents.

## Related pages

- [Builder and questions](creators-builder.md)
- [Sharing and results](creators-results.md)
- [Getting started](creators-getting-started.md)
