# Thiscovery Forms

**Version 1.8.0**  
Copyright (c) 2026 D Cube Consulting. All rights reserved.

Lean HumHub module for creating forms at **space** and **network (global)** level.

## Features

- Field types: text, textarea, number, email, date, dropdown, radio, checkbox, file, rating, ranking, rich text, HTML, page breaks, grid, best–worst, MaxDiff, drill-down, image area
- Form kinds: survey, quick poll, feedback, project, longitudinal, consensus
- Project: configurable approval stages (users and/or groups), catalogue of published records, request-changes and decision log
- Longitudinal: hybrid panel (users or email invites), repeating waves, attrition on the dashboard
- Consensus / Delphi: rounds, published summaries, identity modes, comments, weighted votes
- Translation overlay and language switcher on the fill page
- Question / block library and save-as-template
- Import and export questions (JSON / CSV)
- Quick polls embed on the stream and on Thiscovery pages
- Compound logic (AND/OR, skip question/page, go to page/end) and answer piping / carry-forward
- Per-form: multiple submissions, anonymous submissions, respondent edit after submit, save and resume, side-menu visibility, answers visibility
- Administrators can enable or disable form types in module settings
- Fill-page appearance: per-element CSS accordion (site theme by default) plus custom CSS
- Soft states: Draft → Open → Closed
- Custom thank-you content and CSS
- Stream card linking to the form
- CSV export and dashboards
- Notifications to author and managers on submit
- Permission-based create / manage / answer / view answers
- **Save & continue later** (optional per form) with a copyable / emailable resume code (in-progress drafts are not counted as submissions)

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
