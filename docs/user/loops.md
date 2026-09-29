# Loops

Loops are off unless an administrator turns them on for the module and the form also turns them on. With both off, a question group is shown once and answers are stored as they were in 1.28.7.

A group can repeat in one of three ways:

- A fixed list of codes and labels.
- The options someone selected on an earlier question.
- A number from an earlier question, stored as n1, n2, and so on, up to the maximum.

The source question has to come before the group. A loop cannot contain another loop. Rosters are not in this version.

Each repeat is its own answer. If someone unselects an option, that repeat is hidden. The answer is kept. The default export leaves the cell blank. Selecting the option again shows the kept answer. The studio says: these answers are kept but not shown.

A number that goes from 2 to 1 hides n2. n1 stays n1.

The wide export uses the question variable, two underscores, and the instance code, for example `symptom__asthma`. The codebook lists those columns. A long export is one row per repeat that was shown.

Logic on a repeated question can use any, all, count, or sum. Any other aggregate is an authoring error and the form cannot be published.

Text can include `{{loop.label}}`, `{{loop.index}}`, and `{{loop.count}}`. `{{answer:symptom}}` is the current repeat. `{{answer:symptom[asthma]}}` is another repeat.

Resume stores the repeat that was open. Required questions are required on each repeat that is still shown. A hidden repeat is not required.
