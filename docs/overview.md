# OVERVIEW

## High-Level Project Specification

---

## 1. Project Overview

This project is a personal-use financial monitoring web application for:

1. Personal liquidity tracking
2. Deterministic scenario-based financial projection
3. Financial systems experimentation and model validation

---

## 2. Product Scope

The system is intentionally:

* single-user
* personal-use oriented
* deterministic in computation
* auditable in data changes
* backend-calculation first (frontend for input/output rendering)

---

## 3. Technology Baseline

### Backend

* Laravel
* PHP
* MySQL

### Frontend

* Blade
* Bootstrap
* Livewire (optional but expected)

### Not In Scope (Current Phase)

* SPA frameworks
* Vue
* React
* Pusher/WebSockets
* Multi-user systems
* Advanced authentication flows
* Cloud deployment architecture

---

## 4. Initial Simulation Assumptions

For early testing:

* Timeline starts in May 2026
* "Today" is treated as the final workday of May 2026

This enables immediate validation of month rollover and accrual transition behavior.

Counter runtime also supports simulation time via settings (`simulation_now`, `use_simulation_now`).

---

## 5. Modules

### Module A: Counter (`counter`)

Purpose:

* derive current liquid cash from `starting_amount + net_transactions + accrued_salary`

Key characteristics:

* no minute-persistence job for counter value
* salary accrual applies only during working windows on `workday` status dates
* default workday fallback: weekdays are workdays, weekends are holidays
* workday status model supports `workday`, `absence`, `holiday`
* snapshot API returns `counter`, `increment_per_second`, and related breakdown fields

### Module B: COH Projection (`coh-projection`)

Purpose:

* produce month-by-month deterministic projections for COH, ELR, and EPF

Key characteristics:

* scenario-local payloads persisted in `projection_scenarios.parameters_json`
* save/load/delete/compare scenario workflow
* result caching through `projection_results_cache`
* ELR schedule support with optional compound-interest progression
* cost-of-living handled via budget sets and month-specific budget selection

### Module C: Variance Analysis (`variance-analysis`)

Purpose:

* compare projected month-end values against actuals for saved scenarios

Key characteristics:

* scenario-linked monthly plan-vs-actual workflow
* projected baseline sourced from cached projection results (regenerated when missing)
* actual month values persisted in `projection_actual_months`
* per-category expense breakdown support for actual expenses
* month-level variance display for COH, ELR, and EPF

### Module D: Transportation Log (`transportation-log`)

Purpose:

* track refuelling and commute-driven fuel costs
* estimate transport cost and efficiency
* compare actual fuel spending vs estimated commute fuel cost

Key characteristics:

* submodules: vehicle profile, fuel logs, commute logs
* deterministic calculations from explicit odometer/fuel/commute inputs
* backend-connected snapshot + CRUD endpoints for vehicle/refuel/drive logs
* monthly dashboard for fuel spend, commute estimate, weighted average mileage, and commute distance
* row-click edit workflow for refuel and drive logs (populate input tab + edit/delete actions)
* 24-hour datetime input/display consistency for refuel and drive records
* transport-cost tracking aligned with BCOL/FCOL planning assumptions

---

## 6. Development Phases (Historical Baseline)

## PHASE 1 - Project Setup

* Initialise Laravel
* Configure MySQL
* Configure timezone/environment
* Install Bootstrap
* Install Livewire

## PHASE 2 - Database Layer

* Create migrations/models/seeders
* Seed categories
* Seed initial salary schedules

## PHASE 3 - Transactions

* Expense CRUD
* Income CRUD
* Category assignment
* Validation
* Transaction listing

## PHASE 4 - Workday Calendar

* Workday table
* Calendar UI
* Workday toggling
* Date logic

## PHASE 5 - Salary Schedule and Accrual

* Salary schedule retrieval
* Workday counting
* Per-second increment calculation
* Elapsed eligible-seconds calculation
* Accrued salary derivation

## PHASE 6 - Main Counter

* Build CounterService
* Integrate transactions/accrual
* Return derived Counter

## PHASE 7 - Counter UI

* Main centered Counter UI
* Smooth updates
* Formatting and decimal handling
* Visual responsiveness

## PHASE 8 - Testing and Simulation

* Simulate May 2026 baseline
* Simulate June rollover
* Validate accrual timing
* Validate workday exclusions
* Validate transaction effects

## PHASE 9 - Projection Engine

* Scenario payload modeling
* Month-by-month projection service orchestration
* Scenario persistence and caching
* Scenario comparison workflow

## PHASE 10 - Variance Analysis

* Scenario-linked plan-vs-actual workflow
* Actual month value persistence
* COH/ELR/EPF variance reporting
* Expense breakdown support for actuals

## PHASE 11 - Transportation Log Module

* Vehicle profile modeling
* Fuel log entry and efficiency estimation
* Commute log entry and cost estimation
* Monthly fuel dashboard and weighted mileage summary
* Row-driven edit/delete workflow for refuel and drive logs

---

## 7. Time and Timezone Guidelines

The system is time-sensitive. Follow these rules:

* Store timestamps in UTC
* Convert carefully to local timezone for display and logic boundaries
* Standardise Asia/Kuala_Lumpur timezone handling for module logic
* Validate midnight/month/workday transitions explicitly

---

## 8. Planned Future Modules (Beyond Current Plan)

Potential future additions:

* Charts
* Advanced salary forecasting variants
* Scenario simulation
* Savings goals
* Recurring expenses
* Rich BNPL modelling templates
* Projected month-end balance
* Historical Counter playback
* Export/import
* Mobile UX enhancements
* Investment simulation
