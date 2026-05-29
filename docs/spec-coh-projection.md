# Cumulative Cash on Hand Projection Module (`coh-projection`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`coh-projection` is a forward-looking projection module that forecasts future balances across a configurable month range.

The module computes and compares scenario outcomes using configurable assumptions such as:

* salary progression
* probation duration
* PTPTN repayment status
* BNPL obligations
* household commitments
* savings allocations

The primary output of this module is a month-by-month projection of:

* Cash on Hand (COH)
* Emergency Liquidity Reserve (ELR)
* Employees Provident Fund (EPF)

---

## 2. Core Architectural Principle

The module MUST operate as a deterministic projection engine.

Given identical inputs, the engine must always produce identical outputs.

The following constraints apply:

* no hidden assumptions in UI layers
* no hardcoded financial constants in presentation code
* all calculations executed inside backend services
* frontend responsible only for input collection and output rendering
* projection input must be self-contained within a scenario payload
* projection must not derive assumptions from live transaction history

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

* probation salary
* confirmed salary
* probation duration (3 months, 6 months, or custom)
* salary commencement month
* salary paid-in-arrears toggle

Employment settings are scenario-local.

The projection module must not depend on `salary_schedules` used by the real-time counter module.

### 3.3 Cost of Living Configuration

The system shall support:

* BCOL amount
* FCOL Lite amount
* FCOL Max amount
* FCOL Lite start month
* FCOL Max start month
* category-based expense entries

Cost inputs are explicit projection inputs only.

No extrapolation from historical `transactions` is allowed.

### 3.4 PTPTN Debt Configuration

The system shall support:

* waiver granted flag
* waiver denied flow
* monthly repayment amount
* repayment commencement month

PTPTN waiver is a scenario-level permanent boolean.

### 3.5 BNPL Debt Configuration

The system shall support:

* multiple BNPL schedules
* fixed monthly repayment amounts
* repayment start month
* repayment end month

### 3.6 Household Contributions

The system shall support:

* recurring family allowance
* recurring household bill contributions
* one-off cash injections
* one-off expenses

### 3.7 ELR Configuration

The system shall support:

* daily ELR contribution
* monthly ELR contribution
* variable ELR contribution schedules

### 3.8 EPF Projection

The system shall support:

* employee EPF contribution rate
* employer EPF contribution rate
* monthly EPF accumulation

### 3.9 Projection Output

The system shall produce monthly rows containing:

* month
* gross income
* net income
* total expenses
* debt servicing
* ELR contribution
* EPF contribution
* closing COH
* closing ELR
* closing EPF

`closing_coh` may be negative.

---

## 4. Projection Computation Logic

### 4.1 Chronological Processing Rule

The engine must process projection months in chronological order from start month to end month.

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
- ELR Allocation
```

### 4.3 Monthly ELR Formula

```text
Closing ELR
=
Opening ELR
+ ELR Allocation
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

If month is within probation period:

```text
Use probation salary
```

Otherwise:

```text
Use confirmed salary
```

### 4.6 PTPTN Logic

If waiver granted:

```text
PTPTN repayment = 0
```

If waiver denied and repayment month reached:

```text
PTPTN repayment = configured monthly amount
```

### 4.7 BNPL Logic

If month is within BNPL repayment window:

```text
Apply configured BNPL monthly repayment
```

Otherwise:

```text
BNPL repayment = 0
```

### 4.8 Cost of Living Tier Precedence

Cost-of-living tiers use override precedence, not stacking.

```text
Active Monthly Living Cost = Highest active tier among:
FCOL Max > FCOL Lite > BCOL
```

### 4.9 Arrears Salary Rule

If `salary_paid_in_arrears = true`, salary is shifted by exactly one full month.

Example:

```text
June work salary is paid in July
```

No partial first-month proration is applied.

### 4.10 Negative COH Rule

The engine must allow negative closing COH values.

No floor clamp to zero is applied.

### 4.11 EPF Basis Rule

EPF contributions are computed from gross salary only.

Fixed configured employee and employer rates apply.

---

## 5. Data Model Requirements

### 5.1 projection_scenarios

| Field           | Type           |
| --------------- | -------------- |
| id              | bigint         |
| name            | string         |
| parameters_json | json           |
| notes           | nullable text  |
| created_at      | timestamp      |
| updated_at      | timestamp      |

`parameters_json` stores the complete scenario input set:

* scenario range and starting balances
* employment configuration
* cost-of-living configuration
* PTPTN configuration
* BNPL schedules
* ELR configuration
* EPF configuration
* event entries (allowance/household/one-off items)

### 5.2 projection_results_cache (optional)

| Field        | Type          |
| ------------ | ------------- |
| id           | bigint        |
| scenario_id  | foreign key   |
| results_json | json          |
| created_at   | timestamp     |
| updated_at   | timestamp     |

For `events[].type` values inside `parameters_json`, recommended values are:

* allowance
* household
* one_off_income
* one_off_expense
* elr_override

If no cache is used, projection results are computed on demand from `parameters_json`.

---

## 6. Backend/Frontend Contract

### 6.1 Projection Request Payload

```json
{
  "scenario": {
    "start_month": "2026-06",
    "end_month": "2027-05",
    "starting_coh": 1000.0,
    "starting_elr": 300.0,
    "starting_epf": 0.0
  },
  "employment": {
    "probation_salary": 1800.0,
    "confirmed_salary": 2200.0,
    "probation_duration_months": 3,
    "salary_start_month": "2026-06",
    "salary_paid_in_arrears": true
  },
  "cost_of_living": {
    "bcol_amount": 700.0,
    "fcol_lite_amount": 900.0,
    "fcol_max_amount": 1200.0,
    "fcol_lite_start_month": "2026-09",
    "fcol_max_start_month": "2027-01"
  },
  "ptptn": {
    "waiver_granted": false,
    "monthly_repayment": 120.0,
    "repayment_start_month": "2026-10"
  },
  "bnpl": [
    {
      "name": "Phone",
      "monthly_amount": 150.0,
      "start_month": "2026-06",
      "end_month": "2027-01"
    }
  ],
  "events": [
    {
      "month": "2026-08",
      "type": "one_off_expense",
      "amount": 500.0,
      "note": "Laptop repair"
    }
  ],
  "elr": {
    "monthly_contribution": 50.0
  },
  "epf": {
    "employee_rate": 0.11,
    "employer_rate": 0.13
  }
}
```

### 6.2 Projection Response Payload

```json
{
  "months": [
    {
      "month": "2026-06",
      "opening_coh": 1000.00,
      "closing_coh": 126.78,
      "opening_elr": 300.00,
      "closing_elr": 362.93,
      "opening_epf": 0.00,
      "closing_epf": 122.50,
      "gross_income": 0.00,
      "net_income": 0.00,
      "expenses": 700.00,
      "bnpl": 150.00,
      "ptptn": 0.00,
      "elr_contribution": 62.93,
      "employee_epf": 0.00,
      "employer_epf": 0.00
    }
  ]
}
```

Frontend responsibilities:

* submit projection inputs
* request recomputation
* render tables and charts
* support export UX

The frontend MUST NOT perform financial computations.

When persisting scenarios, the same payload structure is saved into `projection_scenarios.parameters_json`.

---

## 7. Service Structure (Laravel)

### ProjectionService

Responsibilities:

* orchestrate month-by-month computation
* coordinate all calculators
* return finalized projection output

### SalaryCalculator

Responsibilities:

* probation versus confirmed salary logic
* gross/net salary derivation
* salary start and arrears handling

### ExpenseCalculator

Responsibilities:

* BCOL and FCOL application by month
* category expense aggregation
* household and one-off expense handling

### BNPLCalculator

Responsibilities:

* monthly BNPL repayment resolution
* multi-schedule overlap handling

### PTPTNCalculator

Responsibilities:

* waiver logic
* repayment activation by month

### ELRCalculator

Responsibilities:

* ELR allocation resolution (daily/monthly/variable)
* ELR balance updates

### EPFCalculator

Responsibilities:

* employee/employer contribution calculation
* EPF accumulation updates

### ProjectionResultBuilder

Responsibilities:

* shape frontend-ready month rows
* include derived subtotals and balances
* support export-friendly structures

---

## 8. Locked Implementation Decisions

The following decisions are finalized:

* employment configuration is fully scenario-local
* full scenario parameters are stored as JSON per saved scenario
* projection uses explicit projection config only (no historical extrapolation)
* closing COH may be negative
* salary arrears means exact full-month lag with no partial first-month handling
* EPF is calculated from gross salary using fixed employee/employer rates
* cost-of-living tiers use highest-active override precedence (FCOL Max > FCOL Lite > BCOL)
* PTPTN waiver is permanent per scenario
