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
    ];

    public array $transactions = [];

    public array $categories = [];

    public array $allCategories = [];

    public string $recentTransactionPeriod = 'today';

    public string $type = 'income';

    public string $datetime = '';

    public string $category_id = '';

    public string $amount = '';

    public string $note = '';

    public string $statusMessage = '';

    public array $errors = [];

    public ?int $editingTransactionId = null;

    public ?int $deletingTransactionId = null;

    public function mount(CounterService $counterService): void
    {
        $this->loadData($counterService);
    }

    public function loadData(CounterService $counterService): void
    {
        $this->snapshot = $counterService->snapshot();

        $this->loadTransactions();

        $this->allCategories = Category::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->setInitialDatetime();
        $this->filterCategoriesByType();
    }

    public function updatedRecentTransactionPeriod(): void
    {
        $this->loadTransactions();
    }

    public function setRecentTransactionPeriod(string $period): void
    {
        if (! in_array($period, ['today', 'this_week', 'this_month'], true)) {
            return;
        }

        $this->recentTransactionPeriod = $period;
        $this->loadTransactions();
    }

    public function loadTransactions(): void
    {
        $now = now('Asia/Kuala_Lumpur');
        $range = match ($this->recentTransactionPeriod) {
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $this->transactions = Transaction::query()
            ->with('category')
            ->whereBetween('datetime', $range)
            ->latest('datetime')
            ->get()
            ->toArray();
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
        $this->reset('statusMessage', 'errors');
    }

    public function cancelEdit(): void
    {
        $this->editingTransactionId = null;
        $this->reset(['amount', 'note', 'statusMessage', 'errors']);
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
        $this->statusMessage = 'Transaction deleted successfully.';

        $counterService = app(CounterService::class);
        $this->loadData($counterService);
        $this->dispatch('counter-updated');
    }

    public function save(): void
    {
        $this->reset('statusMessage');
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
            $this->statusMessage = 'Transaction updated successfully.';
            $this->editingTransactionId = null;
        } else {
            Transaction::query()->create($validated);
            $this->statusMessage = 'Transaction recorded successfully.';
        }

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
        $netTransactions = (float) ($snapshot['net_transactions'] ?? 0);
        $netTransactionClass = $netTransactions > 0 ? 'text-success' : 'text-danger';
        $periodLabel = match ($recentTransactionPeriod) {
            'this_week' => 'this week',
            'this_month' => 'this month',
            default => 'today',
        };
    @endphp
    {{-- Log form --}}
    <div class="transaction-log-input-column">
        <div class="card data-card">
            <div class="card-header">Inputs</div>
            <div class="card-body p-3">
            <h2 class="h6 mb-3">{{ $editingTransactionId ? 'Edit Transaction' : 'Log New Transaction' }}</h2>
            @if ($statusMessage)
                <div class="alert alert-success py-2 mb-3">{{ $statusMessage }}</div>
            @endif
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
        <div class="counter-equation-summary mb-3">
            <div class="counter-equation-card">
                <div class="card data-card h-100">
                    <div class="card-body text-center p-2">
                        <div class="text-secondary">Starting Amount</div>
                        <div class="fw-semibold">RM {{ number_format($snapshot['starting_amount'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="counter-equation-operator">+</div>
            <div class="counter-equation-card">
                <div class="card data-card h-100">
                    <div class="card-body text-center p-2">
                        <div class="text-secondary">Net Transactions</div>
                        <div class="fw-semibold {{ $netTransactionClass }}">RM {{ number_format($netTransactions, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="counter-equation-operator">+</div>
            <div class="counter-equation-card">
                <div class="card data-card h-100">
                    <div class="card-body text-center p-2">
                        <div class="text-secondary">Unpaid Accrual</div>
                        <div class="fw-semibold" id="accruedSalarySummary">RM {{ number_format($snapshot['accrued_salary'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="counter-equation-operator">=</div>
            <div class="counter-equation-card">
                <div class="card data-card h-100">
                    <div class="card-body text-center p-2">
                        <div class="text-secondary">Projected Amount</div>
                        <div class="fw-semibold" id="dynamicTotalSummary">RM {{ number_format($snapshot['expected_counter'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent transactions table --}}
        <div class="card data-card">
            <div class="card-header transaction-output-header">
                <span>Transactions over {{ $periodLabel }}</span>
                <div class="recent-transaction-filters" role="group" aria-label="Recent transaction period">
                    <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'today' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('today')">Today</button>
                    <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'this_week' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('this_week')">This Week</button>
                    <button type="button" class="recent-transaction-filter {{ $recentTransactionPeriod === 'this_month' ? 'active' : '' }}" wire:click="setRecentTransactionPeriod('this_month')">This Month</button>
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
                                <td colspan="5" class="text-center text-muted py-3">No transactions in this period</td>
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
