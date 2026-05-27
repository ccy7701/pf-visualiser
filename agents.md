# Personal Finance Counter System (pf-counter)

## System Design & Development Specification

---

# 1. Project Overview

This project is a personal-use financial monitoring web application built primarily for:

1. Personal liquidity tracking.
2. Real-time financial projection visualisation.
3. Financial systems experimentation.

The application revolves around a single central concept:

> A dynamically computed real-time Counter representing projected current liquidity.

The Counter shall:

* increase continuously over time according to salary accrual logic,
* increase when income logs are added,
* decrease when expense logs are added,
* and always be derived dynamically rather than permanently stored.

The project intentionally prioritises:

* deterministic financial computation,
* auditability,
* temporal modelling,
* and simplicity of architecture.

---

# 2. Core Architectural Philosophy

## CRITICAL PRINCIPLE

The Counter value SHALL NOT be physically stored and incremented in the database every minute.

Instead:

```text
Counter
=
Starting Amount
+ Net Logged Transactions
+ Derived Salary Accrual
```

The Counter is therefore:

* ephemeral,
* deterministic,
* reproducible,
* and dynamically computed.

This avoids:

* database write amplification,
* drift problems,
* reconciliation inconsistencies,
* scheduler dependency,
* and state corruption.

The database shall store:

* financial events,
* schedules,
* workday definitions,
* and user configuration.

The application layer shall derive the current Counter value at runtime.

---

# 3. Technology Stack

## Backend

* Laravel
* PHP
* MySQL

## Frontend

* Blade
* Bootstrap
* Livewire (optional but expected)

## NOT IN SCOPE (for now)

* SPA frameworks
* Vue
* React
* Pusher/WebSockets
* Multi-user systems
* Authentication complexity
* Cloud deployment

This system is intentionally single-user and personal-use oriented.

---

# 4. Initial Development Assumptions

## Timeline Simulation

For testing purposes:

* The system timeline begins in May 2026.
* “Today” shall initially be treated as the final workday of May 2026.
* This allows immediate visual testing of:

  * rollover behaviour,
  * accrual transitions,
  * and Counter calculations.

---

# 5. Core Functional Requirements

## 5.1 Counter

A single large Counter shall appear prominently in the center of the interface.

The Counter shall:

* dynamically increase over time,
* respond immediately to transactions,
* and represent projected current liquid cash.

The Counter SHALL:

* update visually in real time on the frontend,
* but SHALL NOT constantly write updates into the database.

---

## 5.2 Expense Logging

The user shall be able to log an expense.

Each expense record shall contain:

| Field    | Type     |
| -------- | -------- |
| datetime | datetime |
| category | relation |
| note     | text     |
| amount   | decimal  |

Expense logs SHALL reduce the Counter.

---

## 5.3 Income Logging

The user shall be able to log an income.

Each income record shall contain:

| Field    | Type     |
| -------- | -------- |
| datetime | datetime |
| category | relation |
| note     | text     |
| amount   | decimal  |

Income logs SHALL increase the Counter.

---

## 5.4 Salary Accrual Engine

The Counter shall automatically increase over time according to configured salary schedules.

The system SHALL use the following approach:

# Workday-Only Accrual

Salary accrual SHALL:

* only occur during configured workdays,
* stop on non-working days,
* and compute salary flow proportionally over workdays.

This mode is intentionally chosen for realism.

---

# 6. Database Design

---

## 6.1 transactions

Purpose:
Store all financial events.

### Table Structure

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

### Notes

`type` values:

* income
* expense

Recommendation:
Store all amounts as positive values.
Determine financial sign through `type`.

---

## 6.2 categories

Purpose:
Classify transactions.

### Table Structure

| Field      | Type          |
| ---------- | ------------- |
| id         | bigint        |
| name       | string        |
| type       | nullable enum |
| created_at | timestamp     |
| updated_at | timestamp     |

### Categories

The categories used in the system shall only cover the following Expenses and Incomes, and shall be added to using Seeders in the future when needed.

Expenses:

* Family
* Groceries
* Food
* Household
* Health
* Personal Care
* IT Product
* Prepaid Reload
* Transportation
* Apparel
* Books and Stationery
* Fees
* Subscriptions
* Entertainment
* Gifts and Giving
* Travel
* Payments
* Special Projects
* Others

Income:

* Allowance
* PTPTN
* Salary
* Petty Cash
* Bonus
* Loans
* Payments
* Deposit
* Money Pot Share
* Cash Assistance
* Interest
* Fees
* Other

---

## 6.3 salary_schedules

Purpose:
Store salary configurations over time.

### Table Structure

| Field              | Type          |
| ------------------ | ------------- |
| id                 | bigint        |
| effective_from     | date          |
| effective_until    | nullable date |
| monthly_net_salary | decimal(12,2) |
| notes              | nullable text |
| created_at         | timestamp     |
| updated_at         | timestamp     |

### Example

| effective_from | monthly_net_salary |
| -------------- | ------------------ |
| 2026-06-01     | 1751.70            |
| 2026-10-01     | 2101.90            |

This allows:

* probation simulation,
* salary changes,
* promotions,
* future planning.

---

## 6.4 workdays

Purpose:
Define valid salary-accrual days.

### Table Structure

| Field      | Type          |
| ---------- | ------------- |
| id         | bigint        |
| date       | date          |
| is_workday | boolean       |
| notes      | nullable text |
| created_at | timestamp     |
| updated_at | timestamp     |

### Notes

This table intentionally allows manual override.

Reason:
Malaysian workday structures are inconsistent.

Examples:

* public holidays,
* Sabah-specific holidays,
* replacement holidays,
* company-specific leave,
* half-days,
* Saturday workdays.

The system SHALL prioritise manual configurability over automatic holiday logic.

---

# 7. Counter Computation Logic

## Core Formula

```text
Current Counter
=
Starting Amount
+ Net Transactions
+ Salary Accrual
```

---

## 7.1 Starting Amount

The application SHALL support configurable initial liquidity.

Example:

```text
RM800.00 at end of May 2026
```

This acts as the baseline anchor.

---

## 7.2 Net Transactions

Computation:

```text
Total Income - Total Expenses
```

This shall be dynamically summed from the transactions table.

---

## 7.3 Salary Accrual Logic

### Workday-Based Accrual

Salary SHALL accrue only during configured workdays.

Computation process:

1. Determine current month.
2. Determine monthly salary for that period.
3. Count configured workdays.
4. Convert monthly salary into per-minute workday accrual.
5. Compute elapsed workday minutes.
6. Derive accrued salary.

---

## Conceptual Example

```text
Monthly Salary: RM1751.70
Workdays in June: 22

Total workday minutes:
22 * 24 * 60

Per-minute accrual:
1751.70 / total_workday_minutes
```

Then:

```text
Accrued Salary
=
Elapsed Eligible Minutes * Minute Rate
```

---

# 8. Important Design Decision

## DO NOT RUN BACKEND MINUTE WRITES

The backend SHALL NOT:

* update the Counter every minute,
* run minute-based DB writes,
* or persist time increments.

Instead:

The frontend SHALL:

* fetch the current computed Counter,
* compute the increment rate,
* and animate locally.

Backend recomputation should occur:

* on refresh,
* transaction submission,
* configuration change,
* or manual reload.

This dramatically reduces complexity.

---

# 9. Suggested Laravel Structure

## Services

### CounterService

Purpose:
Centralise Counter computation.

Responsibilities:

* starting amount retrieval,
* transaction summation,
* salary accrual calculation,
* workday eligibility logic,
* final Counter derivation.

---

### SalaryAccrualService

Purpose:
Handle temporal salary computation.

Responsibilities:

* determine active salary schedule,
* determine workdays,
* compute minute rate,
* compute elapsed eligible minutes,
* return accrued salary.

---

### WorkdayService

Purpose:
Handle workday logic.

Responsibilities:

* determine if date is workday,
* retrieve workdays in range,
* manual override support.

---

# 10. Frontend Behaviour

---

## 10.1 Main Counter UI

The Counter should:

* dominate the screen visually,
* remain immediately readable,
* and feel “alive.”

Suggested display characteristics:

* large font,
* centered vertically and horizontally,
* minimal UI clutter,
* smooth numeric updates.

Bootstrap is sufficient.

---

## 10.2 Live Updating

Frontend SHALL:

* increment the visible value in JavaScript,
* without repeatedly querying the backend.

Suggested flow:

1. Backend returns:

   * current Counter,
   * increment rate.

2. Frontend:

   * stores baseline value,
   * increments locally every second.

3. Full recalculation occurs:

   * on page refresh,
   * transaction mutation,
   * or explicit sync.

---

## 10.3 Workday Calendar UI

The system SHALL include:

* a visual calendar,
* manual workday toggling.

The user should be able to:

* mark workdays,
* mark holidays,
* override weekends.

This is critical for realistic accrual behaviour.

---

# 11. Suggested Development Order

---

## PHASE 1 — Project Setup

Tasks:

* Initialise Laravel.
* Configure MySQL.
* Configure timezone.
* Configure environment.
* Install Bootstrap.
* Install Livewire.

---

## PHASE 2 — Database Layer

Tasks:

* Create migrations.
* Create models.
* Create seeders.
* Seed categories.
* Seed initial salary schedules.

---

## PHASE 3 — Transaction Engine

Tasks:

* Expense CRUD.
* Income CRUD.
* Category assignment.
* Validation.
* Transaction listing.

---

## PHASE 4 — Workday Engine

Tasks:

* Build workday table.
* Calendar UI.
* Workday toggling.
* Date logic.

---

## PHASE 5 — Salary Accrual Engine

Tasks:

* Salary schedule retrieval.
* Workday counting.
* Minute-rate calculation.
* Elapsed-minute calculation.
* Accrued salary derivation.

---

## PHASE 6 — Counter Engine

Tasks:

* Build CounterService.
* Integrate transactions.
* Integrate salary accrual.
* Return derived Counter.

---

## PHASE 7 — Frontend Counter UI

Tasks:

* Giant centered Counter.
* Smooth updates.
* Formatting.
* Decimal handling.
* Visual responsiveness.

---

## PHASE 8 — Testing & Simulation

Tasks:

* Simulate May 2026.
* Simulate June rollover.
* Validate accrual timing.
* Validate workday exclusions.
* Validate transaction impacts.

---

# 12. Time & Timezone Considerations

This system is highly time-sensitive.

Recommendations:

* Store timestamps in UTC.
* Convert carefully to local timezone.
* Standardise Malaysia timezone handling.
* Be careful around:

  * midnight boundaries,
  * month rollover,
  * workday transitions.

---

# 13. Potential Future Features

NOT REQUIRED NOW.

Possible future additions:

* charts,
* salary forecasting,
* scenario simulation,
* savings goals,
* recurring expenses,
* BNPL modelling,
* projected month-end balance,
* historical Counter playback,
* export/import,
* mobile responsiveness improvements,
* investment simulation.

---

# 14. Final Design Summary

The system should be viewed less as:

> “a banking ledger”

and more as:

> “a real-time liquidity simulation engine driven by financial events and temporal salary accrual.”

The most important architectural principles are:

1. The Counter is dynamically derived.
2. Salary accrual is workday-aware.
3. Transactions are event-based.
4. The frontend animates locally.
5. The backend remains deterministic and auditable.
6. Simplicity is preferred over premature optimisation.
