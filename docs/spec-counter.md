# Personal Finance Counter Module (`counter`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`counter` is a deterministic real-time liquidity module that derives current liquid cash from:

* starting liquidity
* net transactions
* workday-aware unpaid salary accrual

The module exposes two related balances:

* Actual COH: static between transaction/settings changes
* Expected COH: actual COH plus unpaid scheduled salary accrual

---

## 2. Core Architectural Principle

The Counter value MUST NOT be stored as a minute-updated persistent value.

Counter values are always derived dynamically:

```text
Actual COH = Starting Amount + Net Transactions
Expected COH = Actual COH + Unpaid Salary Accrual
```

This ensures:

* deterministic recomputation
* low write amplification
* reduced drift and reconciliation issues

---

## 3. Functional Requirements

### 3.1 Counter Display

The system shall display one large Counter surface as the primary focus.

Default visible state:

* displays Actual COH
* remains static unless transactions, settings, or other persisted inputs change
* uses the neutral counter color

Hover/focus state:

* displays Expected COH
* increases over time via unpaid salary accrual logic
* displays increment status text below the value
* uses a distinct accent color from the Actual COH state

The hover/focus transition is presentation only; it does not change computation semantics.

### 3.2 Expense Logging

The system shall support expense logging with:

* datetime
* category
* note
* amount

Expenses decrease Actual COH.

### 3.3 Income Logging

The system shall support income logging with:

* datetime
* category
* note
* amount

Income increases Actual COH.

Income transactions categorized as `Salary` also reconcile against scheduled salary accrual for the transaction month so salary is not double-counted in Expected COH.

### 3.4 Salary Accrual

The system shall apply workday-based salary accrual:

* accrues only during configured workdays
* does not accrue on holidays or absence days
* is proportional to elapsed eligible working seconds within configured daily windows

Current configured working windows:

* 08:30 to 12:30
* 13:30 to 17:30

Total eligible work time per full workday: `8 hours = 28,800 seconds`.

---

## 4. Counter Computation Logic

### 4.1 Formulas

```text
Actual COH = Starting Amount + Net Transactions
Expected COH = Actual COH + Unpaid Salary Accrual
```

The `counter` snapshot field is the Actual COH value for backward-compatible page rendering.

### 4.2 Net Transactions

```text
Net Transactions = Total Income - Total Expenses
```

### 4.3 Salary Accrual Calculation

1. Find the earliest `salary_schedules.effective_from` as global accrual start.
2. Iterate each day from accrual start up to `as_of`.
3. Resolve active salary schedule for that day by `effective_from/effective_until`.
4. Count scheduled workdays in that month (`workday` + `absence`) to derive daily base salary.
5. If day status is `workday`, accrue proportionally by eligible seconds within working windows.
6. Sum all days into `scheduled_accrued_salary`.
7. Subtract realized salary transactions for each month to derive `accrued_salary` as unpaid accrual.

Increment rate at a point in time:

1. Resolve schedule for `as_of`.
2. Ensure date status is `workday`.
3. Ensure time is inside configured working windows.
4. `increment_per_second = (monthly_net_salary / scheduled_workdays_in_month) / 28800`.
5. If the current month's scheduled salary is already fully realized by `Income:Salary` transactions, `increment_per_second = 0`.

Conceptual example:

```text
Monthly Salary: RM1751.70
Workdays in Month: 22
Daily Salary Basis: 1751.70 / 22
Per-second Rate (during working windows only): (1751.70 / 22) / 28800
Scheduled Accrued Salary = sum of each day's eligible_seconds * per-second_rate_for_that_day
```

### 4.4 Salary Reconciliation Rule

Salary transactions are grouped by transaction month.

```text
realized_salary_by_month[YYYY-MM] = sum(Income:Salary transactions in that month)
unpaid_accrual_for_month = max(0, scheduled_accrual_for_month - realized_salary_by_month[YYYY-MM])
Unpaid Salary Accrual = sum(unpaid_accrual_for_month)
```

Example:

```text
Starting Amount: RM871.61
Scheduled June salary: RM1766.35
No salary transaction yet:
Actual COH = 871.61
Expected COH = 2637.96

After logging Income:Salary RM1766.35 for June:
Actual COH = 2637.96
Expected COH = 2637.96
```

---

## 5. Data Model Requirements

### 5.1 transactions

| Field       | Type          |
| ----------- | ------------- |
| id          | bigint        |
| type        | enum          |
| datetime    | datetime      |
| category_id | foreign key   |
| note        | text          |
| amount      | decimal(12,2) |
| created_at  | timestamp     |
| updated_at  | timestamp     |

`type` values:

* income
* expense

Recommendation: store `amount` as positive and derive sign from `type`.

### 5.2 categories

| Field      | Type          |
| ---------- | ------------- |
| id         | bigint        |
| name       | string        |
| type       | nullable enum |
| created_at | timestamp     |
| updated_at | timestamp     |

### 5.3 salary_schedules

| Field              | Type          |
| ------------------ | ------------- |
| id                 | bigint        |
| effective_from     | date          |
| effective_until    | nullable date |
| monthly_net_salary | decimal(12,2) |
| notes              | nullable text |
| created_at         | timestamp     |
| updated_at         | timestamp     |

### 5.4 workdays

| Field      | Type          |
| ---------- | ------------- |
| id         | bigint        |
| date       | date          |
| status     | string(16)    |
| notes      | nullable text |
| created_at | timestamp     |
| updated_at | timestamp     |

`status` values:

* `workday`
* `absence`
* `holiday`

Notes:

* legacy `is_workday` has been normalized away in current migrations.
* `absense` (misspelling) is accepted at update boundary and normalized to `absence`.

Workday definitions support manual override.

### 5.5 settings

| Field      | Type          |
| ---------- | ------------- |
| id         | bigint        |
| key        | string(unique)|
| value      | text          |
| created_at | timestamp     |
| updated_at | timestamp     |

Counter-related keys in active use:

* `starting_amount`
* `theme`
* `simulation_now`
* `use_simulation_now`

---

## 6. Backend/Frontend Contract

The backend must not run minute-based Counter persistence jobs.

Instead:

* backend returns computed Actual COH, Expected COH, unpaid accrual, and increment rate
* frontend increments the Expected COH value locally while Actual COH remains static
* full recomputation occurs on refresh, transaction mutation, config change, or manual sync

Current snapshot endpoint:

* `GET /counter/snapshot`

Current response fields:

* `as_of`
* `starting_amount`
* `income_total`
* `expense_total`
* `net_transactions`
* `current_month_income_total`
* `current_month_expense_total`
* `current_month_net_transactions`
* `current_month_unpaid_accrual`
* `projected_eotm_tfp`
* `accrued_salary`
* `scheduled_accrued_salary`
* `realized_salary`
* `actual_counter`
* `expected_counter`
* `counter`
* `increment_per_second`
* `minute_rate`

Field meanings:

* `actual_counter`: Starting Amount + Net Transactions
* `expected_counter`: Actual COH + unpaid salary accrual
* `counter`: alias of `actual_counter`
* `projected_eotm_tfp`: Starting Amount + current-month Net Transactions + current-month Unpaid Accrual
* `accrued_salary`: unpaid scheduled salary accrual after salary transaction reconciliation
* `current_month_unpaid_accrual`: unpaid scheduled salary accrual for the `as_of` month only
* `scheduled_accrued_salary`: raw schedule-derived accrual before salary transaction reconciliation
* `realized_salary`: schedule accrual amount covered by salary transactions

`as_of` resolution:

* if `use_simulation_now` is truthy and `simulation_now` exists, use that timestamp (Asia/Kuala_Lumpur)
* otherwise use live `now('Asia/Kuala_Lumpur')`

---

## 7. Service Structure (Laravel)

### CounterService

* starting amount retrieval
* transaction aggregation
* salary transaction aggregation by month
* salary accrual integration
* Actual COH and Expected COH derivation

### SalaryAccrualService

* active schedule resolution
* scheduled-workday counting (`workday + absence`)
* per-second rate computation
* eligible working-seconds computation within configured windows
* unpaid accrual calculation after realized salary offsets

### WorkdayService

* date status resolution with fallback (weekday => `workday`, weekend => `holiday`)
* workday lookup (`isWorkday`)
* scheduled-workday lookup (`isScheduledWorkday`)
* monthly scheduled-workday counting
* manual override support
