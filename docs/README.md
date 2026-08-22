# Thiscovery Forms

**Version 1.18.5**  
Copyright (c) 2026 D Cube Consulting. All rights reserved.

Lean HumHub module for creating forms at **space** and **network (global)** level.

**User documentation** (in-product **Help**, and paste into Wiki if needed): see [`docs/user/`](user/README.md) — pages for administrators and form creators.

## Features

- Field types: text, textarea, number, email, date, dropdown, radio, checkbox, file, rating, ranking, rich text, HTML, page breaks, grid, best–worst, MaxDiff, drill-down, image area
- Form kinds: survey, quick poll, feedback, EQ-5D, project, longitudinal, consensus
- EQ-5D: licence-safe five-page + thermometer layout, panel waves, profile/VAS export
- Waves: per survey or per panel (module setting); optional on ordinary surveys
- Project: configurable approval stages (users and/or groups), catalogue of published records, request-changes and decision log
- Longitudinal: hybrid panel (users or email invites), repeating waves, attrition on the dashboard
- Consensus / Delphi: rounds, published summaries, identity modes, comments, weighted votes
- Translation overlay and language switcher on the fill page
- Question / block library and save-as-template
- Import and export questions (JSON / CSV), with append or replace and a CSV field-type guide
- Quick polls embed on the stream and on Thiscovery pages
- Compound logic (AND/OR, skip question/page, go to page/end) and answer piping / carry-forward
- Per-form: multiple submissions, anonymous submissions, respondent edit after submit, save and resume, keep incomplete responses, public dashboard share, side-menu visibility, answers visibility
- Administrators can enable or disable form types in module settings
- Fill-page appearance: per-element CSS accordion (site theme by default) plus custom CSS
- Soft states: Draft → Open → Closed
- Custom thank-you content and CSS
- Stream card linking to the form
- CSV export and dashboards
- Notifications to author and managers on submit
- Permission-based create / manage / answer / view answers
- **Save & continue later** (optional per form) with a copyable / emailable resume code
- Preview / test link so stakeholders can try a form without polluting participant results
- Optional public dashboard share link (no sign-in)
- Incomplete responses can be kept for dashboard counts and CSV export

## Requirements

- HumHub **1.18+**
- PHP 8.1+
- [Thiscovery Editor](https://github.com/gauravsdcube/thiscovery-editor) (`thiscovery-editor`) for rich text and thank-you content

## Enable

1. Administration → Modules → Thiscovery Forms → Enable
2. Administration → Modules → Thiscovery Forms → Configure to choose which form types can be created
3. Enable the module on each Space that should use it
4. Configure permissions under Space → Members → Permissions (and Groups for global)

## Permissions

**Space:** Create forms, Manage forms, Answer forms, View form answers  
**Global (Groups):** Create / Manage / Answer / View global forms

## License

AGPL-3.0-or-later — see `COPYRIGHT`.
