# History Module (`history`)

## Functional Specification

Implementation status: verified against the application on 2026-08-31.

Related high-level project specification: `overview.md`

---

## 1. Module Purpose

`history` is a historical trend visualisation module for month-by-month personal finance tracking.

The system visualises a rolling 12-month window of historical values for:

* Cash on Hand (COH)
* Emergency Liquidity Reserve (ELR)
* Employees Provident Fund (EPF)
* total expenses
* total income
* Total Financial Position (TFP), where `TFP = COH + ELR + EPF`

The most recent month is displayed on the right. Moving left goes backward through the prior months, one month per step.

---

## 2. Core Architectural Principle

The Transaction Log MUST be the source of truth for History income and expense activity. History month records remain the source of truth only for month-end balances.

The following constraints apply:

* month-end COH is manually entered and must not be derived
* month-end ELR is manually entered and must not be derived
* month-end EPF is manually entered and must not be derived
* monthly income and expense category amounts are aggregated from dated transactions
* transaction subcategories roll up to their parent categories and BNPL expenses remain included
* closed months may have sparse, absolute per-category overrides
* an effective category amount is its override when present and its transaction aggregate otherwise
* Variance Analysis consumes the same effective activity values and History month-end balances
* category definitions come from the shared category catalog, with the documented category set used as fallback metadata

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
* Payments
* Reimbursement
* Deposit
* Money Pot Share
* Cash Assistance
* Interest
* EPF
* Fees
* Snacktime
* Others

Transaction subcategories do not alter History aggregation: month-level income and expense breakdowns remain aggregated by these parent categories.

---

## 3. Functional Requirements

### 3.1 Layout

The system shall use a two-column desktop layout:

* left column: monthly inputs
* right column: trend visualisation

The left column shall include:

* a header-level Save Balances action
* selected month display in the monthly inputs header
* tabbed balance, expense, and income input sections
* month picker inside the balance input section
* month-end COH, ELR, and EPF inputs inside the balance input section

The right column shall include:

* one active visualisation pane
* a visualisation selector
* previous/next rolling-window controls
* context-specific controls for unpaid accrual and expense-category value mode

The layout collapses responsively at narrower viewport widths.

### 3.2 Month Window

The system shall display a 12-month historical window.

Display order:

* oldest visible month on the left
* most recent visible month on the right
* one month per step

The system shall support navigating the visible window backward or forward by one month at a time.

### 3.3 Month-End Balance Inputs

The system shall support manual entry of:

* month
* COH at end of month
* ELR at end of month
* EPF at end of month

Month-end COH, ELR, and EPF are not derived from Counter, transactions, projections, or variance analysis.

The balance section shall be navigable via its own tab/button, separate from income and expense entry.

### 3.4 Expense Activity and Overrides

The system shall show transaction-derived expense amounts by category for each month.

The available expense categories are listed in section 2.

The expense activity UI shall use a two-column grid of compact category cells.

Each category cell contains:

* category name
* effective amount
* Transaction Log-derived amount
* Derived or Override source badge
* explicit override toggle and optional note

Override controls are enabled only when the selected month is earlier than the current Kuala Lumpur calendar month. Disabling and saving an override deletes it and restores the live transaction-derived amount.

The expense section shall include a computed total row at the bottom.

The system shall derive total expenses as:

```text
Total Expenses = sum(effective_expense_breakdown[].amount)
```

### 3.5 Income Activity and Overrides

The system shall show transaction-derived income amounts and support the same closed-month override workflow used for expenses.

The available income categories are listed in section 2.

The income activity UI shall use the same two-column category-cell structure as expenses.

The income section shall include a computed total row at the bottom.

The system shall derive total income as:

```text
Total Income = sum(effective_income_breakdown[].amount)
```

### 3.6 Visualisation

The visualisation selector shall provide:

* **TFP Trend**: line graph of `COH + ELR + EPF`
* **COH, ELR and EPF**: stacked balance bars
* **Income and Expenses**: grouped income/expense bars
* **Expenses by Category**: 10x10 square waffle chart for the selected month

The visualisation shall use the 12-month window described in section 3.2.

TFP Trend rules:

* render straight line segments between points
* display month label and Total Balance value below the x-axis
* optionally overlay the current month's `Total Balance + Accrual` using the Counter snapshot
* when the unpaid-accrual overlay is active, display its current-month value as an additional green x-axis label

Balance-breakdown rules:

* render COH, ELR, and EPF as one stacked bar per month
* show total TFP in the tooltip footer

Income and expense chart rules:

* render grouped bars with income and expense as separate bars
* display month label, income value, and expense value below the x-axis
* income label text should use the same green family as the income bars
* expense label text should use the same red family as the expense bars

Expense-category rules:

* use the selected month's expense breakdown
* support `sen/RM` and `RM` value modes
* allocate exactly 100 cells using largest-remainder rounding
* sort categories by amount descending in both the waffle grid and legend
* fill cells from the top-left, proceeding from left to right and top to bottom
* render the complete legend without an internal scrollbar
* start with every category selected
* toggle a category's persistent selected/deselected state when any matching cell or legend entry is clicked
* fade deselected cells and legend entries without removing them from the 100-cell allocation
* suppress category cells and legend entries when the selected month has no expense total
* display `Showing:` rather than `Latest:` in the visualisation subtitle while this view is active

### 3.7 Save Inputs

The system shall support saving monthly history inputs for later retrieval.

Saveable inputs include:

* month-end COH
* month-end ELR
* month-end EPF
* expense category breakdown values
* income category breakdown values

Saving shall update the stored month record for the selected month.

Month selection shall automatically load the currently saved values for that month.

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

When the user navigates the history window:

```text
previous_window_latest_month = latest_month - 1 month
next_window_latest_month = latest_month + 1 month
```

### 4.3 Expense Total Rule

For each month:

```text
total_expenses = sum(expense_breakdown[].amount)
```

Missing or blank category values are treated as `0`.

For each category:

```text
effective_amount = override_amount when an override exists, otherwise transaction_amount
```

### 4.4 Income Total Rule

For each month:

```text
total_income = sum(income_breakdown[].amount)
```

Missing or blank category values are treated as `0`.

### 4.5 Month-End Balance Rules

For each month:

```text
month_end_coh = manually_entered_month_end_coh
month_end_elr = manually_entered_month_end_elr
month_end_epf = manually_entered_month_end_epf
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
| closing_elr            | nullable decimal(12,2) |
| closing_epf            | nullable decimal(12,2) |
| created_at             | timestamp     |
| updated_at             | timestamp     |

Unique constraint:

* `month`

### 5.2 history_category_overrides

| Field       | Type                    |
| ----------- | ----------------------- |
| id          | bigint                  |
| month       | string(7)               |
| category_id | category foreign key    |
| amount      | decimal(12,2)           |
| note        | nullable string(500)    |
| created_at  | timestamp               |
| updated_at  | timestamp               |

Unique constraint: (`month`, `category_id`). Overrides are sparse and preserve explicit zero amounts.

### 5.3 Category Source

The module shall prefer existing `categories` records to determine which input rows to render.

Category selection rules:

* expense inputs use categories whose `type` is `expense`
* income inputs use categories whose `type` is `income`
* if category records are unavailable, the module may fall back to the documented category set in section 2

---

## 6. Backend/Frontend Contract

### 6.1 Endpoints

Current endpoints:

* `GET /history`
* `GET /history/months`
* `POST /history/months`
* `PUT /history/months/{month}`
* `PUT /history/months/{month}/overrides/{type}`

### 6.2 History Page Payload

`GET /history` provides the page shell and category metadata.

The inline `window.historyConfig` includes:

* `latestMonth`
* `currentMonth`
* `monthsEndpoint`
* `saveEndpoint`
* `overrideEndpointTemplate`
* `counterSnapshotEndpoint`
* `expenseCategories[]`
* `incomeCategories[]`

The view also receives the active theme.

### 6.3 Month Data Response

`GET /history/months` returns 12-month history data for the requested latest month.

Response fields:

* `latest_month`
* `months[]`

`months[]` entries contain:

* `month`
* `closing_coh`
* `closing_elr`
* `closing_epf`
* `total_expenses`
* `total_income`
* `expense_breakdown[]`
* `income_breakdown[]`
* `has_balance_record`
* `has_transactions`
* `has_overrides`
* `has_record`

Each breakdown item contains `category_id`, `name`, effective `amount`, `derived_amount`, nullable `override_amount`, nullable `override_note`, and `is_overridden`.

### 6.4 Save Month Request

`POST /history/months` accepts one month of balance inputs.

Request fields:

* `month`
* `closing_coh`
* `closing_elr`
* `closing_epf`

Save operation rules:

* upsert by unique key `month`
* persist only month-end balances

### 6.5 Save Overrides Request

`PUT /history/months/{month}/overrides/{type}` accepts the complete active override set for one closed month and transaction type.

Each `overrides[]` entry contains `category_id`, absolute `amount`, and optional `note`. An empty array clears all overrides for that month and type. The server rejects current/future months and categories that do not match the URL type.

---

## 7. UI Notes

The UI prioritises data entry and trend readability while retaining responsive control wrapping.

Expected desktop view:

* left input column for selected month values
* right visualisation column with one selectable chart pane
* Save Balances button in the monthly inputs header
* separate Save Overrides buttons in the Income and Expenses tabs
* selected month display in the monthly inputs header
* month picker, COH input, ELR input, and EPF input grouped in a balance tab
* balance, income, and expense inputs separated by tabs
* category input cells arranged in a two-column grid
* one selected visualisation displayed at a time
* TFP rendered as the default line series
* stacked COH/ELR/EPF, grouped income/expenses, and expense-category waffle alternatives
* previous and next buttons for one-month history window paging
