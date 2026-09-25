# Form types

Choose a type when you create the form. Each type is a different workflow. Administrators can hide types you should not use.

## Survey

Full multi-page form. Any question type, logic, actions, translations, optional resume, optional anonymous fill.

Use waves on a survey when you tick **Use waves** on **Settings → Who can take part**, then set up the panel and schedule under **Settings → Panel & waves**. Choose **Where waves live** (per survey or per panel) on the same access settings. Otherwise treat the survey as a single fieldwork window.

## Quick poll

One question. Designed to drop on a **page** or the **stream**. Keep it short. Results can show after voting if you enable poll results.

Do not use a poll when you need paging, waves, or a long questionnaire — use a survey.

## Feedback form

Starts as a short rating plus comments. Edit it like a survey. Good for event or service feedback.

## Longitudinal survey

The same **panel** answers **repeating waves** of this questionnaire. Attrition appears on the dashboard.

Always uses waves. Build the questions once; schedule waves and mail from **Settings → Panel & waves**.

## Consensus / Delphi

Multi-round process:

- One submission per person **per round**
- Published summaries between rounds
- Identity, threshold, freeze, and required comments under **Settings → Consensus**

Set rounds on **Settings → Rounds**. Do not use this type for a simple one-off survey.

## Project

A structured **record** (default title is like a project record) with:

- Configurable **approval stages** (named users and/or groups)
- Request-changes and a **decision log**
- A **catalogue** of records that have been published through the workflow

Submissions are not a public catalogue item until they pass the stages on **Settings → Approval**. Use this for applications, case records, or anything that needs sign-off, not for anonymous opinion polls.

## Which type should I pick?

| You need | Type |
| --- | --- |
| Long questionnaire, one window | Survey |
| Single question on a page or stream | Quick poll |
| Quick rating and comment | Feedback form |
| Same people, several time points | Longitudinal survey (or survey with **Use waves**) |
| Experts, rounds, feedback of the group | Consensus / Delphi |
| Record plus approvals and a published list | Project |

If you are unsure, start with **Survey**. You can save it as a template after you like the structure.

## Related pages

- [Getting started](creators-getting-started.md)
- [Builder and questions](creators-builder.md)
- [Panels, waves, and email](creators-panels.md)
- [Thiscovery Forms for administrators](admins.md)
