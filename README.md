# PF Visualiser

A simple personal finance web app on Laravel for tracking current cash position and planning forward scenarios.

It combines:

- a live counter (starting cash + transactions, with unpaid salary accrual overlays and optional browser notification refreshed every 60 seconds while incrementing),
- scenario-based projection (COH, ELR, EPF, TFP, salary schedules, opt-in SOCSO L24 from June 2026, budget profiles, month-specific budget selection, and monthly scenario comparison),
- variance analysis (saved projections versus read-only History actuals by month),
- transportation tracking (vehicles, refuel, drive, and parking logs; monthly, weekly, since-refuel, and custom summaries; JSON export),
- history tracking (month-end COH/ELR/EPF, derived TFP, income/expense breakdowns, selectable visualisations, and an optional unpaid-accrual overlay across rolling 12-month windows).

Detailed, implementation-verified specifications are in [`docs/overview.md`](docs/overview.md) and the module specifications under [`docs/`](docs/).
