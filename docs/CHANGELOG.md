# Changelog

All notable changes to this module are documented in this file.

## 1.8.0 (August 15, 2026)

- Enh: Module settings page to enable or disable form types (Administration → Modules → Thiscovery Forms → Configure)
- Enh: CSS tab accordion for page, card, questions, buttons, and each question type, plus a custom CSS box; blank values keep the site theme
- Enh: Dropdown, radio, and checkbox options named Other show a text box so respondents can type their own answer

## 1.7.0 (August 15, 2026)

- Enh: Save and resume is now a per-form setting on every form type; it is off unless enabled
- Enh: When resume is on, opening the form asks whether to continue a saved response or start a new one (anonymous respondents always see this choice)
- Enh: When multiple submissions are not allowed and the person has already submitted, a configurable message is shown instead of the resume choice

## 1.6.0 (August 15, 2026)

- Enh: **Project** form kind — structured records with a published catalogue
- Enh: Configurable approval stages; each stage can assign users and/or groups, with any-one or all-must-approve
- Enh: Submit for review, request changes, publish, archive, and a decision log on each record

## 1.5.1 (August 15, 2026)

- Enh: Rich text blocks, thank-you messages, and consensus round summaries use Thiscovery Editor instead of HumHub markup

## 1.5.0 (August 15, 2026)

- Git release of form kinds and studio (library, templates, question import/export), compound logic and research types, longitudinal and consensus programmes, and translation overlay with export/import (1.2.0–1.4.3)

## 1.4.3 (August 14, 2026)

- Enh: Export all source questions for translation (CSV or JSON) and import overlays back in multiple languages from the Translations tab

## 1.4.2 (August 14, 2026)

- Enh: Sample JSON and CSV question files can be downloaded from the Share tab
- Fix: CSV question import keeps options that span more than one line

## 1.4.1 (August 14, 2026)

- Enh: Email panel members automatically when a later wave opens (Wave 2 onwards), including scheduled start times

## 1.4.0 (August 14, 2026)

- Enh: Longitudinal surveys — hybrid panel (signed-in users or email tokens), waves on the same form, invite emails, wave completion and drop-off on the dashboard
- Enh: Consensus / Delphi — rounds, published summaries, identity modes, optional/required comments, weighted votes, freeze items that reach a threshold
- Enh: Translation overlay with a language switcher on the fill page (source language plus extra locales such as Welsh)

## 1.3.0 (August 14, 2026)

- Enh: Compound logic (AND/OR) with show, hide, skip question, skip page, go to page, and go to end
- Enh: Answer piping on labels, help text, page titles, rich text, and HTML; carry-forward choices from earlier questions
- Enh: Research question types — grid (single/multi), best–worst, MaxDiff, drill-down, and image area select/evaluate
- Enh: Dashboards and CSV export cover the new structured answer types

## 1.2.0 (August 14, 2026)

- Enh: Form kinds — Survey, Quick poll, Feedback, Longitudinal, Consensus — chosen on create
- Enh: Quick polls are a one-question form, vote inline on the stream, and embed on engagement pages
- Enh: Question and block library, save a form as a template, create from a template
- Enh: Import and export questions as JSON or CSV

## 1.1.0 (August 13, 2026)

- Enh: Per-form setting to allow or prevent respondents editing a completed submission (managers can still update answers)
- Enh: Checkbox fields support a maximum number of selections and exclusive options (for example "None of these")
- Enh: Text fields can be pre-filled from a user profile attribute
- Enh: Fill page shows the form title and description
- Enh: Guests opening a signed-in form are redirected to login
- Fix: Completing a question no longer scrolls the fill page back to the top
- Fix: Builder field order is preserved (posted sort order is no longer rewritten by PHP numeric array keys)

## 1.0.0 (August 13, 2026)

- Initial release of Thiscovery Forms for space and network-level forms, including multi-page surveys, anonymous fill, save-and-resume, dashboards, and CSV export
