# Formulas

A formula is the text in a show/hide rule or a calculated question. The server calculates the stored result. The browser can show a preview.

A form saved before formulas, with a field, an operator, and a value, opens as a formula. Saving the form stores that formula. A rule that uses a loop aggregate other than any, all, count, or sum cannot be converted and is refused.

Write a question as `[age]` and a variable as `[var:score]`. An arm is `[arm]`. A panel attribute is `[panel:site]`.

Numbers use 12 decimal places while calculating. The stored result uses the question’s decimal places, up to 6. Halves round away from zero, so 1.5 becomes 2 and -1.5 becomes -2.

`sum()` and `mean()` of answers that are all empty stay empty. If at least one answer has a value, the empty ones are left out. `empty = empty` is false. Use `is_empty([age])` to test a missing answer.

Choice rules use the option code, not the label. `today()` is the date in the form’s time zone. The default zone is Europe/London. That date is kept on the response.

A calculated question with “Hide the result” is still stored. A show/hide rule that hides it removes the stored value.

Loop checks use `any_eq([symptom[*]], "wheeze")`, `all_eq`, `count_answered`, and `sum`. One row is `[symptom["instance-key"]]`.

Named formulas are written `fn:name`. The starter formulas are body mass index, age, a PHQ-9 total and band, a GAD-7 total and band, and an EQ-5D profile string plus a level sum. There is no EQ-5D index.

Test formula asks the server to calculate the expression and shows which calendar date `today()` used.

## Build visually

**Build visually** sits next to every formula box: show or hide, a calculated question, an answer check, and a page-break branch. The box itself stays. The window is another way to write the same text.

Each row is one question, a comparison, and a value:

- **equals** or **does not equal**
- **is one of** or **is not one of** — tick the choices. Several values are separated by commas, for example `[support] in ["Received some but not enough", "Received none"]`. Do not write `or` inside the list.
- **is greater than**, **is at least**, **is less than**, **is at most** — for numbers, or for another question such as `[end_date] >= [start_date]`

**All of these** joins the rows with `and`. **Any of these** joins them with `or`. **Add group** puts a bracket around its own rows, so you can mix the two: all of “question A equals Yes” and a group that is any of “question B is one of Yes or Maybe”.

**Not** on a row or a group wraps that part in `not (...)`.

The line at the bottom of the window is the formula that will be saved. **Use this formula** puts it in the box and replaces what was there. You can then change the text by hand. Open **Build visually** again and the window reads the box, so either place can edit the formula.

A formula the window cannot show — a sum, a date, `selected`, or `or` written inside a list — is left in the box. The window says so, and the text changes only if you use a formula from the window.

Choice values are the option codes. The window lists each question by its label and its variable name, such as `Supported (c3a_supported)`.

## Writing rules

- **Lists:** `[mood] in [1, 2]` and `[mood] not_in ["x", [other]]` test an answer against a list.
- **Powers:** a leading minus applies before `^`, so `-2^2` is `(-2)^2` = `4`. Write `-(2^2)` for -4. The power must be a whole number from 0 to 20; anything else gives an empty result.
- **Dates:** write `date("2024-02-03")` with four-digit year, two-digit month and day. A date that does not exist, such as 2024-02-30, is refused when you save.
- **Text on a list:** `contains_text([q], "x")` on a multiple-choice answer means one of the ticked options is `x`. On plain text it means the text contains `x`.
- **Scores:** `score_of([q])` uses the option's score. On a scored question, an option with no score (for example "Prefer not to say") scores nothing.
- **Number answers** are plain decimals. `1e5` is not accepted.
- **Grid rows:** `[mood.sleep]` is one row of the grid `mood`. `[mood] = "agree"` is true when any row is "agree".
- **Multiple choice:** `selected([sym], "a")` is true when "a" is ticked. `selected_all([sym], "a", "b")` needs both ticked (others may be too). `selected_only([sym], "a", "b")` needs exactly those two and nothing else. An option coded `0` counts as ticked.
- **Answered or not:** `is_answered([q])` and `is_empty([q])`. Ranges: `between([age], 18, 65)`. Counts: `count_selected([sym]) >= 2`.
- **Dates:** compare dates directly, for example `[visit] > date("2024-01-01")`, or use `date_diff`.
- **Routing:** a question has one rule. To show a question and also branch on it, put the branch on the page break after it: branch rules are tried in order, and "Otherwise go to" names the page used when none matches (empty means the next page). "Hide this question if" replaces the old "Skip this question if", which did the same thing.
- **Checks:** an unknown function is refused when you save, and a reference to a question that is not on the form is refused when you publish. Renaming a question's variable updates every formula that uses it.

## Scoring an instrument

Scores need no code. Use calculated questions:

- **Sum score:** `sum(score_of([q1]), score_of([q2]), score_of([q3]))`. Give each option its score in the option list.
- **Reverse coding:** score the option list in reverse, or write `4 - score_of([q5])` for a 0–4 item.
- **Subscales:** one calculated question per subscale, each summing its own items. A total can then add the subscales together.
- **Missing items:** `min_valid(6, ...)` gives a score only when at least 6 items are answered.
- **Cut-offs and bands:** `if([total] >= 10, "Moderate", "Mild")`, nested for more bands.

The starter library has PHQ-9 and GAD-7 totals and bands to copy from.
