# Getting started with Thiscovery Forms

This page is for people who create and run forms. Administrators enable the module and permissions; you build the form, invite people, and look at results.

## Where forms live

- **Space forms** — created inside a space. Members of that space (and guests, if you allow anonymous fill) are the usual audience.
- **Network (global) forms** — created at site level. Use these when one form should not belong to a single space.

You need **Create forms** (space) or **Create global forms** (network) to start a form, and **Manage forms** / **Manage global forms** to edit, delete, export, and open dashboards.

## Create a form

1. Open **Forms** in the space (or the global Forms list).
2. Choose **Create**.
3. Pick a **type**, or start from a saved **template**.
4. You land in the **studio** (the form editor).

Type cannot be changed later in a meaningful way — pick the closest match. See [Form types](creators-form-types.md) for when to use each one.

If a type is missing, an administrator has switched it off in module configuration.

## The studio

The studio has two top tabs: **Form builder** and **Settings**.

| Place | What you do |
| --- | --- |
| **Form builder** | Questions, pages, logic, and field/page actions (Add fields and Library) |
| **Settings** | Everything else, one section at a time via the left-hand list |

Under **Settings**, the left rail groups are:

- **Form** — Basics, End of survey, Who can take part, Participant display, Sharing and display, Panel enrolment, Email templates, Languages, Actions and functions (plus **Consensus** on Consensus / Delphi forms)
- **Programme** — Panel & waves, Rounds, or Approval when that type needs them
- **Quality & style** — Response integrity, Translations, CSS (themes and colours)
- **Publish** — Share; **Versions** after the first save when Thiscovery Versioning is enabled for Forms

Save applies to the whole form from either top tab. Help in the studio header opens the page that matches the section you are on.

### Header and footer buttons

- **Back to forms** — return to the list. Unsaved builder changes are lost if you have not saved.
- **Preview** — saves the form, then opens a **test** copy of the fill page. Test answers are stored separately. They do **not** count in dashboards, totals, or CSV.
- **Save form** — saves and **stays in the studio**. Use this while you are still building.
- **Delete form** — permanently removes the form and its responses. There is no undo.

Save often. Preview is the right way to try the form yourself without polluting real results.

## Status

On **Settings → Basics**:

| Status | Meaning |
| --- | --- |
| Draft | You and other managers can open it; respondents should not treat it as live |
| Open | People who are allowed to can fill it — only after you have **published an edition** (when versioning is on) |
| Closed | Filling stops; you can still view answers and export |

**Publish** (**Settings → Versions** or **Settings → Share**) freezes an edition that participants use. Saving alone does not change what people see while the form is Open. See [Versions and publishing](creators-versioning.md).

Set status to **Open** when you are ready to share the live link. Close it when fieldwork ends.

## Filling versus preview

- **Open** (from the list) or the **share link** — real responses, subject to your settings (anonymous, multiple submissions, waves, and so on).
- **Preview** / test link — for you and stakeholders. Same look, logic, and resume settings; answers are marked as tests and excluded from results. Preview does not save progress unless **Allow save and resume** is on.

**Settings → Share** has both links. Copy the live link only after the form is Open and you have tried Preview.

## Folders

On **Settings → Basics** you can file the form in a folder, or leave it **Unfiled**. Folders help teams find work; they do not replace who is allowed to manage the form.

## Typical first build

1. Create a **Survey** (or the type you need).
2. On **Form builder**, add questions and page breaks.
3. On **Settings → Basics**, set title, status **Draft**, and who can view answers.
4. On **Settings → Who can take part**, set anonymous, resume, and related options.
5. **Save**, then **Preview**. Walk through as a respondent would.
6. Fix wording and logic. Save again.
7. **Publish current draft** (**Settings → Share** or **Settings → Versions**).
8. Set status to **Open**. Copy the live link from **Settings → Share**.
9. After responses arrive, use the form’s dashboard and CSV (see [Sharing and results](creators-results.md)).

## Related pages

- [Builder and questions](creators-builder.md)
- [Form settings](creators-settings.md)
- [Response integrity](creators-response-integrity.md)
- [Sharing and results](creators-results.md)
- [Import questions from CSV](creators-csv-import.md)
- [Panels, waves, and email](creators-panels.md)
- [Form types](creators-form-types.md)
