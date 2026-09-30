# Formulas

A formula is the text in a show/hide rule or a calculated question. The server calculates the stored result. The browser can show a preview.

Write a question as `[age]` and a variable as `[var:score]`. An arm is `[arm]`. A panel attribute is `[panel:site]`.

Numbers use 12 decimal places while calculating. The stored result uses the question’s decimal places, up to 6. Halves round away from zero, so 1.5 becomes 2 and -1.5 becomes -2.

`sum()` and `mean()` of answers that are all empty stay empty. If at least one answer has a value, the empty ones are left out. `empty = empty` is false. Use `is_empty([age])` to test a missing answer.

Choice rules use the option code, not the label. `today()` is the date in the form’s time zone. The default zone is Europe/London. That date is kept on the response.

A calculated question with “Hide the result” is still stored. A show/hide rule that hides it removes the stored value.

Loop checks use `any_eq([symptom[*]], "wheeze")`, `all_eq`, `count_answered`, and `sum`. One row is `[symptom["instance-key"]]`.

Named formulas are written `fn:name`. The starter formulas are body mass index, age, a PHQ-9 total and band, a GAD-7 total and band, and an EQ-5D profile string plus a level sum. There is no EQ-5D index.

In the studio, Test formula asks the server to calculate the expression and shows which calendar date `today()` used.
