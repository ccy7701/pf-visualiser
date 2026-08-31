# PF Visualiser

A simple personal finance web app on Laravel for tracking current cash position and planning forward scenarios.

It combines:

- a live counter (starting cash + transactions, with unpaid salary accrual overlays and optional browser notification refreshed every 60 seconds while incrementing),
- a Transaction Log with optional category-specific subcategories, hierarchical filtering, and daily through custom-period review,
- scenario-based projection (COH, ELR, EPF, TFP, salary schedules with editable EPF/statutory/custom contribution lists, budget profiles, month-specific budget selection, and monthly scenario comparison),
- variance analysis (saved projections versus read-only History actuals by month),
- transportation tracking (vehicles, refuel, drive, and parking logs; monthly, weekly, since-refuel, and custom summaries; JSON export),
- history tracking (month-end COH/ELR/EPF, derived TFP, income/expense breakdowns, selectable visualisations, and an optional unpaid-accrual overlay across rolling 13-month windows),
- Prompt Studio (saved weekly/monthly/custom templates, transaction breakdown generation, editable context, preview, and clipboard copying for use with an external LLM; no AI integration).

Detailed, implementation-verified specifications are in [`docs/overview.md`](docs/overview.md) and the module specifications under [`docs/`](docs/).

## Development

For a fresh checkout, install the backend and frontend dependencies, create the environment file, generate the application key, run migrations, and build the assets with:

```bash
composer run setup
```

Then start the Laravel server, queue listener, application log viewer, and Vite development server together:

```bash
composer run dev
```

If the PHP dependencies are already installed and only the frontend dependency tree needs refreshing, run `npm install`. The optional Laravel Vite font-fallback optimizer is enabled, so `fontaine` is included explicitly in the development dependencies.
