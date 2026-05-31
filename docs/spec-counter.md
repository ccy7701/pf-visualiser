# Personal Finance Counter Module (`counter`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`counter` is a deterministic real-time liquidity module that derives current liquid cash from:

* starting liquidity
* net transactions
* workday-aware salary accrual

---

## 2. Core Architectural Principle

The Counter value MUST NOT be stored as a minute-updated persistent value.

Counter is always derived dynamically:

```text
Counter = Starting Amount + Net Transactions + Salary Accrual
```

This ensures:

* deterministic recomputation
* low write amplification
* reduced drift and reconciliation issues

---

## 3. Functional Requirements

### 3.1 Counter Display

The system shall display one large Counter as the primary focus. It must:

* increase over time via accrual logic
* respond immediately to transaction changes
* represent projected current liquid cash

### 3.2 Expense Logging

The system shall support expense logging with:

* datetime
* category
* note
* amount

Expenses decrease the Counter.

### 3.3 Income Logging

The system shall support income logging with:

* datetime
* category
* note
* amount

Income increases the Counter.

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

### 4.1 Formula

```text
Current Counter Value = Starting Amount + Net Transactions + Salary Accrual
```

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
6. Sum all days into `accrued_salary`.

Increment rate at a point in time:

1. Resolve schedule for `as_of`.
2. Ensure date status is `workday`.
3. Ensure time is inside configured working windows.
4. `increment_per_second = (monthly_net_salary / scheduled_workdays_in_month) / 28800`.

Conceptual example:

```text
Monthly Salary: RM1751.70
Workdays in Month: 22
Daily Salary Basis: 1751.70 / 22
Per-second Rate (during working windows only): (1751.70 / 22) / 28800
Accrued Salary = sum of each day's eligible_seconds * per-second_rate_for_that_day
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
| is_workday | boolean (legacy compatibility) |
| notes      | nullable text |
| created_at | timestamp     |
| updated_at | timestamp     |

`status` values:

* `workday`
* `absence`
* `holiday`

Notes:

* `is_workday` is retained for backward compatibility.
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

* backend returns current computed Counter + increment rate
* frontend increments visible value locally (per-second animation)
* full recomputation occurs on refresh, transaction mutation, config change, or manual sync

Current snapshot endpoint:

* `GET /counter/snapshot`

Current response fields:

* `as_of`
* `starting_amount`
* `income_total`
* `expense_total`
* `net_transactions`
* `accrued_salary`
* `counter`
* `increment_per_second`
* `minute_rate`

`as_of` resolution:

* if `use_simulation_now` is truthy and `simulation_now` exists, use that timestamp (Asia/Kuala_Lumpur)
* otherwise use live `now('Asia/Kuala_Lumpur')`

---

## 7. Service Structure (Laravel)

### CounterService

* starting amount retrieval
* transaction aggregation
* salary accrual integration
* final Counter derivation

### SalaryAccrualService

* active schedule resolution
* scheduled-workday counting (`workday + absence`)
* per-second rate computation
* eligible working-seconds computation within configured windows

### WorkdayService

* date status resolution with fallback (weekday => `workday`, weekend => `holiday`)
* workday lookup (`isWorkday`)
* scheduled-workday lookup (`isScheduledWorkday`)
* monthly scheduled-workday counting
* manual override support
