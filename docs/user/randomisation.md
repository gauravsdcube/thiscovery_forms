# Randomisation

Randomisation stays off until both switches are on: the module setting under Administration, and “Use randomisation on this form” in the form settings.

The server draws the order. The browser does not shuffle. The order is stored with the response, so a saved draft shows the same order. Preview can draw again with Re-randomise. A real response cannot.

## What can be randomised

- Options on a choice question that already has “randomise options” turned on. Other and exclusive options stay at the end.
- Questions inside a question group. Choose shuffle or rotate, and optionally how many to show. Questions that are not shown are not on the route and their answers are not kept.
- Pages between a randomisation block and its end marker. A go-to that leaves the block is refused when you save the form. Pin is not available for pages; use shuffle, rotate, or show a number of pages by setting the block method.

## Arms

One arm per line: `code|name|weight`.

Methods:

- Simple weighted: each new response is drawn from the weights.
- Block: a block is filled in the weight ratio, shuffled, then handed out in order. The next block starts when that one is used up.
- Least filled: the arm with fewer assignments is chosen. A tie uses the response seed.
- Stratified block: the same block method inside a stratum. One factor per line, `field:variable` or `panel:site`. A fully anonymous form cannot use a panel attribute.

Assignment happens when the response starts, or after the respondent leaves the page whose key you set.

`{{arm}}` inserts the arm name. `{{arm_code}}` inserts the code. Logic can test the field key `arm`.

A test response is not assigned and is not counted.

## Screened out

A question can use “End as screened out if”. The response is closed. It is not counted as a completed questionnaire, and the person is not reminded to finish. The screened-out message in the form settings is the text to show them.

## Export

The response export includes the outcome, the arm code and name, the method, when it was assigned, the stratum, and the stored orders (`rand.<variable>.order`). Codebook and Allocation log are separate downloads on the dashboard. Completed counts omit screened-out responses.
