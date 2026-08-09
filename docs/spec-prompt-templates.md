# Prompt Studio (`prompt-studio`)

## Functional Specification

Implementation status: verified against the application on 2026-08-02.

Related high-level project specification: `overview.md`

---

## 1. Purpose and Boundary

Prompt Templates prepares financial information for manual use with an LLM outside PF Visualiser.

The module shall:

* store reusable plain-text templates
* assemble deterministic financial data already stored in PF Visualiser
* allow situational context and questions to remain editable
* generate a preview that can be copied to the browser clipboard

The module shall not:

* call an LLM or AI API
* transmit financial data to an external service
* store API keys or provider credentials
* attempt to generate an AI response

---

## 2. Module Layout

Prompt Studio is a standalone module with two separate workspaces:

1. **Prompt Templates** for selecting, creating, editing, and deleting saved templates.
2. **Prompt Composer** for choosing a saved template and period, supplying optional balance overrides and free-form text, generating a preview, and copying it.

The two workspaces shall be selected using circular subsection icon buttons consistent with the other modules. They share one full-width module card rather than using a separate navigation sidebar. The active workspace's title and subtitle shall occupy the left side of the card header, with the subsection buttons right-aligned on the same row. The overall Prompt Studio page heading remains above the card.

Prompt Composer shall split its full-width content area into two equal columns on desktop: working controls on the left and generated output on the right. The columns stack responsively on narrower displays.

Prompt Templates shall likewise use two equal desktop columns: a card containing template selection, metadata, placeholders, and actions on the left; a separate card containing the template text editor on the right. These columns also stack on narrower displays.

Preparing a new unsaved template shall not change the template currently selected in Prompt Composer. Each configuration maintains its own template selection.

The page shall state that no data is sent outside the application.

The circular module-navigation entry shall appear immediately below Transportation and immediately above the light/dark theme toggle.

---

## 3. Template Management

Each template contains:

* name
* period preset: `weekly`, `monthly`, or `custom`
* plain-text body

The system shall support:

* create
* update
* delete with browser confirmation
* selection from the saved-template list

The initial migration creates two editable defaults:

* **Weekly financial review**
* **Month-end financial report**

The period preset controls which period input Prompt Composer displays:

* weekly: an ISO week picker; the selected week resolves to Monday through Sunday
* monthly: a month picker; the selected month resolves to its first and last calendar day
* custom: explicit start and end dates, initially set to the current day

Weekly and monthly templates do not ask the user to enter start and end dates. The composer displays the resolved inclusive date range beneath the active period input.

---

## 4. Template Placeholders

Template bodies support these placeholders:

| Placeholder | Generated value |
| --- | --- |
| `{{period_intro}}` | Statement indicating whether the period is ongoing, complete, or not yet started |
| `{{period}}` | Human-readable week, month, or custom date range |
| `{{start_date}}` | Start date in `D/M/YYYY` form |
| `{{end_date}}` | End date in `D/M/YYYY` form |
| `{{month}}` | Month and year containing the period end |
| `{{positions}}` | COH, ELR, EPF, derived LFP, and derived TFP |
| `{{positions_comparison}}` | End-month positions compared with the immediately preceding History month |
| `{{expense_total}}` | Period expense total with two decimal places |
| `{{expense_breakdown}}` | Non-BNPL expense category totals with optional subcategory totals, followed by a separate combined BNPL payment line when applicable |
| `{{income_total}}` | Period income total with two decimal places |
| `{{income_breakdown}}` | Income category totals with optional subcategory totals |
| `{{additional_context}}` | User-entered situational notes |
| `{{questions}}` | User-entered questions for the external LLM |

Whitespace around placeholder names is accepted. Unknown placeholders remain unchanged so template mistakes are visible rather than silently deleted.

Three or more consecutive line breaks in generated output are reduced to two. This avoids large gaps when optional context or questions are blank.

---

## 5. Composition Inputs

Required inputs:

* saved template
* selected week for a weekly template, selected month for a monthly template, or explicit range for a custom template

The frontend resolves every selection into an inclusive start and end date before composition. The backend contract continues to receive `start_date` and `end_date` regardless of preset.

Optional inputs:

* period status: automatic, ongoing, or complete
* COH override
* ELR override
* EPF override
* additional context
* questions

The end date must be on or after the start date. ELR and EPF overrides must be non-negative. COH may be negative.

Period status rules:

* `automatic`: use the current date to resolve `not_started`, `ongoing`, or `complete`
* `ongoing`: force “is still ongoing” wording regardless of the current date; weekly and custom introductions also include “Breakdown so far”
* `complete`: force “is over” wording regardless of the current date; weekly and custom introductions also introduce the final breakdown

Monthly report introductions use status-only sentences (`is over`, `is still ongoing`, or `has not started yet`) because the month-end template supplies its own report instructions.

LFP and TFP are always derived:

```text
LFP = COH + ELR
TFP = LFP + EPF
```

If one of the required source positions is unavailable, the corresponding derived position is reported as unavailable.

---

## 6. Transaction Summary Rules

The composer reads `transactions` whose `datetime` falls within the inclusive start/end range.

Expense and income summaries are calculated independently.

The expense total includes all expense transactions. Expense transactions flagged as BNPL are omitted from category and subcategory groups, combined into one amount, and reported as a separate BNPL line after the ordinary expense breakdown.

For each transaction type:

1. Group transactions by parent category and sum each category.
2. Sort category groups by total amount descending.
3. When a category contains formal subcategories, render the category total followed by subcategory totals sorted descending and indented with one literal tab character.
4. Preserve category-only transactions under a `No subcategory` component when mixed with subcategorised entries.
5. For categories without formal subcategories, render only the category total.
6. Do not include individual transaction amounts, purchase descriptions, dates, or notes in generated breakdowns.
7. Render `None recorded.` when the period contains no transactions of that type.
8. Exclude BNPL-flagged expenses from category/subcategory groups and combine their amounts into one separate BNPL line; retain them in the overall expense total.

---

## 7. Position Resolution

Position overrides have highest priority.

Without overrides:

* COH, ELR, and EPF all use the most recent History record at or before the period-end month
* the three values always come from the same History record
* Counter values are not used as position fallbacks

If no applicable History record exists, unavailable values are reported as such. Weekly balances should be overridden when a value more precise than the applicable monthly History record is required.

Month comparison uses only the History record for the calendar month immediately preceding the period-end month. If it is absent, the generated text states that no prior month-end comparison is available.

Comparisons cover:

* TFP
* LFP
* COH
* ELR
* EPF

---

## 8. Data Model

### 8.1 `prompt_templates`

| Field | Type |
| --- | --- |
| `id` | bigint |
| `name` | string(120) |
| `period_type` | enum: `weekly`, `monthly`, `custom` |
| `body` | text |
| `created_at` | timestamp |
| `updated_at` | timestamp |

Template names are not required to be unique.

---

## 9. Backend Contract

Current endpoints:

* `GET /prompt-studio`
* `POST /prompt-studio/templates`
* `PUT /prompt-studio/templates/{promptTemplate}`
* `DELETE /prompt-studio/templates/{promptTemplate}`
* `POST /prompt-studio/compose`

Template create/update fields:

* `name`
* `period_type`
* `body`

Compose request fields:

* `template_id`
* `start_date`
* `end_date`
* `period_status` (optional: `automatic`, `ongoing`, or `complete`)
* `closing_coh` (optional)
* `closing_elr` (optional)
* `closing_epf` (optional)
* `additional_context` (optional)
* `questions` (optional)

Compose response fields:

* `prompt`
* `period.label`
* `period.start_date`
* `period.end_date`
* `period.status`
* `totals.expenses`
* `totals.incomes`

---

## 10. Frontend Behavior

The frontend shall:

* load saved templates from the Prompt Studio page payload
* maintain independent template selections for Prompt Templates and Prompt Composer
* show a week picker for weekly templates and resolve Monday/Sunday boundaries
* show a month picker for monthly templates and resolve first/last-day boundaries
* show explicit start/end date inputs only for custom templates
* display the resolved inclusive date range
* allow automatic, ongoing, or complete period status selection
* adjust the composer period input when its selected saved template changes
* distinguish a new unsaved template from a selected stored template
* disable generation until a stored template is selected
* show Prompt Templates and Prompt Composer validation and operation status messages in separate dismissible toasts
* place generated output in a read-only preview
* enable Copy only when a generated prompt exists
* use the Clipboard API when available, with a selection-based fallback

Changing unsaved template text does not change the stored template used for generation until **Save template** is selected.

---

## 11. Known Initial-Iteration Limitations

This iteration intentionally does not include:

* historical weekly COH/ELR/EPF snapshots
* automatic Projection or Variance Analysis blocks
* follow-up templates specifically modeled on weekly revision or month-end question prompts
* prompt version history
* template import/export
* any AI provider integration

These may be added later without changing the local-only composition boundary.
