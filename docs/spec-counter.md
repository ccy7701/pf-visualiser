# Personal Finance Counter Module (`counter`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`counter` is a real-time liquidity counter module that derives current liquid cash from:

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

The UI shows one large Counter as the primary focus. It must:

* increase over time via accrual logic
* respond immediately to transaction changes
* represent projected current liquid cash

### 3.2 Expense Logging

Users can log expenses with:

* datetime
* category
* note
* amount

Expenses decrease the Counter.

### 3.3 Income Logging

Users can log income with:

* datetime
* category
* note
* amount

Income increases the Counter.

### 3.4 Salary Accrual

Salary accrual is workday-based:

* accrues only during configured workdays
* does not accrue on non-working days
* is proportional to elapsed eligible workday minutes

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

1. Determine active salary schedule for current date.
2. Determine configured workdays in period.
3. Convert monthly salary into minute rate across workday minutes.
4. Calculate elapsed eligible minutes.
5. Derive accrued salary.

Conceptual example:

```text
Monthly Salary: RM1751.70
Workdays in Month: 22
Total Workday Minutes: 22 * 24 * 60
Minute Rate: 1751.70 / total_workday_minutes
Accrued Salary = elapsed_eligible_minutes * minute_rate
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
| is_workday | boolean       |
| notes      | nullable text |
| created_at | timestamp     |
| updated_at | timestamp     |

Workday definitions must support manual override.

---

## 6. Backend/Frontend Contract

The backend must not run minute-based Counter persistence jobs.

Instead:

* backend returns current computed Counter + increment rate
* frontend increments visible value locally (per-second animation)
* full recomputation occurs on refresh, transaction mutation, config change, or manual sync

---

## 7. Service Structure (Laravel)

### CounterService

* starting amount retrieval
* transaction aggregation
* salary accrual integration
* final Counter derivation

### SalaryAccrualService

* active schedule resolution
* workday counting
* minute-rate computation
* elapsed eligible minute computation

### WorkdayService

* workday lookup
* range queries
* manual override support
