# Create a survey from a brief or document

When an administrator turns on **Create from brief / document**, you can start a **Draft survey** from pasted research text or a Word / PDF questionnaire. You always review the proposed questions before anything is saved. The form is never opened or published automatically.

This option only creates **Survey** forms. Other types still use the normal Create cards.

## Before you start

1. An administrator must enable the feature under **Administration → Modules → Thiscovery Forms → Configure**.
2. You need **Create forms** (space) or **Create global forms** (network).
3. On Create, look for **From a brief** → **Brief or Word / PDF**. If that card is missing, the feature is off or Survey is disabled as a type.

## Steps

1. Open **Forms** → **Create**.
2. Choose **Brief or Word / PDF**.
3. Paste your brief and/or upload a `.docx`, `.pdf`, `.txt`, or `.md` file (size limit is set by the administrator).
4. Continue to the review screen. The product proposes question types, labels, and pages.
5. Optionally edit the brief and choose **Regenerate questions from brief**.
6. Set the **Draft title**, check the list, then **Create Draft survey**.
7. You land in the studio **Form builder**. Edit, reorder, and fix logic as usual. Publish only when you are ready.

## Rules vs AI

- **Rules** — used when AI assist is off, or when AI is unavailable. The mapper looks for numbered questions, choice lists, and simple page cues. Good for clear questionnaires; weaker on free-form research briefs.
- **AI** — when the administrator enables LLM assist and configures a provider key. Mapping can be richer. The review screen shows whether the last pass used **AI** or **Rules**.

Scanned PDFs with no selectable text cannot be read. Prefer Word, or paste the text yourself.

## Refine with AI chat

If AI assist is on, the review screen includes a chat panel.

1. Ask for a concrete change (e.g. “add a consent page”, “shorten to 10 questions”, “remove demographics”).
2. Check that the **Brief** text on the left updates.
3. Click **Regenerate questions from brief** (also shown under chat when the brief changes).

Chat alone does not rebuild the question list — regenerate does. Recent chat instructions are also passed into remapping.

Text you paste or upload may be sent to the configured AI provider when AI is on. Do not include passwords, personal identifiers you should not share, or confidential material your organisation forbids sending off-site.

Estimated token use and cost for the session appear on the review screen when AI is on. There is no hard spend limit in the product yet — figures are informational.

## After create

Treat the result like any Draft: fix skip logic, page titles, required flags, and options; use **Preview**; then publish an edition and set status to Open when ready. See [Getting started](creators-getting-started.md) and [Versions and publishing](creators-versioning.md).

You can still import or append questions later from **Settings → Share** (CSV or JSON). See [Import questions from CSV](creators-csv-import.md).
