# Transportation Log Module (`transportation-log`)

## Functional Specification

Implementation status: verified against the application on 2026-08-01.

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`transportation-log` is a transport-cost tracking module for:

* refuelling records
* commute-cost estimation
* fuel-efficiency monitoring
* parking-cost tracking
* period-scoped transport summaries and JSON export

This module supports the personal finance model where transportation can materially vary between saved projection budget profiles.

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

### 3.1 Submodules

The system shall include:

* vehicle profile
* fuel logs
* commute logs
* parking logs
* dashboard and export

### 3.2 Vehicle Profile

The system shall support:

* vehicle name
* description
* preferred consumption unit (`L/100km` or `km/L`)
* tank capacity (L)

Fuel price modes:

* `budi95`
* `ron95`

Current pricing rules:

* `budi95` default reference price: `RM1.99/L` (manual override allowed)
* `ron95` defaults to `RM2.05/L` in the UI and permits manual override

### 3.3 Fuel Logs

The system shall support fuel log entry with:

* vehicle
* odometer reading (km)
* fuel amount (L)
* fuel price mode
* price per litre
* total amount
* date fuelled
* time fuelled
* location
* notes

The system shall support row-based edit workflow:

* clicking a refuel row populates the refuel input subsection
* the refuel input subsection becomes the active input tab
* action mode switches from `Add` to `Edit` and `Delete` in a two-column layout
* update and delete persist through backend endpoints
* the refuel log table displays the recorded location, or an em dash when location is absent

The system shall compute and expose:

* distance since previous fuel log
* estimated `L/100km`
* estimated `km/L`
* cost per km

### 3.4 Commute Logs

The system shall support commute-focused entry with:

* vehicle
* commute type
* origin
* destination
* distance (km)
* optional final odometer reading at the end of the drive (km)
* consumption input value
* mileage in `L/100km`
* start date and time
* end date and time
* average speed (km/h)
* top speed (km/h)
* notes

The system shall support row-based edit workflow:

* clicking a drive row populates the drive input subsection
* the drive input subsection becomes the active input tab
* action mode switches from `Add` to `Edit` and `Delete` in a two-column layout
* update and delete persist through backend endpoints

Commute type values:

* `work_commute`
* `personal_drive`

The system shall compute and expose:

* ending and starting odometer readings as whole-number secondary information within the distance column; the start comes from the immediately preceding drive log for the same vehicle
* estimated fuel used (L)
* estimated fuel cost
* estimated cost per km

### 3.5 Parking Logs

The system shall support:

* casual parking with location, parking date, start hour, end hour, amount, and notes
* monthly passes with purchase date, billing month, location, amount, and notes
* row-click edit/delete workflow matching fuel and drive logs

Casual parking requires an end hour later than its start hour. Monthly passes omit hours; if billing month is omitted, it defaults to the purchase-date month.

For monthly summaries, a monthly pass is scoped by `billing_month`; other periods use its purchase date.

### 3.6 Dashboard

The dashboard supports monthly, weekly, since-refuel, and custom date scopes. It includes:

* scoped fuel spending (actual)
* scoped estimated drive cost
* scoped fuel litres refilled
* average `L/100km` weighted from scoped drive logs by distance
* scoped drive distance
* parking cost

The monthly and weekly controls support previous/next navigation. Since-refuel scopes are bounded by refuel events. Custom scopes require inclusive start/end dates.

### 3.7 Comparison and Export

The system shall support current-period comparison between:

* actual fuel spending from fuel logs
* estimated commute fuel cost from commute logs

The current scoped dashboard can be exported as JSON. The export contains:

* period metadata (`type`, `starts_at`, `ends_before`)
* headline statistics
* refuel logs
* derived drive logs
* parking logs

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

### 4.2 Commute Log Derived Metrics

Given distance `d_km`, consumption, and price per litre:

The current UI stores drive mileage as `L_PER_100KM`:

```text
estimated_fuel_litres = (consumption_l_per_100km / 100) * d_km
```

Then:

```text
estimated_fuel_cost = estimated_fuel_litres * price_per_litre
estimated_cost_per_km = estimated_fuel_cost / d_km
```

### 4.3 Period Summary Metrics

Scoped actual fuel spending:

```text
actual_fuel_spending = sum(scoped_fuel_logs.total_amount)
```

Scoped estimated drive fuel cost:

```text
estimated_drive_cost = sum(scoped_commute_logs.estimated_fuel_cost)
```

Distance-weighted average mileage from scoped drive logs:

```text
weighted_avg_l_per_100km
=
sum(scoped_commute_logs.consumption_value * scoped_commute_logs.distance_km)
/
sum(scoped_commute_logs.distance_km)
```

Scoped parking cost:

```text
parking_cost = sum(scoped_parking_logs.total_amount)
```

---

## 5. Data Model Requirements

### 5.1 transportation_vehicles

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| name                     | string        |
| description              | nullable text |
| consumption_unit_default | string(16)    |
| tank_capacity_l          | decimal(8,2) |
| created_at               | timestamp     |
| updated_at               | timestamp     |

### 5.2 transportation_fuel_logs

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| vehicle_id               | foreign key   |
| odometer_km              | decimal(10,2) |
| fuel_litres              | decimal(10,3) |
| fuel_price_mode          | string(32)    |
| price_per_litre          | decimal(10,3) |
| total_amount             | decimal(10,2) |
| fuelled_at               | datetime      |
| location                 | nullable string |
| notes                    | nullable text |
| created_at               | timestamp     |
| updated_at               | timestamp     |

Derived fields may be materialized later for query performance but are not required for correctness.

### 5.3 transportation_commute_logs

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | bigint        |
| vehicle_id               | foreign key   |
| commute_type             | string(32)    |
| origin                   | string        |
| destination              | string        |
| distance_km              | decimal(10,2) |
| final_odometer_km        | nullable decimal(10,2) |
| consumption_value        | decimal(10,4) |
| consumption_unit         | string(16)    |
| driven_at                | datetime      |
| ended_at                 | nullable datetime |
| average_speed_kmh        | nullable decimal(10,2) |
| top_speed_kmh            | nullable decimal(10,2) |
| drive_time_minutes       | nullable unsigned integer |
| notes                    | nullable text |
| created_at               | timestamp     |
| updated_at               | timestamp     |

### 5.4 transportation_parking_logs

| Field         | Type          |
| ------------- | ------------- |
| id            | bigint        |
| parking_type  | string(32)    |
| location      | string        |
| parking_date  | date          |
| billing_month | nullable date |
| start_hour    | nullable unsigned tiny integer |
| end_hour      | nullable unsigned tiny integer |
| total_amount  | decimal(10,2) |
| notes         | nullable text |
| created_at    | timestamp     |
| updated_at    | timestamp     |

`parking_type` values are `casual` and `monthly_pass`.

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

* `GET /transportation-log`
* `GET /transportation-log/snapshot`
* `POST /transportation-log/vehicles`
* `PUT /transportation-log/vehicles/{vehicle}`
* `DELETE /transportation-log/vehicles/{vehicle}`
* `POST /transportation-log/fuel-logs`
* `PUT /transportation-log/fuel-logs/{fuelLog}`
* `DELETE /transportation-log/fuel-logs/{fuelLog}`
* `POST /transportation-log/commute-logs`
* `PUT /transportation-log/commute-logs/{commuteLog}`
* `DELETE /transportation-log/commute-logs/{commuteLog}`
* `POST /transportation-log/parking-logs`
* `PUT /transportation-log/parking-logs/{parkingLog}`
* `DELETE /transportation-log/parking-logs/{parkingLog}`
* `POST /transportation-log/export`

### 6.2 Validation Baseline

* refuel odometer values must be non-negative and drive distance must be positive
* final drive odometer is optional and must be non-negative when provided
* fuel litres must be positive
* price per litre and total amount must be non-negative
* datetime/date fields must be valid
* vehicle relation must exist
* backend consumption unit must be `L_PER_100KM` or `KM_PER_L`; the current drive form submits `L_PER_100KM`
* drive end datetime must be after drive start datetime
* top speed must be greater than or equal to average speed
* parking type must be `casual` or `monthly_pass`
* casual parking hours must satisfy `0 <= start_hour < end_hour <= 24`
* export period must be `monthly`, `weekly`, `since_refuel`, or `custom`
* custom export end date must be on or after its start date

### 6.3 Response Baseline

Snapshot and mutation responses include:

* persisted source fields
* source arrays for vehicles, refuel logs, drive logs, and parking logs via snapshot
* source records only; the frontend derives metrics used by UI cards and tables

### 6.4 Input/Display Time Format

* datetime display in logs uses 24-hour format
* time inputs for refuel and drive use 24-hour format

---

## 7. Implementation Structure

### TransportationLogController

Responsibilities:

* render the page and return the complete snapshot
* validate and persist vehicle, fuel, drive, and parking CRUD operations
* derive drive duration from start/end timestamps
* validate export scope and stream the JSON download

### TransportationExportPayloadBuilder

Responsibilities:

* resolve monthly, weekly, since-refuel, and custom scopes
* scope source records consistently
* derive drive mileage, fuel use, and route cost using the applicable fuel price
* build headline statistics and normalized export rows

### Frontend dashboard

Responsibilities:

* derive scoped cards and table metrics from snapshot source arrays
* manage period navigation and row-click editing
* submit the active export scope

---

## 8. Interaction Flow

1. User creates one or more vehicle profiles.
2. User logs refuelling events over time.
3. System computes per-log distance/efficiency/cost metrics.
4. User logs routine commute events.
5. System computes commute fuel and cost estimates.
6. Dashboard aggregates actuals and estimates for the selected period.
7. User logs casual parking or monthly passes.
8. User reviews a monthly, weekly, since-refuel, or custom period.
9. User may export that period as JSON.

---

## 9. Non-Goals

This module does not:

* track tolls or maintenance as full transport ledger items
* provide full GPS trip history
* run advanced route analytics or map optimization
* provide complex BI-grade charting
