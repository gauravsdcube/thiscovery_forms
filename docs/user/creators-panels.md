# Panels, waves, and email

Panels are lists of people you invite more than once. Waves are scheduled openings of a form (or of a panel’s shared calendar). Email templates are the letters you send for invites, reminders, and follow-up.

## Panels

Panels are a first-class list in Thiscovery Forms, not only a setting inside one survey.

- Open **Panels** from the Forms area (space or global, matching where you work).
- Each **member** is a record you can open: who they are, how they were added, and which completions they have.
- One person can belong to **several panels**.

### Adding members

You can:

- Select people who already have a site account
- Add by **email**, with first and last name and any extra fields you have defined
- **Upload CSV** for a batch (header row; extra columns use the field key)

Use consistent email addresses. Invites and resume-style mail go to that address.

### Extra member fields

On **Edit panel**, add fields beyond first name, last name, and email (for example postcode or organisation). You can search those values on the panel, import them in CSV, and use them in a survey:

- Pipe `{{member.field_key}}` into labels, help, HTML blocks, and email templates
- In **Add fields**, the **Panel member** group lists identity fields plus this panel’s extra fields. Drop one onto the form to store a snapshot on the response. Leave it hidden, or show it so the person can confirm or update it. On submit it is written back to the member record.

Open a **member** to see everything stored on them: identity, extra fields, consent, and form completions. Survey answers themselves stay on the form response (open a completion to view them).

### Enrolment from a form

Attaching a panel on **Panel & waves** adds each completer who can be identified (invite link, signed-in email, or an email question on the form) and lists that completion on the member. Preview and live fills both count. On **Settings → Panel enrolment**, a form can also add completers to a *different* panel. One person can belong to several panels.

## Waves

**Longitudinal** forms always use waves: one complete response per panel member per wave.

**Ordinary surveys** (and related types that support it) use waves when you tick **Use waves** on **Settings → Who can take part**. After you save, **Settings → Panel & waves** appears under Programme.

### Per survey versus per panel

On the same settings pane, choose **Where waves live**:

- **Per survey** — you open and close waves on this form. Other forms keep their own dates.
- **Per panel** — the panel has one calendar. Every form attached to that panel uses the wave that is currently open. You still send invite emails **from each form**.

Pick the mode that matches how your study is run before you promise dates to a panel.

### Wave 1 and later waves

- **Wave 1** of an anonymous wave form can be filled **without** a panel invite token (public or anonymous link).
- **Later waves** still need an invitation (panel token). Do not send only the generic fill link for wave 2 onwards.

That lets you recruit on wave 1 and follow up the same people afterwards.

## Email templates

Templates are written with **Thiscovery Editor** and managed as their own list (not buried only inside one form). Each template has:

- **Subject**
- **Email header** — optional banner (logo, title). You can set background and text colour.
- **Email body** — the main message. Headings, lists, links, and buttons are supported. A button URL can be `{{formUrl}}`.
- **Email footer** — optional contact line or disclaimer, with its own colours.

Sent mail uses the same header / body / footer card as other Thiscovery emails. Empty header or footer simply omits that band.

Typical uses:

- Panel invite
- Wave open
- Reminder
- After completion
- Action-triggered mail (from a page or submit action)

On the form, pick which template to use for each purpose. Actions on the builder can also **Send email** with a chosen template.

You can insert:

- `{{answer:Question label}}` — a response given on this fill
- `{{var:name}}` — a value stored by **Set variable** or a **custom function**
- `{{member.first_name}}`, `{{member.email}}`, `{{member.field_key}}` — panel member values
- `{{panel.title}}` — the panel name

Send a Preview completion to yourself before you mail a panel.

If the site cannot send email, invites and action mail will not arrive. That is a hosting/administration issue, not something you can fix inside the form.

## Running a wave

A practical sequence:

1. Build and Preview the form. Status can stay Draft until you are ready.
2. Create or choose a **panel**. Add members (or enrol from a previous form).
3. Attach the panel on **Panel & waves**. Create wave dates (or use the panel calendar if the site is in per-panel mode).
4. Choose email templates.
5. Set the form **Open**. Open the wave when fieldwork should start.
6. Send invites from the form. For wave 1 anonymous recruitment, you may also share the public fill link.
7. Watch the dashboard for completes and attrition. Send reminders as needed.
8. Close the wave (and later the form) when the window ends.

## Related pages

- [Form settings](creators-settings.md)
- [Form types](creators-form-types.md)
- [Sharing and results](creators-results.md)
- [Thiscovery Forms for administrators](admins.md) (wave scope)
