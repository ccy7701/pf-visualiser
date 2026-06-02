# History Module (`history`)

## Functional Specification

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`history` is a historical trend visualisation module for month-by-month personal finance tracking.

The system visualises 12 months of historical values for:

* Cash on Hand (COH)
* total expenses
* total income

The most recent month is displayed on the right. Moving left goes backward through the prior months, one month per step.

---

## 2. Core Architectural Principle

The History module MUST use explicit historical month entries as its source of truth.

The following constraints apply:

* month-end COH is manually entered and must not be derived
* total expenses are derived from user-entered expense category amounts
* total income is derived from user-entered income category amounts
* monthly inputs shall be saved and reloaded from persistent storage
* chart values must be reproducible from stored monthly history records
* category definitions are fixed to the current category set below for this module spec

Expense categories:

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

Income categories:

* Allowance
* PTPTN
* Salary
* Petty Cash
* Bonus
* Loans

---

## 3. Functional Requirements

### 3.1 Layout

The system shall use a two-column desktop layout:

* left column: monthly inputs
* right column: 12-month visualisation

Mobile optimisation is not required for this module.

### 3.2 Month Window

The system shall display a 12-month historical window.

Display order:

* oldest visible month on the left
* most recent visible month on the right
* one month per step

### 3.3 Month-End COH Input

The system shall support manual entry of:

* month
* COH at end of month

Month-end COH is not derived from Counter, transactions, projections, or variance analysis.

### 3.4 Expense Inputs

The system shall support manual expense entry by category for each month.

The available expense categories are listed in section 2.

The system shall derive total expenses as:

```text
Total Expenses = sum(expense_category_amounts[].amount)
```

### 3.5 Income Inputs

The system shall support manual income entry by category for each month.

The available income categories are listed in section 2.

The system shall derive total income as:

```text
Total Income = sum(income_category_amounts[].amount)
```

### 3.6 Visualisation

The system shall render:

* a line graph for month-end COH
* side-by-side bar graphs for total expenses and total income

The visualisation shall use the 12-month window described in section 3.2.

### 3.7 Save Inputs

The system shall support saving monthly history inputs for later retrieval.

Saveable inputs include:

* month-end COH
* expense category breakdown values
* income category breakdown values

Saving shall update the stored month record for the selected month.

---

## 4. Computation Logic

### 4.1 Historical Window Resolution

The system resolves the visible month range as 12 consecutive months ending at the selected latest month.

If no latest month is selected, the latest month defaults to the current calendar month.

### 4.2 Month Ordering Rule

Months are displayed in ascending chronological order.

For a 12-month window:

```text
visible_months = [latest_month - 11 months, ..., latest_month]
```

### 4.3 Expense Total Rule

For each month:

```text
total_expenses = sum(expense_breakdown[].amount)
```

Missing or blank category values are treated as `0`.

### 4.4 Income Total Rule

For each month:

```text
total_income = sum(income_breakdown[].amount)
```

Missing or blank category values are treated as `0`.

### 4.5 COH Rule

For each month:

```text
month_end_coh = manually_entered_month_end_coh
```

No fallback derivation is applied.

---

## 5. Data Model Requirements

### 5.1 history_months

| Field                  | Type          |
| ---------------------- | ------------- |
| id                     | bigint        |
| month                  | string(7)     |
| closing_coh            | decimal(12,2) |
| expense_breakdown_json | json          |
| income_breakdown_json  | json          |
| created_at             | timestamp     |
| updated_at             | timestamp     |

Unique constraint:

* `month`

### 5.2 Expense Breakdown Shape

`expense_breakdown_json` stores category-level monthly expense inputs.

Each entry contains:

* `category_id`
* `name`
* `amount`

### 5.3 Income Breakdown Shape

`income_breakdown_json` stores category-level monthly income inputs.

Each entry contains:

* `category_id`
* `name`
* `amount`

### 5.4 Category Source

The module shall use existing `categories` records to determine which input rows to render.

Category selection rules:

* expense inputs use categories whose `type` is `expense`
* income inputs use categories whose `type` is `income`
* category definitions should be read at runtime from the database, not copied from a one-time database snapshot

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

Initial endpoints:

* `GET /history`
* `GET /history/months`
* `POST /history/months`
* `PUT /history/months/{month}` or equivalent month-upsert endpoint

### 6.2 History Page Payload

`GET /history` provides the initial page shell and category metadata.

Initial payload includes:

* `expense_categories[]`
* `income_categories[]`
* selected latest month

### 6.3 Month Data Response

`GET /history/months` returns 12-month history data for the requested latest month.

Response fields:

* `latest_month`
* `months[]`

`months[]` entries contain:

* `month`
* `closing_coh`
* `total_expenses`
* `total_income`
* `expense_breakdown[]`
* `income_breakdown[]`

### 6.4 Save Month Request

`POST /history/months` accepts one month of historical inputs.

Request fields:

* `month`
* `closing_coh`
* `expense_breakdown[]`
* `income_breakdown[]`

Save operation rules:

* upsert by unique key `month`
* derive `total_expenses` from expense breakdown values
* derive `total_income` from income breakdown values
* preserve category IDs where available
* persist the saved month so the page can reload the same inputs later

---

## 7. UI Notes

The initial UI shall prioritise data entry and trend readability over mobile responsiveness.

Expected desktop view:

* left input column for selected month values
* right visualisation column for the 12-month chart
* COH rendered as a line series
* expenses and income rendered as side-by-side bar series
