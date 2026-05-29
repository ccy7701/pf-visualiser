# OVERVIEW

## High-Level Project Specification

---

## 1. Project Overview

This project is a personal-use financial monitoring web application for:

1. Personal liquidity tracking
2. Real-time financial projection visualisation
3. Financial systems experimentation

---

## 2. Product Scope

The system is intentionally:

* single-user
* personal-use oriented
* deterministic in computation
* auditable in data changes

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

---

## 5. Development Phases

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
* Minute-rate calculation
* Elapsed-minute calculation
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

---

## 6. Time and Timezone Guidelines

The system is time-sensitive. Follow these rules:

* Store timestamps in UTC
* Convert carefully to local timezone for display and logic boundaries
* Standardise Malaysia timezone handling
* Validate midnight/month/workday transitions explicitly

---

## 7. Planned Future Modules (Not Required Now)

Potential future additions:

* Charts
* Salary forecasting
* Scenario simulation
* Savings goals
* Recurring expenses
* BNPL modelling
* Projected month-end balance
* Historical Counter playback
* Export/import
* Mobile UX enhancements
* Investment simulation

