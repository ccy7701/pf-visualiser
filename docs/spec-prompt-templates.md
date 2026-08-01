# Prompt Templates (`settings/prompt-templates`)

## Functional Specification

Implementation status: initial iteration verified against the application on 2026-08-01.

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

## 2. Settings Layout

The Settings page shall include a **Prompt Templates** configuration tab.

The tab contains two sections:

1. **Template** for selecting, creating, editing, and deleting saved templates.
2. **Compose prompt** for selecting a date range, supplying optional balance overrides and free-form text, generating a preview, and copying it.

The page shall state that no data is sent outside the application.

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

The period preset controls the initial date range offered by the browser:

* weekly: Monday through Sunday of the current week
* monthly: first through last calendar day of the current month
* custom: current day as both start and end until edited

---

## 4. Template Placeholders

Template bodies support these placeholders:

| Placeholder | Generated value |
| --- | --- |
| `{{period_intro}}` | Context-aware statement indicating whether the period is past, current, or future |
| `{{period}}` | Human-readable week, month, or custom date range |
| `{{start_date}}` | Start date in `D/M/YYYY` form |
| `{{end_date}}` | End date in `D/M/YYYY` form |
| `{{month}}` | Month and year containing the period end |
| `{{positions}}` | COH, ELR, EPF, derived LFP, and derived TFP |
| `{{positions_comparison}}` | End-month positions compared with the immediately preceding History month |
| `{{expense_total}}` | Period expense total with two decimal places |
| `{{expense_breakdown}}` | Expense categories and transaction details |
| `{{income_total}}` | Period income total with two decimal places |
| `{{income_breakdown}}` | Income categories and transaction details |
| `{{additional_context}}` | User-entered situational notes |
| `{{questions}}` | User-entered questions for the external LLM |

Whitespace around placeholder names is accepted. Unknown placeholders remain unchanged so template mistakes are visible rather than silently deleted.

Three or more consecutive line breaks in generated output are reduced to two. This avoids large gaps when optional context or questions are blank.

---

## 5. Composition Inputs

Required inputs:

* saved template
* inclusive start date
* inclusive end date

Optional inputs:

* COH override
* ELR override
* EPF override
* additional context
* questions

The end date must be on or after the start date. ELR and EPF overrides must be non-negative. COH may be negative.

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

For each transaction type:

1. Group transactions by category.
2. Sum each category.
3. Sort category groups by total amount descending.
4. Show a single transaction on one line, appending its note when present.
5. For multiple transactions in one category, show the category total followed by each transaction amount and note.
6. Use the transaction date as fallback detail when a grouped transaction has no note.
7. Render `None recorded.` when the period contains no transactions of that type.

The composer does not infer formal subcategories. Detail such as Food → Dinner or Transportation → Fuel is available only when represented by transaction notes or other manually supplied context.

---

## 7. Position Resolution

Position overrides have highest priority.

Without overrides:

* for a period ending today or later, COH uses the Counter snapshot's `actual_counter`
* otherwise, COH uses the most recent History record at or before the period-end month
* ELR and EPF use the most recent History record at or before the period-end month

This fallback does not imply that PF Visualiser stores historical weekly balance snapshots. Weekly balances should be overridden when an exact historical position is required.

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

* `POST /settings/prompt-templates`
* `PUT /settings/prompt-templates/{promptTemplate}`
* `DELETE /settings/prompt-templates/{promptTemplate}`
* `POST /settings/prompt-templates/compose`

Template create/update fields:

* `name`
* `period_type`
* `body`

Compose request fields:

* `template_id`
* `start_date`
* `end_date`
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
* `totals.expenses`
* `totals.incomes`

---

## 10. Frontend Behavior

The frontend shall:

* load saved templates from the Settings page payload
* adjust the date range when the selected period preset changes
* distinguish a new unsaved template from a selected stored template
* disable generation until a stored template is selected
* show validation and operation status messages
* place generated output in a read-only preview
* enable Copy only when a generated prompt exists
* use the Clipboard API when available, with a selection-based fallback

Changing unsaved template text does not change the stored template used for generation until **Save template** is selected.

---

## 11. Known Initial-Iteration Limitations

This iteration intentionally does not include:

* formal transaction subcategories
* historical weekly COH/ELR/EPF snapshots
* automatic Projection or Variance Analysis blocks
* follow-up templates specifically modeled on weekly revision or month-end question prompts
* prompt version history
* template import/export
* any AI provider integration

These may be added later without changing the local-only composition boundary.
