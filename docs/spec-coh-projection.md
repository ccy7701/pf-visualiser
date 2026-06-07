# Cumulative Cash on Hand Projection Module (`coh-projection`)

## Functional Specification

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

---

## 3. Functional Requirements

### 3.1 Scenario Configuration

The system shall support:

* projection start month
* projection end month
* starting COH
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
* statutory deductions (`socso`, `eis`)

`closing_coh` may be negative.

The system shall also return metadata fields including:

* `start_month`
* `end_month`
* `months_count`

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
+ Net Salary
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

Schedules are sorted by start month. For each month, the matching schedule is the latest schedule whose `start_month` is on or before the target month and whose optional `end_month` is either blank or on or after the target month.

If no schedule matches a month, gross salary is 0.

Schedule-specific EPF rates override the scenario-level EPF rates for months resolved to that schedule. If a schedule EPF rate is blank, the scenario-level rate is used.

### 4.6 Salary Arrears Rule

If `salary_paid_in_arrears = true`, salary is shifted by exactly one full month.

No partial first-month proration is applied.

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
    "salary_paid_in_arrears": true
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
    "ptptn_waiver_granted": false
  },
  "summary": {
    "final_coh": 0,
    "final_elr": 0,
    "final_epf": 0,
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
      "eis": 0
    }
  ]
}
```

Frontend responsibilities:

* collect projection inputs
* call projection endpoints
* render tables/charts
* perform no financial computations

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
* budget selection is month-specific via `monthly_budget_selection`
* budget profiles are arbitrary saved plans keyed by profile ID
* if a month has no valid selected budget profile, the first saved profile is used
* PTPTN waiver is permanent per scenario
* ELR supports optional compound-interest progression with daily compounding
