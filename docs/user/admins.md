# Thiscovery Forms for administrators

This page is for people who enable the module, choose which form types are available, and decide who can create, fill, and see answers. Form creators have their own pages.

Thiscovery Forms lets teams build surveys, polls, feedback forms, projects, consensus rounds, and repeating-wave studies at **space** level or **network (global)** level. Rich text, thank-you pages, and email templates use **Thiscovery Editor**. Official EQ-5D instrument text is not included; the EQ-5D type is a layout only.

## Enable the module

1. Go to **Administration → Modules**.
2. Enable **Thiscovery Forms**.
3. Open **Configure** (or **Administration → Modules → Thiscovery Forms → Configure**).
4. On each space that should use forms, enable the module for that space (**Space → Modules**, or the space module list in Administration).

Without the space-level enable, members will not see Forms in that space even if they have permissions.

Network-level (global) forms live outside a space. People reach them from the Forms area in Administration or the global Forms list, depending on how your site is set up.

## Module configuration

**Administration → Modules → Thiscovery Forms → Configure**

### Form types

Tick the types people may **create**. Existing forms of a disabled type stay available; nobody can start a new one of that type until you tick it again.

Available types:

| Type | Typical use |
| --- | --- |
| Survey | Multi-page form with any question type |
| Quick poll | One question, often embedded on a page or stream |
| Feedback form | Short rating plus comments, ready to edit |
| EQ-5D survey | Five one-question pages plus a 0–100 thermometer; repeating waves |
| Longitudinal survey | The same panel answers repeating waves |
| Consensus / Delphi | Multi-round consensus with summaries between rounds |
| Project | Structured record with approval stages and a published catalogue |

If a type is missing on the Create screen, it is almost always switched off here.

### Waves

EQ-5D surveys and longitudinal surveys **always** use waves. Ordinary surveys only get a **Panel & waves** tab when both of the following are true:

1. **Allow waves on ordinary surveys** is on in this configuration, and
2. The survey itself has **Use waves** ticked on its Settings tab.

**Wave calendar** (wave scope):

- **Per survey** — each form opens and closes its own waves. Use this when studies run on different calendars.
- **Per panel** — the panel has one calendar. Every attached form uses the wave that is currently open on that panel. Invite emails are still sent from each form.

Changing wave scope affects how creators schedule fieldwork. Agree this before a study goes live.

## Permissions

Permissions are separate from “who can view answers” on an individual form. A person needs the right permission **and** the form must allow them in (status Open, anonymous or signed-in, and so on).

### Space permissions

**Space → Members → Permissions** (Thiscovery Forms group)

| Permission | What it allows | Default |
| --- | --- | --- |
| Create forms | Start new forms in this space | Owners, admins, moderators |
| Manage forms | Edit, delete, dashboards, CSV, panels attached to space forms | Owners, admins, moderators |
| Answer forms | Fill open forms they can reach | Members and above |
| View form answers | See submissions when the form’s **Who can view answers** is set to permission-based access | Owners, admins, moderators |

Guests cannot be given Create or Manage. Answer and View answers are also not granted to guests by default. Public or anonymous fill is controlled **on the form** (anonymous submissions + Open status), not by giving guests Manage.

Typical patterns:

- **Members fill, staff manage** — members: Answer forms. Moderators or a named group: Create and Manage.
- **Open research space** — members: Create and Answer. A smaller group: Manage and View form answers.
- **External respondents** — keep Answer forms for members; tick **Allow anonymous submissions** on the form and share the fill link. Do not grant guests Manage.

### Network (global) permissions

**Administration → Users → Groups → [group] → Permissions**

| Permission | What it allows |
| --- | --- |
| Create global forms | Create network-level forms |
| Manage global forms | Edit and administer those forms |
| Answer global forms | Fill global forms they can reach |
| View global form answers | See answers when the form uses permission-based visibility |

Give these only to groups that should run site-wide studies. Space permissions do not apply to global forms.

## Spaces versus network forms

| | Space form | Global form |
| --- | --- | --- |
| Where it lives | One space | The network |
| Who creates it | Create forms in that space | Create global forms |
| Audience | Space members, plus guests if the form allows | Anyone the form and global permissions allow |
| Menu | Optional **Show in space menu** on the form | Site navigation, if you add it |

Use a space when a community or project owns the work. Use a global form when the same instrument must appear once for the whole network.

## Dependencies and site settings

- **Thiscovery Editor** must be enabled. Form descriptions, thank-you pages, rich-text blocks, and email templates use it.
- **Email** must work (HumHub mailer / SMTP). Invites, reminders, resume codes, and action emails will otherwise fail silently from the respondent’s point of view.
- **Guests** must be allowed on the site if you want truly public fill links. HumHub’s guest access and the form’s **Allow anonymous submissions** both need to be on.
- File-upload questions use HumHub file storage. Check upload size limits if respondents attach large files.

## Folders

Creators can file forms in folders (Unfiled, or a folder they may use). Folder access follows who can create or manage in that container. Folders are for organisation; they do not replace permissions.

## What managers can delete

Deleting a form from the list or from the studio **permanently removes** the form and its responses. It is not a recycle-bin hide. Tell creators this before they use **Delete form**.

## Headerless fill pages

Creators can tick **Hide HumHub header** so respondents see only the form (no top bar or space menu). Opening those forms from inside HumHub does a full page load so the header actually hides. That is expected, not a broken theme.

## EQ-5D and licensing

The **EQ-5D survey** type supplies a five-page layout and a vertical 0–100 scale. It does **not** ship official EuroQol wording. Your organisation must hold the right licence and paste licensed text and copyright onto each page. CSV export can include a five-digit health profile and a VAS score (blank stored as 999) for teams that analyse licensed data themselves.

## Checklist before the first live study

1. Module enabled globally and on the relevant spaces.
2. Form types you actually use are ticked; unused types are off to keep Create simple.
3. Wave scope agreed (per survey vs per panel); waves on ordinary surveys on or off as needed.
4. Space and/or group permissions set.
5. Thiscovery Editor enabled; outbound email tested.
6. Guest access decided if public links are required.
7. At least one person with Manage can reach dashboards and CSV.

## Related pages

- [Getting started](creators-getting-started.md) (form creators)
- [Form types](creators-form-types.md) (form creators)
- [Panels, waves, and email](creators-panels.md) (form creators)
