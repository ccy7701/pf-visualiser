<?php

use App\Models\Category;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\CounterService;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public array $snapshot = [
        'starting_amount' => 0,
        'net_transactions' => 0,
        'accrued_salary' => 0,
        'expected_counter' => 0,
        'current_month_starting_amount' => 0,
        'current_month_net_transactions' => 0,
        'current_month_unpaid_accrual' => 0,
        'projected_eotm_tfp' => 0,
    ];

    public array $transactions = [];

    public float $periodIncomeTotal = 0.0;

    public float $periodExpenseTotal = 0.0;

    public array $categories = [];

    public array $allCategories = [];

    public array $selectedCategoryIds = [];

    public bool $categoryFiltersInitialized = false;

    public string $recentTransactionPeriod = 'daily';

    public string $referenceDate = '';

    public string $customPeriodStartDate = '';

    public string $customPeriodEndDate = '';

    public string $noteSearch = '';

    public bool $showNoteSearch = false;

    public string $type = 'income';

    public string $datetime = '';

    public string $category_id = '';

    public string $amount = '';

    public string $note = '';

    public array $errors = [];

    public ?int $editingTransactionId = null;

    public ?int $deletingTransactionId = null;

    public function mount(CounterService $counterService): void
    {
        $this->referenceDate = now('Asia/Kuala_Lumpur')->toDateString();
        $this->loadData($counterService);
    }

    public function loadData(CounterService $counterService): void
    {
        $this->snapshot = $counterService->snapshot();

        $this->allCategories = Category::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->toArray();

        $allCategoryIds = array_map(fn ($category) => (string) $category['id'], $this->allCategories);

        if (! $this->categoryFiltersInitialized) {
            $this->selectedCategoryIds = $allCategoryIds;
            $this->categoryFiltersInitialized = true;
        } else {
            $this->selectedCategoryIds = array_values(array_intersect($this->selectedCategoryIds, $allCategoryIds));
        }

        $this->loadTransactions();

        $this->setInitialDatetime();
        $this->filterCategoriesByType();
    }

    public function updatedRecentTransactionPeriod(): void
    {
        $this->loadTransactions();
    }

    public function updatedNoteSearch(): void
    {
        $this->loadTransactions();
    }

    public function updatedSelectedCategoryIds(): void
    {
        $validCategoryIds = array_map(fn ($category) => (string) $category['id'], $this->allCategories);
        $this->selectedCategoryIds = array_values(array_intersect($this->selectedCategoryIds, $validCategoryIds));
        $this->loadTransactions();
    }

    public function toggleNoteSearch(): void
    {
        if ($this->showNoteSearch) {
            $this->closeNoteSearch();

            return;
        }

        $this->showNoteSearch = true;
    }

    public function closeNoteSearch(): void
    {
        $this->showNoteSearch = false;
    }

    public function resetTransactionFilters(): void
    {
        $this->noteSearch = '';
        $this->selectedCategoryIds = array_map(fn ($category) => (string) $category['id'], $this->allCategories);
        $this->loadTransactions();
    }

    public function setCategoryGroupSelection(string $type, bool $selected): void
    {
        if (! in_array($type, ['income', 'expense'], true)) {
            return;
        }

        $groupCategoryIds = array_map(
            fn ($category) => (string) $category['id'],
            array_filter($this->allCategories, fn ($category) => $category['type'] === $type),
        );

        $this->selectedCategoryIds = $selected
            ? array_values(array_unique([...$this->selectedCategoryIds, ...$groupCategoryIds]))
            : array_values(array_diff($this->selectedCategoryIds, $groupCategoryIds));

        $this->loadTransactions();
    }

    public function hasActiveTransactionFilters(): bool
    {
        return trim($this->noteSearch) !== ''
            || count($this->selectedCategoryIds) !== count($this->allCategories);
    }

    public function setRecentTransactionPeriod(string $period): void
    {
        if (! in_array($period, ['daily', 'weekly', 'monthly', 'annually', 'custom'], true)) {
            return;
        }

        $this->recentTransactionPeriod = $period;

        if ($period === 'custom' && ($this->customPeriodStartDate === '' || $this->customPeriodEndDate === '')) {
            $referenceDate = $this->selectedReferenceDate();
            $this->customPeriodStartDate = $referenceDate->copy()->startOfMonth()->toDateString();
            $this->customPeriodEndDate = $referenceDate->copy()->endOfMonth()->toDateString();
        }

        $this->loadTransactions();
    }

    public function shiftRecentTransactionPeriod(int $direction): void
    {
        if ($this->recentTransactionPeriod === 'custom') {
            $range = $this->customPeriodRange();

            if ($range === null) {
                return;
            }

            [$startDate, $endDate] = $range;
            $periodLength = $startDate->diffInDays($endDate) + 1;
            $this->customPeriodStartDate = $startDate->addDays($direction * $periodLength)->toDateString();
            $this->customPeriodEndDate = $endDate->addDays($direction * $periodLength)->toDateString();
            $this->loadTransactions();

            return;
        }

        $referenceDate = $this->selectedReferenceDate();

        match ($this->recentTransactionPeriod) {
            'weekly' => $referenceDate->addWeeks($direction),
            'monthly' => $referenceDate->addMonthsNoOverflow($direction),
            'annually' => $referenceDate->addYearsNoOverflow($direction),
            default => $referenceDate->addDays($direction),
        };

        $this->referenceDate = $referenceDate->toDateString();
        $this->loadTransactions();
    }

    public function updatedCustomPeriodStartDate(): void
    {
        if ($this->recentTransactionPeriod === 'custom') {
            $this->loadTransactions();
        }
    }

    public function updatedCustomPeriodEndDate(): void
    {
        if ($this->recentTransactionPeriod === 'custom') {
            $this->loadTransactions();
        }
    }

    public function loadTransactions(): void
    {
        $referenceDate = $this->selectedReferenceDate();
        $range = match ($this->recentTransactionPeriod) {
            'weekly' => [$referenceDate->copy()->startOfWeek(), $referenceDate->copy()->endOfWeek()],
            'monthly' => [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()],
            'annually' => [$referenceDate->copy()->startOfYear(), $referenceDate->copy()->endOfYear()],
            'custom' => $this->customPeriodRange(),
            default => [$referenceDate->copy()->startOfDay(), $referenceDate->copy()->endOfDay()],
        };

        if ($range === null) {
            $this->transactions = [];
            $this->periodIncomeTotal = 0.0;
            $this->periodExpenseTotal = 0.0;

            return;
        }

        $noteSearch = trim($this->noteSearch);
        $filteredQuery = Transaction::query()
            ->whereBetween('datetime', $range)
            ->when($noteSearch !== '', fn ($query) => $query->whereLike('note', '%'.$noteSearch.'%'))
            ->whereIn('category_id', $this->selectedCategoryIds);

        $this->periodIncomeTotal = (float) (clone $filteredQuery)
            ->where('type', 'income')
            ->sum('amount');
        $this->periodExpenseTotal = (float) (clone $filteredQuery)
            ->where('type', 'expense')
            ->sum('amount');

        $this->transactions = $filteredQuery
            ->with('category')
            ->latest('datetime')
            ->get()
            ->toArray();
    }

    private function selectedReferenceDate(): Carbon
    {
        return $this->referenceDate
            ? Carbon::parse($this->referenceDate, 'Asia/Kuala_Lumpur')
            : now('Asia/Kuala_Lumpur');
    }

    private function customPeriodRange(): ?array
    {
        if (
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->customPeriodStartDate)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->customPeriodEndDate)
        ) {
            return null;
        }

        try {
            $startDate = Carbon::createFromFormat('!Y-m-d', $this->customPeriodStartDate, 'Asia/Kuala_Lumpur')->startOfDay();
            $endDate = Carbon::createFromFormat('!Y-m-d', $this->customPeriodEndDate, 'Asia/Kuala_Lumpur')->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $endDate->lt($startDate) ? null : [$startDate, $endDate];
    }

    public function customPeriodLabel(): string
    {
        $range = $this->customPeriodRange();

        if ($range === null) {
            return 'a custom period';
        }

        return $range[0]->format('j/n/Y').' - '.$range[1]->format('j/n/Y');
    }

    public function updatedType(): void
    {
        $this->filterCategoriesByType();
    }

    public function filterCategoriesByType(): void
    {
        $this->categories = array_values(array_filter($this->allCategories, fn ($cat) => ! $cat['type'] || $cat['type'] === $this->type));
        if (count($this->categories) > 0 && ! in_array($this->category_id, array_column($this->categories, 'id'))) {
            $this->category_id = (string) $this->categories[0]['id'];
        }
    }

    public function setInitialDatetime(): void
    {
        $now = now('Asia/Kuala_Lumpur');
        $this->datetime = $now->format('d/m/Y H:i');
    }

    public function edit(int $transactionId): void
    {
        $transaction = Transaction::with('category')->findOrFail($transactionId);

        $this->editingTransactionId = $transaction->id;
        $this->type = $transaction->type;
        $this->datetime = Carbon::parse($transaction->datetime)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i');
        $this->category_id = (string) $transaction->category_id;
        $this->amount = (string) $transaction->amount;
        $this->note = $transaction->note ?? '';

        $this->filterCategoriesByType();
        $this->reset('errors');
    }

    public function cancelEdit(): void
    {
        $this->editingTransactionId = null;
        $this->reset(['amount', 'note', 'errors']);
        $this->setInitialDatetime();
        $this->type = 'income';
        $this->filterCategoriesByType();
    }

    public function confirmDelete(int $transactionId): void
    {
        $this->deletingTransactionId = $transactionId;
    }

    public function cancelDelete(): void
    {
        $this->deletingTransactionId = null;
    }

    public function delete(): void
    {
        if ($this->deletingTransactionId === null) {
            return;
        }

        Transaction::query()->findOrFail($this->deletingTransactionId)->delete();

        $this->deletingTransactionId = null;
        $this->dispatch('transaction-toast', message: 'Transaction deleted successfully.');

        $counterService = app(CounterService::class);
        $this->loadData($counterService);
        $this->dispatch('counter-updated');
    }

    public function save(): void
    {
        $this->errors = [];

        // Validate
        $v = validator([
            'type' => $this->type,
            'datetime' => $this->datetime,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'note' => $this->note,
        ], [
            'type' => ['required', 'in:income,expense'],
            'datetime' => ['required', 'date_format:d/m/Y H:i'],
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            $this->errors = $v->errors()->toArray();
            return;
        }

        $validated = $v->validated();

        $category = Category::query()->findOrFail($validated['category_id']);

        if ($category->type && $category->type !== $validated['type']) {
            $this->errors['category_id'] = ['Category type does not match transaction type.'];
            return;
        }

        $validated['datetime'] = Carbon::createFromFormat('d/m/Y H:i', $validated['datetime'], 'Asia/Kuala_Lumpur');

        if ($this->editingTransactionId) {
            $transaction = Transaction::query()->findOrFail($this->editingTransactionId);
            $transaction->update($validated);
            $successMessage = 'Transaction updated successfully.';
            $this->editingTransactionId = null;
        } else {
            Transaction::query()->create($validated);
            $successMessage = 'Transaction recorded successfully.';
        }

        $this->dispatch('transaction-toast', message: $successMessage);

        $this->reset(['amount', 'note']);
        $this->setInitialDatetime();
        $this->type = 'income';
        $this->filterCategoriesByType();

        // Refresh data via CounterService
        $counterService = app(CounterService::class);
        $this->loadData($counterService);
        $this->dispatch('counter-updated');
    }
};
?>

<div class="transaction-log-grid">
    {{-- Summary cards --}}
    @php
        $netTransactions = (float) ($snapshot['current_month_net_transactions'] ?? 0);
        $netTransactionClass = $netTransactions > 0 ? 'text-success' : 'text-danger';
        $periodReferenceDate = $referenceDate
            ? \Carbon\Carbon::parse($referenceDate, 'Asia/Kuala_Lumpur')
            : now('Asia/Kuala_Lumpur');
        $periodLabel = match ($recentTransactionPeriod) {
            'weekly' => $periodReferenceDate->copy()->startOfWeek()->format('j/n').' - '.$periodReferenceDate->copy()->endOfWeek()->format('j/n'),
            'monthly' => $periodReferenceDate->format('F Y'),
            'annually' => 'the year '.$periodReferenceDate->format('Y'),
            'custom' => $this->customPeriodLabel(),
            default => $periodReferenceDate->format('j/n/Y'),
        };
    @endphp
    {{-- Log form --}}
    <div class="transaction-log-input-column">
        <div class="card data-card">
            <div class="card-header py-2">Inputs</div>
            <div class="card-body p-3">
            <h2 class="h6 mb-3">{{ $editingTransactionId ? 'Edit Transaction' : 'Log New Transaction' }}</h2>
            <form wire:submit="save">
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="type">Type</label>
                        <select wire:model.live="type" class="form-select" id="type" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        @if (isset($errors['type'])) <div class="text-danger">{{ implode(' ', $errors['type']) }}</div> @endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="datetime">Date &amp; Time</label>
                        <input wire:model="datetime" class="form-control" type="text" id="datetime" placeholder="DD/MM/YYYY HH:MM" required>
                        @if (isset($errors['datetime'])) <div class="text-danger">{{ implode(' ', $errors['datetime']) }}</div> @endif
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select wire:model="category_id" class="form-select" id="category_id" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors['category_id'])) <div class="text-danger">{{ implode(' ', $errors['category_id']) }}</div> @endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="amount">Amount</label>
                        <input wire:model="amount" class="form-control" type="number" min="0.01" step="0.01" id="amount" required>
                        @if (isset($errors['amount'])) <div class="text-danger">{{ implode(' ', $errors['amount']) }}</div> @endif
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="note">Note</label>
                    <textarea wire:model="note" class="form-control" id="note" rows="4"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-dark flex-grow-1" wire:loading.attr="disabled">
                        {{ $editingTransactionId ? 'Update Transaction' : 'Save Transaction' }}
                    </button>
                    @if ($editingTransactionId)
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelEdit">Cancel</button>
                        <button type="button" class="btn btn-outline-danger" wire:click="confirmDelete({{ $editingTransactionId }})">Delete</button>
                    @endif
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    @if ($deletingTransactionId)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center p-3">
                        <p class="mb-3">Are you sure you want to delete this transaction?</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-danger" wire:click="delete">Delete</button>
                            <button class="btn btn-secondary" wire:click="cancelDelete">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="transaction-log-output-column">
        <div class="card data-card counter-equation-panel mb-3">
            <div class="card-header py-2">Values for this month</div>
            <div class="card-body p-3">
                <div class="counter-equation-summary">
                    <div class="counter-equation-card">
                        <div class="counter-equation-item">
                            <div class="text-secondary">Starting Amount</div>
                            <div class="fw-semibold" id="startingAmountSummary">RM {{ number_format($snapshot['current_month_starting_amount'] ?? $snapshot['starting_amount'], 2) }}</div>
                        </div>
                    </div>
                    <div class="counter-equation-operator">+</div>
                    <div class="counter-equation-card">
                        <div class="counter-equation-item">
                            <div class="text-secondary">Net Transactions</div>
                            <div class="fw-semibold {{ $netTransactionClass }}" id="netTransactionsSummary">RM {{ number_format($netTransactions, 2) }}</div>
                        </div>
                    </div>
                    <div class="counter-equation-operator">+</div>
                    <div class="counter-equation-card">
                        <div class="counter-equation-item">
                            <div class="text-secondary">Unpaid Accrual</div>
                            <div class="fw-semibold" id="unpaidAccrualSummary">RM {{ number_format($snapshot['current_month_unpaid_accrual'], 2) }}</div>
                        </div>
                    </div>
                    <div class="counter-equation-operator">=</div>
                    <div class="counter-equation-card">
                        <div class="counter-equation-item">
                            <div class="text-secondary">Projected EOTM TFP</div>
                            <div class="fw-semibold" id="projectedEotmTfpSummary">RM {{ number_format($snapshot['projected_eotm_tfp'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent transactions table --}}
        <div class="card data-card">
            <div class="card-header transaction-output-header py-2">
                <span>Transactions over {{ $periodLabel }}</span>
                <div class="recent-transaction-controls">
                    <div
                        class="recent-transaction-search"
                        @if ($showNoteSearch) wire:click.outside="closeNoteSearch" @endif
                    >
                        <button
                            type="button"
                            class="recent-transaction-search-btn {{ $this->hasActiveTransactionFilters() ? 'active' : '' }}"
                            wire:click="toggleNoteSearch"
                            aria-label="Filter transactions"
                            aria-expanded="{{ $showNoteSearch ? 'true' : 'false' }}"
                            aria-controls="transactionFilterPanel"
                        >
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        </button>
                        @if ($showNoteSearch)
                            <button
                                type="button"
                                class="recent-transaction-filter-backdrop"
                                wire:click="closeNoteSearch"
                                aria-label="Close transaction filters"
                            ></button>
                            <div class="recent-transaction-search-panel" id="transactionFilterPanel">
                                <div class="recent-transaction-filter-panel-header">
                                    <span class="fw-semibold">Filter transactions</span>
                                    <button type="button" class="btn btn-sm btn-light" wire:click="closeNoteSearch" aria-label="Close transaction filters">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div>
                                    <label class="form-label small" for="noteSearch">Note</label>
                                    <input
                                        wire:model.live.debounce.300ms="noteSearch"
                                        class="form-control form-control-sm"
                                        type="search"
                                        id="noteSearch"
                                        placeholder="Search transaction notes"
                                        autocomplete="off"
                                        autofocus
                                    >
                                </div>
                                <div class="recent-transaction-category-groups">
                                    @foreach (['income' => 'Income', 'expense' => 'Expense'] as $categoryType => $categoryTypeLabel)
                                        <fieldset class="recent-transaction-category-group">
                                            <legend class="visually-hidden">{{ $categoryTypeLabel }} categories</legend>
                                            <div class="recent-transaction-category-group-header">
                                                <span class="recent-transaction-category-group-title">{{ $categoryTypeLabel }} categories</span>
                                                <span>
                                                    <button type="button" wire:click="setCategoryGroupSelection('{{ $categoryType }}', true)">All</button>
                                                    <span aria-hidden="true">/</span>
                                                    <button type="button" wire:click="setCategoryGroupSelection('{{ $categoryType }}', false)">None</button>
                                                </span>
                                            </div>
                                            <div class="recent-transaction-category-options">
                                                @foreach ($allCategories as $filterCategory)
                                                    @if ($filterCategory['type'] === $categoryType)
                                                        <div class="form-check">
                                                            <input
                                                                wire:model.live="selectedCategoryIds"
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                value="{{ $filterCategory['id'] }}"
                                                                id="transactionCategoryFilter{{ $filterCategory['id'] }}"
                                                            >
                                                            <label class="form-check-label" for="transactionCategoryFilter{{ $filterCategory['id'] }}">
                                                                {{ $filterCategory['name'] }}
                                                            </label>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary align-self-start" wire:click="resetTransactionFilters">
                                    Reset filters
                                </button>
                            </div>
                        @endif
                    </div>
                    <div class="recent-transaction-filters" role="group" aria-label="Recent transaction period">
                        <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'daily' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('daily')">Daily</button>
                        <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'weekly' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('weekly')">Weekly</button>
                        <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'monthly' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('monthly')">Monthly</button>
                        <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'annually' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('annually')">Annually</button>
                        <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'custom' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('custom')">Period</button>
                    </div>
                    @if ($recentTransactionPeriod === 'custom')
                        <div class="recent-transaction-period-selection">
                            <label class="visually-hidden" for="customPeriodStartDate">Period start date</label>
                            <input wire:model.live="customPeriodStartDate" id="customPeriodStartDate" class="form-control form-control-sm" type="date">
                            <span aria-hidden="true">to</span>
                            <label class="visually-hidden" for="customPeriodEndDate">Period end date</label>
                            <input wire:model.live="customPeriodEndDate" id="customPeriodEndDate" class="form-control form-control-sm" type="date">
                        </div>
                    @endif
                    <div class="recent-transaction-shift" role="group" aria-label="Navigate transaction period">
                        <button type="button" class="recent-transaction-shift-btn" wire:click="shiftRecentTransactionPeriod(-1)" aria-label="Previous period">&lt;</button>
                        <button type="button" class="recent-transaction-shift-btn" wire:click="shiftRecentTransactionPeriod(1)" aria-label="Next period">&gt;</button>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
            <div class="transaction-log-table-shell">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm align-middle mb-0 transaction-log-table">
                        <thead class="table-light">
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Category</th>
                            <th>Note</th>
                            <th class="text-end">Income</th>
                            <th class="text-end">Expense</th>
                        </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="fw-bold text-end px-2" style="background-color: #212529; color: white;">PERIOD TOTAL</td>
                                <td class="text-end">
                                    <span class="text-primary">RM {{ number_format($periodIncomeTotal, 2) }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="text-danger">RM {{ number_format($periodExpenseTotal, 2) }}</span>
                                </td>
                            </tr>
                        @forelse ($transactions as $tx)
                            <tr
                                wire:click="edit({{ $tx['id'] }})"
                                class="{{ $editingTransactionId === $tx['id'] ? 'table-active' : '' }}"
                            >
                                <td>{{ $tx['datetime'] ? \Carbon\Carbon::parse($tx['datetime'])->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') : '' }}</td>
                                <td>{{ $tx['category']['name'] ?? '' }}</td>
                                <td>{{ $tx['note'] ?? '' }}</td>
                                <td class="text-end">
                                    @if ($tx['type'] === 'income')
                                        <span class="text-primary">RM {{ number_format($tx['amount'], 2) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($tx['type'] === 'expense')
                                        <span class="text-danger">RM {{ number_format($tx['amount'], 2) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    {{ trim($noteSearch) !== '' ? 'No transactions match this note' : 'No transactions in this period' }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
