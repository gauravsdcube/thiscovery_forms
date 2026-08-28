# Response integrity

The **Response integrity** tab (and the matching defaults under **Administration → Modules → Thiscovery Forms**) score how a submission looks for review **when Enable integrity checks is ticked**. They do **not** prove fraud, misconduct, or that a person is not genuine, and they do **not** delete answers. If the checkbox is off, no quality scores or integrity metadata are stored.

Use the **?** next to each setting in the studio for a short explanation. This page is the longer guide, including what you can tell clients and ethics committees.

## Principle

Several signals are combined into one quality score. A single issue — a fast completion, a shared IP address, straight-lining, one failed attention check, or similar wording — must **not** by itself classify a genuine response as fraudulent.

In the product that is enforced: **one signal type can at most produce “Review required”**. **Suspicious** and **automatic exclusion** need at least two different signal categories. Status is a suggestion. Managers can override it. Excluded responses stay in the database and can be reinstated. A written reason is required when a manager excludes a response.

## Where to configure it

| Place | What it controls |
| --- | --- |
| **Administration → Modules → Thiscovery Forms** | Site-wide defaults. Cloudflare Turnstile keys are only set here. |
| Studio **Response integrity** tab | Overrides for this survey. **Use site default** inherits the administration value. |
| Builder field card | **Attention check** and the expected answer on an instructed-response question |

Preview / test fills are not scored for integrity and do not appear on the integrity dashboard.

## Access mode

Who may open the fill page, and how duplicate checks interpret identity:

| Mode | Who can take part |
| --- | --- |
| Public / open link | Anyone with the share URL (still subject to Open status and anonymous settings) |
| Unique invitation link | The URL must include `?access=` from a generated invitation. Panel `?token=` invites still work where you already use a panel. |
| Signed-in account with an email address | Signed-in HumHub user whose account has an email field. This is **not** a per-survey email proof or inbox confirmation. |
| Logged-in user only | Any signed-in user |
| Restricted to space members | Signed-in members of this space (space forms only) |

Public and unique-invite modes turn **Allow anonymous submissions** on when you save that mode. Email / logged-in / restricted turn it off.

**Unique invitation links:** after you save the form, use **Manage unique invitation links**. Generate a batch as one-time or multi-use. Download the CSV immediately — plaintext tokens are shown only then. The database stores a hash and a short hint, not the raw token. Reuse of the same token on another complete response is flagged as a duplicate.

## Features (on / off)

Leave a survey control on **Use site default** unless this study needs different behaviour.

| Setting | What it does |
| --- | --- |
| Enable integrity checks | **Checkbox.** Scores and integrity metadata are stored only when this is on. Off by default. Per-survey can inherit the site default or override it. |
| Bot protection | Records honeypot, missing browser session, and related signs as flags |
| Rate limiting | Refuses further submits from the same hashed IP/session in the window (a **gate**, not a quality flag). Shared NAT can lock several people out |
| CAPTCHA / Turnstile | Optional Cloudflare widget. Needs keys in Administration |
| Duplicate detection | Same user, invitation token, or hashed IP/session. Hash IP Off skips IP matching only |
| Speed detection | Faster than your floor seconds or faster than typical time. Browser timings are supporting signals and can be spoofed |
| Straight-lining detection | Identical grids, flat ratings, sequences such as 1,2,3,4,5 |
| Attention checks | Scores Builder questions marked as attention checks. Match is exact (code or label), not a substring |
| Logical consistency checks | Your IF/AND rules on this tab |
| Free-text quality checks | Short, empty, repeated, question-echoing, or low letter-variety text. The product does **not** detect AI writing |
| Similar-response detection | Compares this submission with others on the same survey (last 250 completes) |
| Integrity scoring | Builds the overall score and suggested status |
| Record time on individual questions | Extra per-question timings. Page times are stored anyway |
| Hash IP addresses | **On:** HMAC of IP plus a network hash, never the raw address. **Off:** store no IP hashes |
| Automatic exclusion when several signals agree | **Off** by default. When On, exclusion still requires a very low score **and** at least two different signal types |

**CAPTCHA when:** Off, only when behaviour looks suspicious (honeypot, no session, or rate limit), or Always (every submit, if keys are set). Failed Always-mode CAPTCHA blocks submit. Other CAPTCHA failures are recorded as flags.

**Rate window:** for example 8 submits per 10 minutes from the same connection.

## Score, status, and analysis state

Each complete response starts at **100**. Category weights (bot, duplicate, speed, and so on) subtract points. Weights are capped at 40 so one slider cannot dominate the score. One indicator uses only part of that category’s weight.

| Integrity status | Typical meaning |
| --- | --- |
| Trusted | Score at or above the Trusted threshold |
| Review required | Below Trusted, or a single signal type fired |
| Suspicious | Below the Review threshold **and** at least two signal types fired |
| Excluded | Manual override with a reason, or automatic exclusion if you enabled it and several signals agree |

**Analysis state** (how the response is treated in analysis and default CSV):

| State | Meaning |
| --- | --- |
| Included | Use in analysis |
| Review required | Keep in the file; inspect before treating as clean |
| Quarantined | Held out of the default trusted set; not deleted |
| Excluded | Left out of default export and analysis; original answers remain |

Managers change status and analysis state on the answer review. A reason is **required** when excluding. The audit log records who changed what. A later resubmit refreshes scores and flags but does **not** overwrite a human override.

## Review screen

Open **Answers**. The list shows **Score**, **Integrity**, and **Analysis** (Included / Review / Excluded) on every row. Click a response: the score and include-in-analysis decision sit **at the top**, then the answers, then full integrity detail.

On an individual response you can:

- See the quality score (0–100), suggested integrity status, and current analysis state
- See which questions were flagged (attention, free text, and similar)
- Choose **Include in analysis**, **Hold for review**, **Quarantine**, or **Exclude from analysis**
- Give a reason when excluding (required). The record is kept and can be reinstated

Managers and people with **View Answers** permission can save that decision. People who only see answers because they submitted the form cannot change analysis.

The detail below the answers still shows component scores, timings, similar IDs, notes, and audit history.

Bot, honeypot, session, CAPTCHA, and hashed-IP duplicate detail are **managers only**. CSV export omits those technical flags so a spreadsheet sent to a client does not include them. Technical hashes are never shown on screen.

## Integrity dashboard

From **Answers** or the form dashboard, open **Response integrity**. Totals for Trusted, Review, Suspicious, Excluded, and each flag type are **clickable** — they open Answers already filtered.

## Answers list and export

Filter Answers by integrity status, flag type, or minimum score. Click **Score** or **Analysis** in the table header to sort.

CSV export adds quality score, integrity status, analysis status, and a flags column. **Excluded** rows are omitted unless you choose **Export including excluded**. You can also export only Trusted responses, or only scores above a threshold, using the same filters as the list.

## Attention checks on the Builder

1. Add a normal choice or rating question.
2. Tick **Attention check**.
3. Type the expected answer (for example `Agree`).
4. Keep **Attention checks** on under Response integrity.

Write the instruction in the question text (“Please select Agree”). Pass and fail are both recorded. Failed checks contribute to the score; they do not auto-reject.

## Consistency rules

Each rule needs **at least two** conditions (question + equals / does not equal / contains + value). The response is flagged only when **all** conditions match.

Example: Q1 equals “Never used the service” **and** Q10 equals “I use the service every day”.

Give a short **rule id** (stored on the flag) and a **label** reviewers will understand. Add as many rules as the survey needs.

## Privacy

Quality metadata is stored in separate tables from answer values. When Hash IP is On, IPs used for duplicate/rate checks are HMAC-hashed (plus a network hash); the raw address is never stored. When Hash IP is Off, no IP hashes are stored and IP duplicate matching is skipped. Do not put extra personal data into review notes unless your protocol allows it.

## Ethics and limits (for clients and protocols)

You **can** tell clients and ethics committees:

- Quality scores are **screening aids**. They support human review; they are not a determination of fraud or that a respondent is not genuine.
- Answers are **never auto-deleted**. Exclude keeps the record and can be reversed.
- **One signal cannot** mark a response Suspicious or Excluded.
- The product does **not** claim text was AI-generated.
- Raw IP addresses are **not stored** for these checks.
- Status changes and exclusions are **audited**. Exclusion needs a written reason.

You **must not** claim:

- That a low score proves bots, fraud, collusion, or inattention as a legal or disciplinary finding.
- That “signed-in with an email address” proves a verified mailbox for this survey.
- That completion times are exact (they are browser-reported and can be incomplete or altered).
- That similar wording proves copying, or that low letter-variety proves gibberish in every language.
- That a shared IP/VPN/hospital network is one person.

Operational limits to mention in a protocol if relevant: rate limiting can block several genuine people behind one NAT; uniqueness of invitation links depends on not forwarding the URL; similarity only compares the last 250 complete responses.

## Related pages

- [Form settings](creators-settings.md)
- [Sharing and results](creators-results.md)
- [Getting started](creators-getting-started.md)
- [Thiscovery Forms for administrators](admins.md)
