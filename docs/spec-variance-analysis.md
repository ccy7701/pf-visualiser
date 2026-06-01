# Variance Analysis Module (`variance-analysis`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`variance-analysis` is a scenario-linked plan-versus-actual module for monthly financial tracking.

The system compares saved projection outputs against user-entered month-end actual values for:

* Cash on Hand (COH)
* Emergency Liquidity Reserve (ELR)
* Employees Provident Fund (EPF)

---

## 2. Core Architectural Principle

Variance Analysis MUST be anchored to a saved projection scenario.

The following constraints apply:

* projected months come from scenario-local projection outputs
* actual values are stored separately from projection assumptions
* variance computation is deterministic from projected vs actual values
* actual entry is month-scoped and user-driven
* module does not mutate original projection assumptions

---

## 3. Functional Requirements

### 3.1 Scenario Selection

The system shall support loading a saved projection scenario for analysis.

Loaded scenario context includes:

* scenario metadata (`id`, `name`, `notes`, `updated_at`)
* projected month rows (baseline plan)
* persisted actual month rows (if any)

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

### 3.3 Actual Month Inputs

The system shall support user entry of actual month-end values:

* `closing_coh`
* `closing_elr`
* `closing_epf`

The system shall also support optional additional actual fields:

* `opening_coh`
* `net_income`
* `expenses`
* `debt_servicing`
* `notes`

All actual amount fields are nullable.

### 3.4 Expense Breakdown Inputs

The system shall support per-category actual expense entry via `expense_breakdown[]`:

* `category_id`
* `name`
* `amount`

Current UI category keys:

* `food`
* `groceries`
* `personal_care`
* `subscriptions`
* `household`
* `health`
* `apparel`
* `transportation`
* `entertainment`
* `prepaid_reload`
* `books_stationery`
* `others`

Actual `expenses` value is derived in UI as the sum of category amounts.

### 3.5 Monthly Comparison View

The system shall present month-by-month comparison rows:

* month label
* COH (actual vs projected)
* COH variance
* ELR (actual vs projected)
* ELR variance
* EPF (actual vs projected)
* EPF variance

### 3.6 Save Behavior

The system shall persist actual values per scenario and month.

Save operation rules:

* upsert by unique key (`scenario_id`, `month`)
* include only months with at least one meaningful actual value
* preserve nullable fields when not provided

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

### 4.2 Variance Display Rule

If actual value is absent (`null`), variance is displayed as `-`.

If actual value is present:

* positive delta is prefixed with `+`
* negative delta is shown with `-`
* zero delta is shown as `0.00`

### 4.3 Actual Expenses Derivation Rule

When expense breakdown is edited, month `expenses` is recalculated as:

```text
Actual Expenses = sum(expense_breakdown[].amount)
```

### 4.4 Projection Baseline Stability Rule

Variance analysis must not alter projected rows.

Projected values remain immutable baseline values sourced from saved scenario outputs.

---

## 5. Data Model Requirements

### 5.1 projection_actual_months

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

### 5.2 projection_scenarios linkage

Variance analysis is linked to `projection_scenarios` by `scenario_id`.

Scenario delete behavior:

* related `projection_actual_months` rows cascade delete

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

* `GET /variance-analysis`
* `GET /variance-analysis/scenarios/{scenario}`
* `POST /variance-analysis/scenarios/{scenario}/actuals`

### 6.2 Load Scenario Response

`GET /variance-analysis/scenarios/{scenario}` returns:

* `scenario`
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

### 6.3 Save Actuals Request

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
* load scenario with projected and persisted actual rows
* persist actual month values via upsert

### ProjectionService integration

Responsibilities in this module context:

* regenerate projected scenario results when cache is missing
* ensure variance baseline remains available for selected scenario

---

## 8. Interaction Flow

1. User opens Variance Analysis module.
2. User selects and loads a saved projection scenario.
3. System returns projected baseline rows plus any saved actual rows.
4. User selects a month row.
5. User enters actual balances and optional expense category values.
6. UI computes variance immediately against projected values.
7. User saves actual values.
8. Backend upserts rows into `projection_actual_months`.

---

## 9. Non-Goals

This module does not:

* edit projection assumptions directly
* re-run live projection on every actual input change
* infer actuals from transaction ledger automatically
* replace projection scenario management features
