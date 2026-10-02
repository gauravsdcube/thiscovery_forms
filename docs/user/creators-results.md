# Sharing and results

How people open the form, how you watch progress, and how you export data.

## Settings → Share

| Link | Use |
| --- | --- |
| Fill / share link | Real respondents. Works when status is **Open** and they are allowed to take part. For a public audience, tick **Allow anonymous submissions** as well. |
| Preview / test link | Stakeholders try the form. Answers are **not** counted as participant submissions. |
| Dashboard share | Aggregate results without sign-in, only if **Share dashboard without sign-in** is enabled on **Settings → Sharing and display** and you have saved. You can regenerate the link if it has leaked. |

**Publish current draft** freezes a published edition when Thiscovery Versioning is on (same action as **Settings → Versions**).

**Save as template** stores the form design for reuse. It does not include answers. Templates are labelled by form type on the Create screen.

**Import and export questions** (same section) accepts JSON or CSV. Leave **Replace all existing questions** unticked to append, or tick it to overwrite the form. Column names and every field type are described in [Import questions from CSV](creators-csv-import.md).

## Opening from the site

- **Forms list** — Open, Edit (managers), dashboard.
- **Space menu** — if **Show in side menu** is on and the form is Open.
- **Stream card** — a wall entry can link to the form.
- **Quick poll** — can be embedded on the stream and on Thiscovery pages.

Headerless forms still open from these places; the fill page simply omits the HumHub header (**Run without HumHub header**).

## Who can see answers

This is the **Who can view answers** setting, plus **View form answers** permission when you use permission-based access. People with **Manage** can always work with results for forms they manage.

Respondents do not automatically see other people’s answers unless you chose **Managers and respondents** or permission-based access that includes them.

## Dashboard

The form dashboard shows totals for **complete** responses. Test / preview answers are excluded.

If **Keep incomplete responses** is on, the dashboard also shows an in-progress count. Use that to see drop-off, not as a substitute for completed n.

Longitudinal and wave forms can show attrition across waves. Consensus dashboards follow rounds. Project catalogues show records that have passed approval, not every draft submission.

Polls can show a simple result chart where you have enabled poll results.

## CSV export

On the Answers export, choose **Export headers**:

- **Participant labels** — question text (default)
- **Variable names** — the field variable ids
- **Variable and label** — both

Which columns appear, and whether personal data is scrubbed, is set once under **Settings → Export**. Every **Export CSV** button (Answers, Dashboard, Response integrity) uses that set.

**Scrub PII** (off by default) omits identity and PII-tagged columns and replaces emails, phone numbers, and IP addresses in remaining cells with `[redacted]`. The file is named `*-scrubbed.csv`. Answers in the database are unchanged.

CSV export adds quality score, integrity status, analysis status, and flags when Response integrity is in use. Incomplete rows are included only when you kept incomplete responses. Preview answers are not included. Excluded responses are omitted from CSV unless you choose **Export including excluded**. Filter the Answers list (Trusted only, minimum score, flag type) before export if you want a subset.

Files uploaded on the form are not the CSV itself; they are stored as HumHub files and referenced from the response. If a file question is tagged as personal data and scrubbing is on, the file **name** column is omitted.

## Notifications

The form author and managers can be notified when someone submits. Check that site email works. For custom messages, use **Send email** actions and templates instead of relying only on the default notification.

## After fieldwork

1. Set status to **Closed** so new fills stop.
2. Export CSV and keep a copy according to your data policy.
3. If the public dashboard link was used, regenerate or disable public dashboard share if it should not stay live.
4. Delete the form only when you are sure you no longer need the data — deletion is permanent.

## Related pages

- [Getting started](creators-getting-started.md)
- [Form settings](creators-settings.md)
- [Versions and publishing](creators-versioning.md)
- [Response integrity](creators-response-integrity.md)
- [Import questions from CSV](creators-csv-import.md)
- [Form types](creators-form-types.md)

## Notes for analysts

- **Time stamps** in the answers CSV (submitted, updated) are ISO 8601 in the form's time zone (Settings), with the offset, for example `2026-10-01T14:03:00+01:00`.
- **Choice percentages** on the dashboard use people who answered as the base. On multiple-choice questions they can add up to more than 100%.
- **Delphi round summaries** show whole percentages that add up to 100.
- **Number questions** show n, mean, median, quartiles and range on the dashboard.
- **Response rates** for waves use the members invited to that wave. Drop-off follows the same people from one wave to the next.
- **EQ-5D:** the export gives the five-digit profile (`9` marks a missing dimension) and the VAS (0–100, rounded; `999` if missing). No EQ-5D index (utility) value is calculated, because value sets are country-specific and licensed. Apply the value set for your country in your analysis software.
- **Excluded responses** are left out of exports unless you choose to include them. The integrity status columns show quarantined responses.
