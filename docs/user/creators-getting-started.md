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
4. You land in the **studio** (the form builder).

Type cannot be changed later in a meaningful way — pick the closest match. See [Form types](creators-form-types.md) for when to use each one.

If a type is missing, an administrator has switched it off in module configuration.

## The studio

The studio is the editor for one form. Tabs typically include:

| Tab | What you do there |
| --- | --- |
| Builder | Add questions, pages, logic, and field actions |
| Settings | Title, status, who can take part, emails, languages, functions |
| Response integrity | Bot protection, quality scoring, consistency rules, invitation links |
| CSS | Fill-page colours and layout; blank keeps the site theme |
| Share | Live link, preview link, publish current draft, dashboard link, save as template |
| Versions | Revisions (every save), published editions, restore, open-period history |
| Panel & waves | Shown when the form uses waves |
| Approval | Project type only |
| Rounds | Consensus / Delphi only |

Extra tabs appear only when they apply to that type.

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
| Open | People who are allowed to can fill it — only after you have **published an edition** |
| Closed | Filling stops; you can still view answers and export |

**Publish** (Versions or Share) freezes an edition that participants use. Saving alone does not change what people see while the form is Open. See [Versions and publishing](creators-versioning.md).

Set status to **Open** when you are ready to share the live link. Close it when fieldwork ends.

## Filling versus preview

- **Open** (from the list) or the **share link** — real responses, subject to your settings (anonymous, multiple submissions, waves, and so on).
- **Preview** / test link — for you and stakeholders. Same look, logic, and resume settings; answers are marked as tests and excluded from results. Preview does not save progress unless **Save and resume** is on.

The Share tab has both links. Copy the live link only after the form is Open and you have tried Preview.

## Folders

On Settings you can file the form in a folder, or leave it **Unfiled**. Folders help teams find work; they do not replace who is allowed to manage the form.

## Typical first build

1. Create a **Survey** (or the type you need).
2. On **Builder**, add questions and page breaks.
3. On **Settings**, set title, status **Draft**, and who can take part.
4. **Save**, then **Preview**. Walk through as a respondent would.
5. Fix wording and logic. Save again.
6. **Publish current draft** (Share or Versions).
7. Set status to **Open**. Copy the live link from **Share**.
7. After responses arrive, use the form’s dashboard and CSV (see [Sharing and results](creators-results.md)).

## Related pages

- [Builder and questions](creators-builder.md)
- [Form settings](creators-settings.md)
- [Response integrity](creators-response-integrity.md)
- [Sharing and results](creators-results.md)
- [Import questions from CSV](creators-csv-import.md)
- [Panels, waves, and email](creators-panels.md)
- [Form types](creators-form-types.md)
