# Randomisation

Randomisation stays off until both switches are on: the module setting under Administration, and “Use randomisation on this form” in the form settings.

The server draws the order. The browser does not shuffle. The order is stored with the response, so a saved draft shows the same order. Preview can draw again with Re-randomise. A real response cannot.

## What can be randomised

- Options on a choice question that already has “randomise options” turned on. Each response gets its own order. There is no setting that shows every guest the same shuffled order. Other and exclusive options stay at the end. The order stored is the order first shown.
- Questions inside a question group. Choose shuffle or rotate, and optionally how many to show. Questions that are not shown are not on the route and their answers are not kept.
- Pages between **Shuffled pages** and **End of shuffled pages**. Give the set a name. That name is the download column `rand.<name>.order`, which lists the page keys in the order that person saw them. Leave **Pages to show** empty to show every page. **Random order** shuffles the pages for each person. **Take turns** moves the starting page along for each new person. A go-to that leaves the set is refused when you save the form. Questions on a page stay in the order you built them unless that question group has **Shuffle the questions in this group** set to Yes.

## Groups

In **Settings → Randomisation → Groups**, add one row per group: a code, a name, and a weight. Leave every weight at 1 when the groups should be equal.

How people are placed:

- **Random each time**: each new person is drawn from the weights. A group can come up again before every group has been used.
- **Shuffled list**: a list is built in the weight ratio, shuffled, then handed out. The first person might get group 7 and the next person group 2. **People in each round** must be a multiple of the weights added together. For 24 equal groups, enter 24. The next list starts when that one is used up. Two people starting together are not given the same place.
- **Smallest group first**: the group with fewer people is preferred, with a random element so the next group cannot be predicted from the counts. Abandoned responses do not hold a place.
- **Shuffled list inside a category**: the same shuffled list inside each answer you name. One line per question, `field:variable` or `panel:site`. A fully anonymous form cannot use a panel field.

Someone is placed when the form opens, or after they leave the page whose key you set.

`{{arm}}` inserts the group name. `{{arm_code}}` inserts the code. Logic still uses the name `arm`, for example “show if arm equals g01”. The answer download columns stay `arm_code` and `arm_name`.

A test response is not placed in a group and is not counted.

## Screened out

A question or page break can use “End as screened out if”. This works whether or not randomisation is on. The response is closed. It is not counted as a completed questionnaire, and the person is not reminded to finish. The message and optional URL are under **Settings → End of survey → When someone is screened out**. **Go to end if** is a normal completion and still uses the thank-you or the completion URL.

## Export

The response export includes the outcome, the group code and name (`arm_code`, `arm_name`), the method, when the person was placed, the category, and the stored orders (`rand.<name>.order`). Codebook and Group list are separate downloads on the dashboard. Completed counts omit screened-out responses.
