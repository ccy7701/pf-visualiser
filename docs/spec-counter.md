# Personal Finance Counter Module (`counter`)

## Functional Specification

Implementation status: verified against the application on 2026-08-01.

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`counter` is a deterministic real-time liquidity module that derives current liquid cash from:

* starting liquidity
* net transactions
* workday-aware unpaid salary accrual

The module exposes these related values:

* Actual COH: static between transaction/settings changes
* Expected COH: actual COH plus unpaid scheduled salary accrual
* Counter hover value: Actual COH plus the current month's unpaid scheduled salary accrual

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

* displays Actual COH plus current-month unpaid salary accrual
* increases over time via current-month unpaid salary accrual logic
* displays increment status text below the value
* uses a distinct accent color from the Actual COH state

The hover/focus transition is presentation only; it does not change computation semantics.

### 3.2 Counter System Notification

The General section of the Settings page shall provide an opt-in browser system notification.

When enabled and `increment_per_second > 0`:

* the notification title uses `RM{expected_counter} | Incrementing (GET TO WORK!)`
* the displayed Expected COH uses Actual COH plus total unpaid salary accrual and is refreshed every 60 seconds
* updates replace the existing notification silently rather than creating a notification stack
* the notification remains available only while the Counter page is open

When incrementing is paused, notifications are disabled, or the user leaves the Counter page, the active Counter notification shall close.

Notification behavior requires browser support, explicit user permission, and a secure context (HTTPS or localhost). The enabled preference is browser-local and does not add a database setting.

### 3.3 Expense Logging

The system shall support expense logging with:

* datetime
* category
* note
* amount

Expenses decrease Actual COH.

### 3.4 Income Logging

The system shall support income logging with:

* datetime
* category
* note
* amount

Income increases Actual COH.

Income transactions categorized as `Salary` also reconcile against scheduled salary accrual using oldest-unpaid-month-first allocation, so a salary paid in the following month can settle the prior month's accrued salary without clearing the current month's accrual.

Successful transaction create, update, and delete actions shall use the page-level bottom-right status toast rather than an inline form success alert.

### 3.5 Transaction Log Period Navigation

The Transaction Log transaction table shall support period filters:

* Daily
* Weekly
* Monthly
* Annually

The table header shall describe the active range:

* Daily: `Transactions over {date}`
* Weekly: `Transactions over x/x - x/x`
* Monthly: `Transactions over MonthName YYYY`
* Annually: `Transactions over the year YYYY`

Previous (`<`) and next (`>`) controls shall shift the active range backward or forward by one selected unit.

### 3.6 Settings Page

Counter configuration is maintained on a dedicated Settings page. Its navigation provides General, Workday Calendar, and Salary Schedules sections.

The General section shall:

* describe Starting Amount as the base cash amount used to calculate the Counter
* expose the Counter notification enable/disable control
* render Save Settings as a centered four-column button with top spacing

The Salary Schedules panel shall render Add Schedule as a centered four-column button with top spacing.

### 3.7 Salary Accrual

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
7. Allocate realized salary transactions against scheduled accrual from oldest unpaid month to newest.
8. Subtract allocated realized salary from scheduled accrual to derive `accrued_salary` as unpaid accrual.

Increment rate at a point in time:

1. Resolve schedule for `as_of`.
2. Ensure date status is `workday`.
3. Ensure time is inside configured working windows.
4. `increment_per_second = (monthly_net_salary / scheduled_workdays_in_month) / 28800`.
5. If the current month's scheduled salary is already fully realized by allocated `Income:Salary` transactions, `increment_per_second = 0`.

Conceptual example:

```text
Monthly Salary: RM1751.70
Workdays in Month: 22
Daily Salary Basis: 1751.70 / 22
Per-second Rate (during working windows only): (1751.70 / 22) / 28800
Scheduled Accrued Salary = sum of each day's eligible_seconds * per-second_rate_for_that_day
```

### 4.4 Salary Reconciliation Rule

Salary transactions are ordered by transaction datetime and allocated against unpaid scheduled salary accrual from oldest month to newest month. The transaction date still controls when the income affects Actual COH and current-month Net Transactions, but it does not force the salary receipt to reconcile only against that transaction month.

```text
salary_realizations = Income:Salary transactions ordered by datetime
allocated_realized_salary_by_month = FIFO allocation of salary_realizations against scheduled_accrual_by_month
unpaid_accrual_for_month = max(0, scheduled_accrual_for_month - allocated_realized_salary_by_month[YYYY-MM])
Unpaid Salary Accrual = sum(unpaid_accrual_for_month)
```

Example:

```text
Starting Amount: RM871.61
Scheduled June salary: RM1766.35
No salary transaction yet:
Actual COH = 871.61
Expected COH = 2637.96

After logging Income:Salary RM1766.35:
Actual COH = 2637.96
Expected COH = 2637.96
```

If the June salary is paid in July, the July-dated transaction increases July Actual COH and July Net Transactions, while the salary reconciliation allocates the payment to June's unpaid accrual first. July unpaid accrual remains visible and continues incrementing until it is later paid or otherwise fully allocated.

### 4.5 Current-Month Summary Values

The Transaction Log "Values for this month" panel uses month-specific values:

```text
Current Month Starting Amount = Starting Amount + all transactions before start_of_month
Current Month Net Transactions = income during as_of month - expenses during as_of month
Current Month Unpaid Accrual = unpaid scheduled salary accrual for as_of month only
Projected EOTM TFP = Current Month Starting Amount + Current Month Net Transactions + Current Month Unpaid Accrual
```

This means the displayed Starting Amount in the monthly panel is the opening cash position for the selected/current month, not necessarily the persisted `starting_amount` setting.

Example:

```text
Global Starting Amount setting: RM871.61
June net transactions before July: +RM70.24
July Current Month Starting Amount: RM941.85

July salary receipt for June: +RM1576.60
July expenses so far: -RM190.65
July Current Month Net Transactions: RM1385.95
Actual COH/static counter: RM2327.80
Hover counter: RM2327.80 + July unpaid accrual
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
* frontend increments the hover value and summary unpaid-accrual values locally while Actual COH remains static
* full recomputation occurs on refresh, transaction mutation, config change, or manual sync
* the browser notification service worker (`/counter-notification-sw.js`) owns the persistent system notification surface
* while opted in and incrementing, the page replaces that notification every 60 seconds using a stable notification tag

Current snapshot endpoint:

* `GET /counter/snapshot`

Related current page and mutation endpoints:

* `GET /counter`
* `GET /transaction-log`
* `POST /transactions`
* `PATCH /transactions/{transaction}`
* `DELETE /transactions/{transaction}`
* `PATCH /workdays/{workday}`
* `GET /settings`

Current response fields:

* `as_of`
* `starting_amount`
* `current_month_starting_amount`
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
* `expected_counter`: Actual COH + total unpaid salary accrual
* `counter`: alias of `actual_counter`
* `starting_amount`: persisted base cash setting
* `current_month_starting_amount`: opening cash position for the `as_of` month, calculated as Starting Amount + all transactions before that month
* `projected_eotm_tfp`: Current Month Starting Amount + current-month Net Transactions + current-month Unpaid Accrual
* `accrued_salary`: unpaid scheduled salary accrual after salary transaction reconciliation
* `current_month_unpaid_accrual`: unpaid scheduled salary accrual for the `as_of` month only
* `scheduled_accrued_salary`: raw schedule-derived accrual before salary transaction reconciliation
* `realized_salary`: schedule accrual amount covered by allocated salary transactions

`as_of` resolution:

* if `use_simulation_now` is truthy and `simulation_now` exists, use that timestamp (Asia/Kuala_Lumpur)
* otherwise use live `now('Asia/Kuala_Lumpur')`

---

## 7. Service Structure (Laravel)

### CounterService

* starting amount retrieval
* transaction aggregation
* current-month opening balance derivation
* salary transaction realization retrieval
* salary accrual integration
* Actual COH and Expected COH derivation

### SalaryAccrualService

* active schedule resolution
* scheduled-workday counting (`workday + absence`)
* per-second rate computation
* eligible working-seconds computation within configured windows
* oldest-unpaid-month-first salary realization allocation
* unpaid accrual calculation after allocated realized salary offsets

### WorkdayService

* date status resolution with fallback (weekday => `workday`, weekend => `holiday`)
* workday lookup (`isWorkday`)
* scheduled-workday lookup (`isScheduledWorkday`)
* monthly scheduled-workday counting
* manual override support
