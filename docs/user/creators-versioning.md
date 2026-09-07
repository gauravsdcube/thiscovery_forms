# Versions, revisions, and editions

Use the **Versions** tab to manage how your form changes over time without surprising people who are already filling it.

## Revisions vs editions

| Term | When it is created | What it is for |
| --- | --- | --- |
| **Revision** | Every time you **Save** in the studio | A snapshot of the working draft (questions, settings, integrity, CSS/theme, translations, and related definition data) |
| **Edition** | When you **Publish** | The frozen definition participants use. Answers store which edition they belong to |

Revision numbers and edition numbers are separate (for example Revision #12 and Edition #3).

## Status still means availability

**Draft / Open / Closed** still control whether the form accepts responses. They are independent of which edition is live.

- **Draft** — managers and preview only
- **Open** — accepting responses against the **current published edition**
- **Closed** — not accepting responses; the published edition stays for audit and reopen

You **cannot set status to Open** until at least one edition has been published.

## Typical flow

1. Build and **Save** (creates revisions as you go).
2. **Publish current draft** from the Versions tab or the Share tab.
3. Set status to **Open** and save.
4. Later edits stay in the working draft until you publish again. New respondents get the new edition; people already in progress keep the edition they started.

## Restore

**Restore** on a revision replaces the working draft with that snapshot and creates a new revision labelled “Restored from …”. It does **not** change the live published edition or past answers. Publish again when you want participants to use the restored content.

## Preview

- **Share → Preview** uses the current working draft (test answers).
- **Versions → Preview** opens a fill preview of that revision or edition snapshot.

## Delete

You can delete old revisions and editions you no longer need. You **cannot** delete a published edition that has answers, or the revision that backs the current published edition.

## Open periods

The Versions tab (and forms list history) records when a form was opened and closed, and which edition was live. That helps audit fieldwork windows.

## Answers

Each response stores its **edition**. Dashboards and exports can show which published definition someone answered.

## Permissions

By default, anyone who can manage the form can view, restore, publish, and delete versions. Space and network permissions for versioning can be tightened under HumHub permissions if needed.

Site administrators can turn Forms versioning on or off under **Administration → Modules → Thiscovery Versioning** (Configure). When off, the Versions tab and publish rules are hidden; existing history is kept.
