# Versions, revisions, and editions

Open **Settings → Versions** (Publish group) after you have saved the form at least once. Versioning uses the **Thiscovery Versioning** module. If that module is off or Forms versioning is disabled, Versions is hidden and Open does not require a published edition.

You can also **Publish current draft** from **Settings → Share**.

## Revisions vs editions

| Term | When it is created | What it is for |
| --- | --- |
| **Revision** | Every time you **Save** in the studio | A snapshot of the working draft (questions, settings, integrity, CSS/theme, translations, and related definition data) |
| **Edition** | When you **Publish** | The frozen definition participants use. Answers store which edition they belong to |

Revision numbers and edition numbers are separate (for example Revision #12 and Edition #3).

## Status still means availability

**Draft / Open / Closed** still control whether the form accepts responses. They are independent of which edition is live.

- **Draft** — managers and preview only
- **Open** — accepting responses against the **current published edition**
- **Closed** — not accepting responses; the published edition stays for audit and reopen

When versioning is on, you **cannot set status to Open** until at least one edition has been published.

## Typical flow

1. Build the form in the studio.
2. **Publish current draft** from **Settings → Versions** or **Settings → Share**. This **saves** your latest studio changes and then freezes them as the live edition.
3. Set status to **Open** and save if it is not already Open.
4. Later edits stay in the working draft until you publish again. New respondents get the new edition; people already in progress keep the edition they started.

## Restore

**Restore** on a revision replaces the working draft with that snapshot and creates a new revision labelled “Restored from …”. It does **not** change the live published edition or past answers. Publish again when you want participants to use the restored content.

## Preview

- **Settings → Share → Preview** uses the current working draft (test answers).
- **Settings → Versions → Preview** opens a fill preview of that revision or edition snapshot.

## Delete

You can delete old revisions and editions you no longer need. You **cannot** delete a published edition that has answers, or the revision that backs the current published edition.

## Open periods

**Settings → Versions** (and forms list history) records when a form was opened and closed, and which edition was live. That helps audit fieldwork windows.

## Answers

Each response stores its **edition**. Dashboards and exports can show which published definition someone answered.

## Permissions

By default, anyone who can manage the form can view, restore, publish, and delete versions. Space and network permissions for versioning can be tightened under HumHub permissions if needed.

Site administrators can turn Forms versioning on or off under **Administration → Modules → Thiscovery Versioning** (Configure). When off, Versions is hidden and publish-before-Open rules do not apply; existing history is kept.
