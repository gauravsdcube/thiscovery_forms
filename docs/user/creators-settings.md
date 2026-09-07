# Form settings

The studio has two top tabs: **Form builder** and **Settings**.

Under **Settings**, a left-hand list (same style as the field palette) opens each area in the main pane: form settings, programme tools, quality & style, and publish. Save still applies to the whole form.

Type is shown here but chosen at create time.

## Basics

| Setting | What it does |
| --- | --- |
| Title | Name on the form, in lists, and in emails |
| Description | Shown to respondents (rich text) |
| Status | Draft, Open, or Closed. **Open requires a published edition** (Versions or Share → Publish). Participants use the published edition, not the unsaved working draft. |
| Folder | Filing; **Unfiled** is fine |
| Who can view answers | Who may open the answers / dashboard for this form |

**Who can view answers**

- **Author and managers only** — you and people with Manage.
- **Managers and respondents** — managers, plus someone who has submitted this form.
- **Anyone with View Answers permission** — uses the space or global **View form answers** permission.

## End of survey

| Setting | What it does |
| --- | --- |
| After a successful submission | **Show a message** (thank you, optional button) or **Redirect to a URL** |
| Thank you message | Rich text after submit when using message mode; empty uses the default thank-you |
| Thank-you button | Optional label and URL (blank URL returns to this form) |
| Redirect URL | Used instead of the thank-you page when redirect mode is selected |
| Already submitted message | Shown when another submission is blocked; supports `{formName}`; empty uses the default |
| Already-submitted button | Optional label and URL (blank URL goes to the forms list) |

Use the end-of-survey messages to say what happens next, not to collect more answers.

## Who can take part

| Setting | What it does |
| --- | --- |
| Allow multiple submissions | The same person can submit more than once (not used on wave / consensus rounds in the same way — those are one per wave or round) |
| Allow anonymous submissions | People can fill without signing in, if the site allows guests |
| Allow edit after submit | Respondents can change a completed response |
| Save and resume | People can continue later with a resume code |
| Keep incomplete responses | In-progress answers are stored, counted on the dashboard, and included in CSV |
| Use waves | Ordinary surveys only, and only if an administrator has allowed waves on surveys |

**Anonymous:** Wave 1 of a wave-based form can be filled without a panel token when anonymous is on. Later waves still need an invitation. Do not assume a public link works for wave 2.

**Resume:** When this is on, opening the form asks whether to continue a saved response or start a new one. That applies to signed-in people, guests, and **Preview**. Progress is not saved, and no resume code is shown, unless this is on and the person chooses to continue or uses **Save & continue later**. If multiple submissions are off and the person has already submitted, they see the **Already submitted** message instead.

Give people a way to keep the resume code (copy or email) if you expect long forms.

**Keep incomplete:** Use this when you need drop-off counts or partial data. Incomplete rows appear in export; treat them carefully in analysis.

## Sharing and display

| Setting | What it does |
| --- | --- |
| Public dashboard | Lets you generate a dashboard link that does not require sign-in (link itself is on the Share tab) |
| Show in menu | Lists an open form in the space side menu |
| Hide HumHub header | Fill, preview, and thank-you pages run without the site header or space menu |
| Poll results | For quick polls: whether to show results after voting (as configured on the poll) |

**Hide HumHub header** is useful for kiosks, email links, and embedding a focused task. Managers still use the normal studio and list with the usual HumHub chrome. Opening a headerless form from the list does a full page load so the header can hide; **Edit** from that fill page loads the studio with the header back.

## Panel enrolment

If the form already has a panel on **Panel & waves**, people who complete it with an email (or a signed-in account) are added to that panel automatically, and the completion is listed on the member.

Use this section only to add completers to a **different** panel as well. Tick **Record each completion on the panel** if those enrolment completions should appear on the enrolment panel. One person can belong to several panels.

Details of invites and waves are on [Panels, waves, and email](creators-panels.md).

## Email templates

Choose templates (written in Thiscovery Editor) for invites, waves, reminders, and post-completion mail. Create templates once and reuse them. Placeholders such as `{{var:name}}` and answer piping work where the product inserts them.

If no custom template is chosen, a default text is used where the action or invite still sends mail.

## Languages

Add languages and provide translations for questions and chrome. Respondents can switch language on the fill page. Arabic and Urdu use a right-to-left layout.

Leave a language incomplete and people will see the default language for missing strings — finish the overlay before you go live in that language.

## Actions and functions

**Custom functions** are named formulas you reuse across this form. Give each a **name** (letters, numbers, underscore — for example `riskBand`) and a **formula**. Actions can run a custom function by name. Emails and later text can insert `{{var:name}}`.

**On submit** — a list of actions (send email, set variable, go to page, go to end, custom function) that run when the form is submitted. Use this for a confirmation email or to store a derived value at the end.

Define functions here; attach **when** they run on the Builder (field/page) or in this **On submit** list.

### Worked examples

**Custom function table on Settings**

| Name | Formula |
| --- | --- |
| `studyArm` | `control` |
| `riskBand` | `Review needed — score {{answer:Overall score}}` |

Running **Custom function** `studyArm` stores `{{var:studyArm}}` = `control`.  
Running **Custom function** `riskBand` when Overall score is `8` stores `{{var:riskBand}}` = `Review needed — score 8`.

**On submit list**

1. Set variable `siteCode` = `DEMO-01`
2. Custom function `riskBand`
3. Send email “Thank you” with body:

```text
Hello {{user.firstname}},

Your study code is {{var:siteCode}}.
{{var:riskBand}}
```

Result for Alex with score 8:

```text
Hello Alex,

Your study code is DEMO-01.
Review needed — score 8
```

Placeholders also work in invite and wave templates where the product inserts them. See [Builder and questions](creators-builder.md) for the full placeholder list and field/page action examples.

## Consensus

Visible only on **Consensus / Delphi** forms. Identity mode, comments, and weighted votes are configured with the rounds (see [Form types](creators-form-types.md)).

## CSS tab (not under Settings)

Appearance of the **fill page** only: page, card, questions, buttons, and per-question-type styles, plus a custom CSS box. Empty fields keep the site theme. Studio, lists, and dashboards stay on the normal theme.

## Related pages

- [Getting started](creators-getting-started.md)
- [Builder and questions](creators-builder.md)
- [Response integrity](creators-response-integrity.md)
- [Sharing and results](creators-results.md)
- [Panels, waves, and email](creators-panels.md)
