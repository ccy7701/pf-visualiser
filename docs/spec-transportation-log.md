# Transportation Log Module (`transportation-log`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`transportation-log` is a transport-cost tracking module for:

* refuelling records
* commute-cost estimation
* fuel-efficiency monitoring
* monthly plan-versus-actual transport cost comparison

This module supports the personal finance model where transportation can materially increase from BCOL to FCOL ranges.

---

## 2. Core Architectural Principle

The Transportation Log module MUST operate with deterministic computations from explicit user inputs.

The following constraints apply:

* no hidden UI-side assumptions
* no implicit trip reconstruction from background location data
* refuel and commute estimates must be reproducible from stored records
* efficiency values from non-full-tank events must be flagged as estimates

---

## 3. Functional Requirements

### 3.1 Submodules (Initial Scope)

The system shall include:

* vehicle profile
* fuel logs
* commute logs

### 3.2 Vehicle Profile

The system shall support:

* vehicle name
* description
* odometer unit (initially `km`)
* fuel unit (initially `L`)
* preferred consumption unit (`L/100km` or `km/L`)
* tank capacity (L)
* fuel type
* default fuel price mode

Initial fuel price modes:

* `budi95`
* `ron95_non_subsidised`
* `manual`

Initial pricing rules:

* `budi95` default reference price: `RM1.99/L` (manual override allowed)
* `ron95_non_subsidised` may change weekly (manual override allowed)

### 3.3 Fuel Logs

The system shall support fuel log entry with:

* vehicle
* odometer reading (km)
* fuel amount (L)
* price per litre
* total amount
* full tank flag
* date fuelled
* time fuelled
* location
* notes

The system shall compute and expose:

* distance since previous fuel log
* estimated `L/100km`
* estimated `km/L`
* cost per km
* reliability status (`estimated` or `more_reliable_full_tank_based`)

### 3.4 Commute Logs

The system shall support commute-focused entry with:

* vehicle
* commute type
* origin
* destination
* distance (km)
* consumption input value
* consumption input unit (`L/100km` or `km/L`)
* fuel price mode
* date
* direction
* notes

Initial commute type values:

* `home_to_work`
* `work_to_home`
* `custom`

The system shall compute and expose:

* estimated fuel used (L)
* estimated fuel cost
* estimated cost per km

### 3.5 Dashboard (Initial)

The dashboard shall include:

* this month fuel spending (actual)
* this month estimated commute cost
* total fuel litres logged (month)
* average `L/100km` (from eligible logs)
* estimated commute distance (month)
* fuel budget remaining
* projected month-end fuel cost

### 3.6 Comparison View

The system shall support monthly comparison between:

* actual fuel spending from fuel logs
* estimated commute fuel cost from commute logs

---

## 4. Computation Logic

### 4.1 Fuel Log Derived Metrics

If a previous fuel log exists for the same vehicle:

```text
distance_since_last_km = current_odometer_km - previous_odometer_km
```

If `distance_since_last_km > 0` and `fuel_litres > 0`:

```text
estimated_l_per_100km = (fuel_litres / distance_since_last_km) * 100
estimated_km_per_litre = distance_since_last_km / fuel_litres
cost_per_km = total_amount / distance_since_last_km
```

If `full_tank = false`, efficiency outputs must be tagged as estimated.

### 4.2 Commute Log Derived Metrics

Given distance `d_km`, consumption, and price per litre:

When input unit is `L/100km`:

```text
estimated_fuel_litres = (consumption_l_per_100km / 100) * d_km
```

When input unit is `km/L`:

```text
estimated_fuel_litres = d_km / consumption_km_per_l
```

Then:

```text
estimated_fuel_cost = estimated_fuel_litres * price_per_litre
estimated_cost_per_km = estimated_fuel_cost / d_km
```

### 4.3 Fuel Spending and Budget Metrics

Monthly actual fuel spending:

```text
actual_fuel_spending_month = sum(fuel_logs.total_amount for month)
```

Monthly estimated commute fuel cost:

```text
estimated_commute_cost_month = sum(commute_logs.estimated_fuel_cost for month)
```

Fuel budget remaining:

```text
fuel_budget_remaining = monthly_transport_budget - actual_fuel_spending_month
```

Projected month-end fuel cost (simple run-rate baseline):

```text
projected_month_end_fuel_cost
=
(actual_fuel_spending_to_date / elapsed_days_in_month) * total_days_in_month
```

---

## 5. Data Model Requirements

### 5.1 fuel_vehicle_profiles

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| name                     | string        |
| description              | nullable text |
| odometer_unit            | string(16)    |
| fuel_unit                | string(16)    |
| consumption_unit_default | string(16)    |
| tank_capacity_l          | nullable decimal(8,2) |
| fuel_type                | string(32)    |
| default_fuel_price_mode  | string(32)    |
| created_at               | timestamp     |
| updated_at               | timestamp     |

### 5.2 fuel_logs

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| vehicle_id               | foreign key   |
| odometer_km              | decimal(12,2) |
| fuel_litres              | decimal(10,3) |
| price_per_litre          | decimal(8,3)  |
| total_amount             | decimal(12,2) |
| is_full_tank             | boolean       |
| fuelled_at               | datetime      |
| location                 | nullable string |
| notes                    | nullable text |
| created_at               | timestamp     |
| updated_at               | timestamp     |

Derived fields may be materialized later for query performance but are not required for correctness.

### 5.3 commute_logs

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| vehicle_id               | foreign key   |
| commute_type             | string(32)    |
| origin                   | string        |
| destination              | string        |
| distance_km              | decimal(10,2) |
| consumption_value        | decimal(10,4) |
| consumption_unit         | string(16)    |
| fuel_price_mode          | string(32)    |
| price_per_litre          | decimal(8,3)  |
| commute_date             | date          |
| direction                | nullable string(32) |
| notes                    | nullable text |
| created_at               | timestamp     |
| updated_at               | timestamp     |

### 5.4 fuel_price_references (Optional First-Class Table)

If implemented for weekly tracking:

| Field         | Type          |
| ------------- | ------------- |
| id            | bigint        |
| fuel_mode     | string(32)    |
| effective_on  | date          |
| price_per_litre | decimal(8,3) |
| source_note   | nullable string |
| created_at    | timestamp     |
| updated_at    | timestamp     |

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints (Initial Contract Target)

* `GET /transportation-log`
* `GET /transportation-log/vehicles`
* `POST /transportation-log/vehicles`
* `PUT /transportation-log/vehicles/{vehicle}`
* `GET /transportation-log/transportation-logs`
* `POST /transportation-log/transportation-logs`
* `PUT /transportation-log/transportation-logs/{fuelLog}`
* `GET /transportation-log/commute-logs`
* `POST /transportation-log/commute-logs`
* `PUT /transportation-log/commute-logs/{commuteLog}`
* `GET /transportation-log/dashboard`

### 6.2 Validation Baseline

* odometer and distance values must be positive
* fuel litres must be positive
* price per litre and total amount must be non-negative
* datetime/date fields must be valid
* vehicle relation must exist
* consumption unit must be one of `L/100km`, `km/L`

### 6.3 Response Baseline

API responses should include:

* persisted source fields
* computed metrics used by UI cards/tables
* explicit flags for estimate reliability

---

## 7. Service Structure (Laravel)

### FuelLogService

Responsibilities:

* create/update fuel logs
* compute fuel-log derived metrics
* aggregate monthly actual fuel spending

### CommuteLogService

Responsibilities:

* create/update commute logs
* compute commute fuel/cost estimates
* aggregate monthly commute estimates

### FuelDashboardService

Responsibilities:

* monthly summary cards
* budget remaining computation
* month-end projection baseline
* actual-vs-estimated comparison output

---

## 8. Interaction Flow

1. User creates one or more vehicle profiles.
2. User logs refuelling events over time.
3. System computes per-log distance/efficiency/cost metrics.
4. User logs routine commute events.
5. System computes commute fuel and cost estimates.
6. Dashboard aggregates monthly actuals and estimates.
7. User reviews actual fuel spending vs estimated commute fuel cost.

---

## 9. Non-Goals (Initial Release)

This module does not:

* track parking/tolls/maintenance as full transport ledger items
* provide full GPS trip history
* run advanced route analytics or map optimization
* provide complex BI-grade charting in first release
