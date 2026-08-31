# Variance Analysis Module (`variance-analysis`)

## Functional Specification

Implementation status: verified against the application on 2026-08-31.

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`variance-analysis` is a scenario-linked plan-versus-actual module for monthly financial tracking.

The system compares saved projection outputs against History-sourced month-end actual values for:

* Cash on Hand (COH)
* Emergency Liquidity Reserve (ELR)
* Employees Provident Fund (EPF)
* Total financial position (TFP), where `TFP = COH + ELR + EPF`

---

## 2. Core Architectural Principle

Variance Analysis MUST be anchored to a saved projection scenario.

The following constraints apply:

* projected months come from scenario-local projection outputs
* actual balances come from `history_months`
* actual income and expenses come from Transaction Log monthly aggregates with History overrides applied
* variance computation is deterministic from projected vs actual values
* actual entry is handled in the History module, not in Variance Analysis
* module does not mutate original projection assumptions

---

## 3. Functional Requirements

### 3.1 Scenario Selection

The system shall support loading a saved projection scenario for analysis.

Loaded scenario context includes:

* scenario metadata (`id`, `name`, `notes`, `updated_at`)
* projected month rows (baseline plan)
* History-backed actual month rows for projected months (if any)
* expense category metadata for rendering History breakdowns

### 3.2 Projected Baseline

For each projected month, the system shall expose:

* `month`
* `opening_coh`
* `net_income`
* `expenses`
* `debt_servicing`
* `closing_coh`
* `closing_elr`
* `closing_epf`

Projection values are read from scenario result cache; if missing, they are regenerated from scenario payload.

### 3.3 Actual Month Source

The system shall source actual month-end values from History:

* `closing_coh`
* `closing_elr`
* `closing_epf`

The system shall also expose derived or nullable actual fields:

* `opening_coh`
* `net_income`
* `expenses`
* `debt_servicing`
* `notes`

Actual income and expenses are effective resolved totals. Balance fields remain nullable when a projected month has transactions or overrides but no History balance record.

Variance Analysis shall render actual values as read-only values. Edits to actual values shall be made in the History module.

### 3.4 Expense Breakdown Source

The system shall source per-category actual expense values from the shared History activity resolver via `expense_breakdown[]`:

* `category_id`
* `name`
* `amount`

Expense categories use the same category catalog as the History module. Actual `expenses` is the effective expense total and actual `net_income` is the effective income total.

### 3.5 Monthly Comparison View

The system shall present month-by-month comparison rows:

* month label
* COH (actual vs projected)
* COH variance
* ELR (actual vs projected)
* ELR variance
* EPF (actual vs projected)
* EPF variance
* TFP (actual vs projected)
* TFP variance

Each metric value cell displays both the History-backed actual value and the saved projected value; its adjacent variance cell displays the signed delta.

### 3.6 Read-Only Actuals

The Variance Analysis UI shall not provide manual actual-value inputs or a save-actuals action.

Read-only actual panes include:

* COH / ELR / EPF values from History for the selected month
* TFP derived from History COH, ELR, and EPF values for the selected month
* expense category values from History for the selected month
* a source label indicating whether a History record exists for the selected month

---

## 4. Variance Computation Logic

### 4.1 Variance Formula

For each metric:

```text
Variance = Actual Value - Projected Value
```

Applied independently for:

* COH variance: `actual.closing_coh - projected.closing_coh`
* ELR variance: `actual.closing_elr - projected.closing_elr`
* EPF variance: `actual.closing_epf - projected.closing_epf`
* TFP variance: `actual TFP - projected TFP`, where `TFP = closing_coh + closing_elr + closing_epf`

### 4.2 Variance Display Rule

If actual value is absent (`null`), variance is displayed as `-`.

If actual value is present:

* positive delta is prefixed with `+`
* negative delta is shown with `-`
* zero delta is shown as `0.00`

### 4.3 Actual Expenses Derivation Rule

When History expense breakdown is loaded, month `expenses` is calculated as:

```text
Actual Expenses = sum(expense_breakdown[].amount)
```

### 4.4 Projection Baseline Stability Rule

Variance analysis must not alter projected rows.

Projected values remain immutable baseline values sourced from saved scenario outputs.

---

## 5. Data Model Requirements

### 5.1 Actual Sources

Variance Analysis combines:

* `history_months` for closing COH, ELR, and EPF
* `transactions` for monthly parent-category income and expense aggregates
* `history_category_overrides` for sparse effective-amount replacements

### 5.2 projection_actual_months Legacy Table

| Field                 | Type          |
| --------------------- | ------------- |
| id                    | bigint        |
| scenario_id           | foreign key   |
| month                 | string(7)     |
| opening_coh           | nullable decimal(12,2) |
| net_income            | nullable decimal(12,2) |
| expenses              | nullable decimal(12,2) |
| debt_servicing        | nullable decimal(12,2) |
| closing_coh           | nullable decimal(12,2) |
| closing_elr           | nullable decimal(12,2) |
| closing_epf           | nullable decimal(12,2) |
| expense_breakdown_json| nullable json |
| notes                 | nullable text |
| created_at            | timestamp     |
| updated_at            | timestamp     |

Unique constraint:

* (`scenario_id`, `month`)

The table may still exist for legacy compatibility, but current UI actuals are sourced from History.

### 5.3 projection_scenarios linkage

Variance analysis is linked to `projection_scenarios` by `scenario_id`.

Scenario delete behavior:

* related `projection_actual_months` rows cascade delete

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

* `GET /variance-analysis`
* `GET /variance-analysis/scenarios/{scenario}`
* `POST /variance-analysis/scenarios/{scenario}/actuals` (legacy compatibility)

### 6.2 Load Scenario Response

`GET /variance-analysis/scenarios/{scenario}` returns:

* `scenario`
* `expense_categories[]`
* `history_months[]`
* `projected_months[]`
* `actual_months[]`

`projected_months[]` fields:

* `month`
* `opening_coh`
* `net_income`
* `expenses`
* `debt_servicing`
* `closing_coh`
* `closing_elr`
* `closing_epf`

`actual_months[]` fields:

* `month`
* `opening_coh`
* `net_income`
* `expenses`
* `debt_servicing`
* `closing_coh`
* `closing_elr`
* `closing_epf`
* `expense_breakdown[]`
* `notes`
* `has_balance_record`
* `has_transactions`
* `has_overrides`

`history_months[]` fields:

* `month`
* `expense_breakdown[]`
* `income_breakdown[]`
* source-presence flags

### 6.3 Save Actuals Request

Current UI does not call this endpoint. It writes `projection_actual_months` for legacy compatibility, but those rows are not read by the current Variance Analysis UI.

`POST /variance-analysis/scenarios/{scenario}/actuals` request body:

* `actuals[]`
* `actuals[].month` (`YYYY-MM`)
* nullable numeric fields for month metrics
* optional `actuals[].expense_breakdown[]`
* optional `actuals[].notes`

Validation rules in current implementation:

* month regex: `^\d{4}-\d{2}$`
* `closing_elr >= 0` when provided
* `closing_epf >= 0` when provided
* `expense_breakdown[].amount >= 0`

### 6.4 Save Actuals Response

Success response:

* `message` (`Actual values saved successfully.`)

---

## 7. Service Structure (Laravel)

### VarianceAnalysisController

Responsibilities:

* render variance analysis page with saved scenarios
* load scenario with projected rows and resolved actual rows
* expose expense categories and effective expense breakdowns for read-only rendering
* retain legacy actual upsert endpoint where present

### ProjectionService integration

Responsibilities in this module context:

* regenerate projected scenario results when cache is missing
* ensure variance baseline remains available for selected scenario

---

## 8. Interaction Flow

1. User opens Variance Analysis module.
2. User selects and loads a saved projection scenario.
3. System returns projected baseline rows plus resolved actual rows.
4. User selects a month row.
5. UI displays read-only balances and effective transaction-derived activity.
6. UI computes variance immediately against projected values.
7. User edits actual values in History if the History record needs correction.

---

## 9. Non-Goals

This module does not:

* edit projection assumptions directly
* re-run live projection on every actual input change
* edit or save actual values directly in Variance Analysis
* replace projection scenario management features
