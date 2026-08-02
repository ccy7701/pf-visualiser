# OVERVIEW

## High-Level Project Specification

Implementation status: verified against the application on 2026-08-02.

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
* local-only for prompt preparation; no LLM or AI API integration

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

* derive current liquid cash from `starting_amount + net_transactions`
* derive expected and hover counter values from actual cash plus unpaid salary accrual

Key characteristics:

* no minute-persistence job for counter value
* salary accrual applies only during working windows on `workday` status dates
* salary receipts reconcile oldest unpaid salary accrual first, independent of the receipt month
* monthly summaries use the month-opening cash position plus current-month net transactions and current-month unpaid accrual
* default workday fallback: weekdays are workdays, weekends are holidays
* workday status model supports `workday`, `absence`, `holiday`
* snapshot API returns `counter`, `increment_per_second`, and related breakdown fields
* optional system notification displays incrementing Expected COH and refreshes every 60 seconds while the Counter page remains open
* dedicated Settings page hosts General settings, Workday Calendar, and Salary Schedules
* Transaction Log supports optional category-specific subcategories for entry, editing, display, and hierarchical filtering
* History and Projection remain intentionally aggregated at parent-category level

### Module B: COH Projection (`coh-projection`)

Purpose:

* produce month-by-month deterministic projections for COH, ELR, and EPF

Key characteristics:

* scenario-local payloads persisted in `projection_scenarios.parameters_json`
* save/load/delete/compare scenario workflow
* result caching through `projection_results_cache`
* ELR schedule support with optional compound-interest progression
* cost-of-living handled via saved budget profiles and month-specific budget selection
* optional SOCSO L24 deduction, effective no earlier than June 2026
* monthly scenario comparison across COH, ELR, EPF, and TFP, with higher shared-month values highlighted and non-shared months greyed out

### Module C: Variance Analysis (`variance-analysis`)

Purpose:

* compare projected month-end values against actuals for saved scenarios

Key characteristics:

* scenario-linked monthly plan-vs-actual workflow
* projected baseline sourced from cached projection results (regenerated when missing)
* active actual month values sourced read-only from `history_months`
* `projection_actual_months` retained only for the legacy write endpoint
* per-category History expense breakdown support for actual expenses
* month-level variance display for COH, ELR, EPF, and TFP

### Module D: Transportation Log (`transportation-log`)

Purpose:

* track refuelling and commute-driven fuel costs
* estimate transport cost and efficiency
* compare actual fuel spending vs estimated commute fuel cost

Key characteristics:

* submodules: vehicle profile, fuel logs, commute logs, and parking logs
* deterministic calculations from explicit odometer/fuel/commute inputs
* backend-connected snapshot + CRUD endpoints for vehicle, refuel, drive, and parking logs
* monthly, weekly, since-refuel, and custom dashboards for fuel spend, drive estimate, weighted average mileage, commute distance, and parking cost
* row-click edit workflow for refuel and drive logs (populate input tab + edit/delete actions)
* drive logs support an optional final odometer reading for the end of each trip
* casual and monthly-pass parking records with billing-month handling
* JSON export for monthly, weekly, since-refuel, and custom periods
* 24-hour datetime input/display consistency for refuel and drive records
* transport-cost tracking aligned with projection budget profile planning assumptions

### Module E: History (`history`)

Purpose:

* track historical month-end COH, ELR, EPF, monthly income, and monthly expenses
* visualise historical trend movement across rolling 12-month windows

Key characteristics:

* explicit month-scoped persistence in `history_months`
* manual month-end COH, ELR, and EPF entry, with derived TFP
* income and expense totals derived from category-level monthly breakdowns
* month picker with automatic month load behavior
* rolling 12-month navigation with one-month backward/forward window movement
* selectable visualisations for TFP trend, stacked COH/ELR/EPF, income/expenses, and expense-category composition
* optional current-month unpaid-accrual overlay on TFP Trend

### Module F: Prompt Studio (`prompt-studio`)

Purpose:

* save reusable instructions for weekly, monthly, or custom financial reviews
* combine those instructions with deterministic transaction and balance summaries
* produce editable, copy-ready plain text for an external LLM without sending application data externally

Key characteristics:

* standalone, full-width module with circular subsection controls for separate Prompt Templates and Prompt Composer workspaces
* desktop Composer layout with working controls on the left and generated output on the right
* circular module-navigation entry below Transportation and above the theme toggle
* seeded weekly financial-review and month-end report templates
* readable `{{placeholder}}` substitution for periods, positions, transaction totals, category breakdowns, context, and questions
* inclusive weekly, monthly, and custom date ranges
* week and month selectors that resolve inclusive dates automatically from the template preset
* automatic or explicit ongoing/complete period status for partial and final prompt wording
* status-only monthly introductions, with breakdown-introduction wording reserved for weekly and custom templates
* expense and income category groups sorted by total amount descending
* transaction summaries stop at category or formal subcategory totals and omit individual purchase details
* optional COH, ELR, and EPF overrides; otherwise all three positions come from one applicable History record, with derived LFP and TFP
* no Counter-based COH fallback in prompt position resolution
* month-end comparison against the immediately preceding History month when available
* generated preview and browser clipboard copy action
* no network call or AI service integration

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
* Category and optional subcategory assignment
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
* History-backed actual month loading
* COH/ELR/EPF variance reporting
* Expense breakdown support for actuals

## PHASE 11 - Transportation Log Module

* Vehicle profile modeling
* Fuel log entry and efficiency estimation
* Commute log entry and cost estimation
* Period-scoped fuel dashboard and weighted mileage summary
* Row-driven edit/delete workflow for refuel and drive logs
* Parking-log workflow and period-based JSON export

## PHASE 12 - History Module

* Historical month persistence model
* Month-based save/load workflow
* Category-level income and expense breakdown inputs
* Rolling 12-month selectable TFP, balance-breakdown, income/expense, and expense-category visualisations

## PHASE 13 - Prompt Studio

* Standalone module and circular navigation entry
* Prompt-template persistence and CRUD in the Prompt Templates workspace
* Independent Prompt Composer workspace for generating output
* Weekly, monthly, and custom period presets
* Parent-category and formal-subcategory total composition without individual purchase details
* Optional position overrides and History-backed month comparison
* Local preview and clipboard workflow with no AI integration

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

* Advanced salary forecasting variants
* Scenario simulation
* Savings goals
* Recurring expenses
* Rich BNPL modelling templates
* Projected month-end balance
* Historical Counter playback
* General application import and non-transport exports
* Mobile UX enhancements
* Investment simulation
