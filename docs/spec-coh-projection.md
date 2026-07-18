# Projection Module (`coh-projection`)

## Functional Specification

Implementation status: verified against the application on 2026-07-18.

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`coh-projection` is a deterministic scenario engine that projects month-by-month balances across a configured month range.

The system projects:

* Cash on Hand (COH)
* Emergency Liquidity Reserve (ELR)
* Employees Provident Fund (EPF)

---

## 2. Core Architectural Principle

The projection module MUST operate as a deterministic backend computation engine.

Given identical inputs, the engine must produce identical outputs.

The following constraints apply:

* no hidden UI-side assumptions
* no frontend-side financial computations
* all projection assumptions must be inside payload
* projection must not derive assumptions from live counter transactions
* projection must not derive unpaid salary accrual or salary receipt reconciliation state from the live Counter module

---

## 3. Functional Requirements

### 3.1 Scenario Configuration

The system shall support:

* projection start month
* projection end month
* starting COH, supplied explicitly as the opening cash position for the first projected month
* starting ELR
* starting EPF

### 3.2 Employment Configuration

The system shall support:

* one or more salary schedules
* schedule start month
* optional schedule end month
* schedule monthly gross salary
* optional schedule-specific employee EPF rate percent
* optional schedule-specific employer EPF rate percent
* schedule note
* salary paid-in-arrears toggle
* opt-in SOCSO L24 toggle, effective from June 2026

Employment settings are scenario-local.

### 3.3 Budget Profiles Configuration

The system shall support:

* separately navigable Budget Profiles projection input section
* create, save, edit, and delete budget profiles
* arbitrary budget profile keys, including but not limited to legacy `bcol`, `fcol_lite`, and `fcol_max`
* per-profile display name
* per-profile `category_allocations[]`
* Budget Plans Added card list using the same card/action pattern as Salary Schedules Added

### 3.4 Monthly Budget Selection Configuration

The system shall support:

* separately navigable Monthly Budget Selection projection input section
* generated month rows for the configured projection month range
* month-level selection of which saved budget profile to use

Cost inputs are explicit projection inputs only.

### 3.5 PTPTN Configuration

The system shall support:

* waiver granted flag
* monthly repayment amount
* repayment start month

### 3.6 BNPL Configuration

The system shall support:

* multiple monthly entries
* month
* amount
* note

### 3.7 Events Configuration

The system shall support monthly events with:

* month
* type
* amount
* note

Supported event `type` values:

* `allowance`
* `household`
* `one_off_income`
* `one_off_expense`
* `elr_override`

### 3.8 ELR Configuration

The system shall support:

* ELR schedules (`start_month`, `end_month`, daily `amount`)
* ELR note
* compound interest toggle
* annual interest rate percent

### 3.9 EPF Configuration

The system shall support:

* employee EPF rate percent
* employer EPF rate percent

### 3.10 Projection Output

The system shall return monthly rows containing core balances and breakdown fields, including:

* opening/closing COH
* opening/closing ELR
* opening/closing EPF
* gross/net income
* expenses and debt servicing
* ELR contribution and ELR interest
* statutory deductions (`socso`, `socso_l24`, `eis`)

`closing_coh` may be negative.

The system shall also return metadata fields including:

* `start_month`
* `end_month`
* `months_count`
* `salary_paid_in_arrears`
* `socso_l24_enabled`
* `ptptn_waiver_granted`

### 3.11 Saved Scenarios and Comparison

The Projection page supports saving, updating, loading, deleting, and comparing saved scenarios.

Scenario comparison rules:

* exactly two scenarios are selected by the current UI
* the table uses the chronological union of both scenarios' month ranges
* each month is split into COH, ELR, EPF, and TFP groups with one value column per scenario
* for shared months, the greater value in each pair is highlighted blue
* hovering or keyboard-focusing the highlighted cell shows the absolute RM advantage
* ties are neutral
* months present in only one scenario are greyed out, the missing side is shown as `—`, and no winner is highlighted

---

## 4. Projection Computation Logic

### 4.1 Chronological Processing Rule

Months are processed in ascending month order from `start_month` to `end_month`.

If `end_month` is before `start_month`, month sequence is empty.

Each month inherits opening balances from the previous month's closing balances.

### 4.2 Monthly COH Formula

```text
Closing COH
=
Opening COH
+ Net Salary Received In Projection Month
+ Allowances
+ One-Off Income
- Living Expenses
- BNPL Repayments
- PTPTN Repayments
- One-Off Expenses
- ELR Contribution
```

### 4.3 Monthly ELR Formula

When compound interest is disabled:

```text
Closing ELR = Opening ELR + ELR Contribution
```

When compound interest is enabled:

For each day in month:

```text
balance = balance + (balance * annual_rate/100/365)
balance = balance + daily_contribution
```

Where:

* `daily_contribution = monthly_elr_contribution / days_in_month`
* monthly ELR contribution is resolved from schedule (or legacy daily/monthly inputs if provided)

Monthly ELR interest is:

```text
ELR Interest = Closing ELR - Opening ELR - ELR Contribution
```

### 4.4 Monthly EPF Formula

```text
Closing EPF
=
Opening EPF
+ Employee EPF Contribution
+ Employer EPF Contribution
```

### 4.5 Salary Progression Logic

Salary is resolved from `employment.salary_schedules`.

Schedules are normalized in ascending start-month order. For each month, the matching schedule is the first normalized schedule whose `start_month` is on or before the target month and whose optional `end_month` is either blank or on or after the target month.

Schedules should be configured as non-overlapping ranges. If overlapping ranges are supplied, the first matching normalized schedule is used.

If no schedule matches a month, gross salary is 0.

Schedule-specific EPF rates override the scenario-level EPF rates for months resolved to that schedule. If a schedule EPF rate is blank, the scenario-level rate is used.

### 4.6 Salary Arrears Rule

If `salary_paid_in_arrears = true`, salary is shifted by exactly one full month.

For a projection month, salary is resolved from the previous work month. For example, a July projection row receives June salary, and an August projection row receives July salary.

If `salary_paid_in_arrears = false`, salary is resolved from the same month as the projection row.

No partial first-month proration is applied.

Salary arrears in this module is a scenario-planning rule only. It does not consume live `Income:Salary` transactions, does not allocate salary receipts against unpaid accrual, and does not model workday-by-workday unpaid salary accrual. Those behaviors belong to the live Counter module.

### 4.7 PTPTN Logic

If waiver granted, PTPTN repayment is 0.

If waiver is not granted and repayment start month is reached, repayment uses configured monthly amount.

### 4.8 BNPL Logic

BNPL repayment for a month is the sum of BNPL entries matching that month.

### 4.9 Cost of Living Budget Selection Rule

Monthly living expense is derived from the selected budget profile for that month.

`monthly_budget_selection[].budget` stores the selected budget profile key.

If no monthly selection is provided, or if a stale selected profile key no longer exists, the first available saved budget profile is used.

### 4.10 ELR Override Event Rule

If an `elr_override` event exists for a month, it overrides that month’s resolved ELR contribution amount.

### 4.11 EPF Basis Rule

EPF contributions are computed from gross salary only using configured percent rates.

### 4.12 Statutory Deduction and Net Salary Rule

Monthly statutory deductions are resolved from gross salary using local bracket JSON files:

* SOCSO: `data/contribution-brackets/socso_act4_brackets.json`
* EIS: `data/contribution-brackets/eis_act800_brackets.json`

If gross salary is less than or equal to 0, all statutory deductions are 0.

For positive gross salary:

1. Find the first bracket where `gross_salary >= min` and `gross_salary <= max`.
2. If `max` is `null`, the bracket has no upper limit.
3. If no matching bracket file or bracket exists, the missing deduction amount defaults to 0.

SOCSO Act 4 bracket fields:

* `employer_share`: employer SOCSO amount resolved by the statutory resolver
* `employee_INV`: employee invalidity contribution, exposed in projection rows as `socso`
* `employee_NEI`: employee employment injury contribution, exposed in projection rows as `socso_l24`

SOCSO L24 is optional at scenario level through `employment.socso_l24_enabled`. It is deducted only when enabled and the projection month is June 2026 or later. Projection months before June 2026 always expose `socso_l24` as `0`, regardless of the option.

The checkbox and normalized payload default to `false` when the field is absent, including for older saved scenarios.

EIS Act 800 bracket fields:

* `employee`: employee EIS contribution, exposed in projection rows as `eis`
* `employer`: employer EIS contribution in the source data, not currently included in projection row outputs

Base net salary received in a projection month is:

```text
base_net_salary
= gross_income
- employee_epf
- socso
- socso_l24
- eis
```

`socso_l24` is `0` unless `employment.socso_l24_enabled` is true and the projection month is June 2026 or later. This effective-month rule is applied to the payroll month being projected, including when salary schedules use the paid-in-arrears option.

The monthly row field `net_income` includes base net salary plus income-style monthly additions:

```text
net_income = base_net_salary + allowances + one_off_income
```

The COH formula uses the same components separately:

```text
Closing COH
= Opening COH
+ base_net_salary
+ Allowances
+ One-Off Income
- Living Expenses
- BNPL Repayments
- PTPTN Repayments
- One-Off Expenses
- ELR Contribution
```

### 4.13 Relationship to Counter Module

The projection module is independent from the live Counter module.

Counter behavior:

* Actual COH is derived from persisted starting amount plus actual transactions.
* Counter hover displays Actual COH plus current-month unpaid salary accrual.
* Salary receipts reconcile against unpaid salary accrual from oldest unpaid month first.
* Current-month summaries use month-opening cash plus current-month net transactions and current-month unpaid accrual.

Projection behavior:

* `scenario.starting_coh` is an explicit scenario input and is not automatically pulled from Counter.
* Monthly `opening_coh` is the prior projection row's `closing_coh`, except for the first row where it equals `scenario.starting_coh`.
* `Net Salary Received In Projection Month` is planned salary after the salary arrears rule, not live unpaid accrual.
* FIFO salary receipt reconciliation is not applied because projection rows do not contain actual transaction receipts.
* If a scenario should start from the live app state, the caller must explicitly seed `starting_coh` with the desired Counter-derived value, such as live Actual COH or a month-opening cash amount.

---

## 5. Data Model Requirements

### 5.1 projection_scenarios

| Field           | Type          |
| --------------- | ------------- |
| id              | bigint        |
| name            | string        |
| parameters_json | json          |
| notes           | nullable text |
| created_at      | timestamp     |
| updated_at      | timestamp     |

`parameters_json` stores normalized scenario input payload.

### 5.2 projection_results_cache

| Field        | Type        |
| ------------ | ----------- |
| id           | bigint      |
| scenario_id  | foreign key |
| results_json | json        |
| created_at   | timestamp   |
| updated_at   | timestamp   |

Cache is written on save/load/compare paths when needed.

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

* `GET /projection`
* `POST /projection/run`
* `POST /projection/scenarios`
* `GET /projection/scenarios/{scenario}`
* `DELETE /projection/scenarios/{scenario}`
* `POST /projection/compare`

### 6.2 Projection Request Payload Shape

```json
{
  "scenario": {
    "start_month": "2026-06",
    "end_month": "2026-08",
    "starting_coh": 0,
    "starting_elr": 0,
    "starting_epf": 0
  },
  "employment": {
    "salary_schedules": [
      {
        "start_month": "2026-06",
        "end_month": "2026-08",
        "monthly_gross_salary": 1800,
        "employee_epf_rate_percent": null,
        "employer_epf_rate_percent": null,
        "note": "Probation"
      },
      {
        "start_month": "2026-09",
        "end_month": null,
        "monthly_gross_salary": 2200,
        "employee_epf_rate_percent": null,
        "employer_epf_rate_percent": null,
        "note": "Confirmed"
      }
    ],
    "salary_paid_in_arrears": true,
    "socso_l24_enabled": true
  },
  "cost_of_living": {
    "budgets": {
      "bcol": {
        "name": "BCOL",
        "category_allocations": [
          { "category_id": "food", "name": "Food", "amount": 500 },
          { "category_id": "transportation", "name": "Transportation", "amount": 200 }
        ]
      },
      "travel_month": {
        "name": "Travel Month",
        "category_allocations": [
          { "category_id": "food", "name": "Food", "amount": 650 },
          { "category_id": "transportation", "name": "Transportation", "amount": 500 }
        ]
      }
    },
    "monthly_budget_selection": [
      { "month": "2026-07", "budget": "travel_month" }
    ]
  },
  "ptptn": {
    "waiver_granted": false,
    "monthly_repayment": 120,
    "repayment_start_month": "2026-08"
  },
  "bnpl": [
    { "month": "2026-06", "amount": 150, "note": "Phone" }
  ],
  "events": [
    { "month": "2026-08", "type": "one_off_expense", "amount": 500, "note": "Laptop repair" }
  ],
  "elr": {
    "schedules": [
      { "start_month": "2026-06", "end_month": "2026-08", "amount": 50 }
    ],
    "note": "Optional note",
    "compound_interest_enabled": false,
    "annual_interest_rate_percent": 0
  },
  "epf": {
    "employee_rate_percent": 11,
    "employer_rate_percent": 13
  }
}
```

### 6.3 Projection Response Shape (Core)

```json
{
  "meta": {
    "start_month": "2026-06",
    "end_month": "2026-08",
    "months_count": 3,
    "salary_paid_in_arrears": true,
    "socso_l24_enabled": true,
    "ptptn_waiver_granted": false
  },
  "summary": {
    "final_coh": 0,
    "final_elr": 0,
    "final_epf": 0,
    "final_tfp": 0,
    "lowest_coh": 0,
    "highest_coh": 0
  },
  "months": [
    {
      "month": "2026-06",
      "opening_coh": 0,
      "closing_coh": 0,
      "opening_elr": 0,
      "closing_elr": 0,
      "opening_epf": 0,
      "closing_epf": 0,
      "gross_income": 0,
      "net_income": 0,
      "expenses": 0,
      "bnpl": 0,
      "ptptn": 0,
      "debt_servicing": 0,
      "elr_contribution": 0,
      "elr_interest": 0,
      "employee_epf": 0,
      "employer_epf": 0,
      "socso": 0,
      "socso_l24": 0,
      "eis": 0
    }
  ]
}
```

Frontend responsibilities:

* collect projection inputs
* call projection endpoints
* render tables/charts
* derive display-only TFP sums and pairwise comparison highlights from backend-returned closing balances
* perform no source projection or statutory-deduction computation

### 6.4 Saved Scenario Contract

`POST /projection/scenarios` accepts the projection payload plus:

* `name` (required)
* `notes` (optional)
* `scenario_id` (optional; when present, update that scenario)

Saving persists the full normalized input payload in `projection_scenarios.parameters_json`, computes the result, and updates `projection_results_cache`. The response contains `message`, scenario summary metadata, and `result`.

`GET /projection/scenarios/{scenario}` returns detailed scenario metadata including `parameters_json`, plus the cached or regenerated `result`.

`DELETE /projection/scenarios/{scenario}` deletes the scenario and cascades its cached result and legacy actual rows.

### 6.5 Scenario Comparison Contract

`POST /projection/compare` accepts:

```json
{
  "scenario_ids": [1, 2]
}
```

The endpoint accepts two to four IDs; the current UI sends two distinct scenario IDs. The response contains `comparisons[]`, where each item contains comparison scenario metadata and its complete projection `result`. The UI performs the month-union and highlight presentation described in section 3.11.

---

## 7. Service Structure (Laravel)

### ProjectionService

* normalize payload
* orchestrate month-by-month computation
* return result via result builder

### SalaryCalculator

* salary schedule matching
* schedule-specific gross salary selection
* schedule-specific EPF rate override support
* arrears handling

### ExpenseCalculator

* resolve monthly budget profile key
* aggregate category allocations

### BNPLCalculator

* month-based BNPL sum

### PTPTNCalculator

* waiver and repayment start logic

### ELRCalculator

* schedule-based ELR contribution resolution
* event override handling
* optional compound interest progression

### EPFCalculator

* employee/employer EPF computation

### StatutoryDeductionResolver

* SOCSO/EIS deduction lookup from gross income

### ProjectionResultBuilder

* build `meta`, `summary`, and `months` response shape

---

## 8. Locked Implementation Decisions

The following decisions are finalized:

* projection is scenario-local and deterministic
* scenario inputs are persisted in `parameters_json`
* projection uses explicit scenario payload only
* closing COH may be negative
* salary arrears means exact one-month lag
* EPF is based on gross salary only
* SOCSO L24 is scenario-local, opt-in, and never applies before June 2026
* budget selection is month-specific via `monthly_budget_selection`
* budget profiles are arbitrary saved plans keyed by profile ID
* if a month has no valid selected budget profile, the first saved profile is used
* PTPTN waiver is permanent per scenario
* ELR supports optional compound-interest progression with daily compounding
