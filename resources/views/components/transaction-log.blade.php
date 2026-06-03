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

        $this->transactions = Transaction::query()
            ->with('category')
            ->latest('datetime')
            ->limit(20)
            ->get()
            ->toArray();

        $this->allCategories = Category::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->setInitialDatetime();
        $this->filterCategoriesByType();
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

<div>
    {{-- Summary cards --}}
    <div class="row g-2 mb-3">
        <div class="col-4">
            <div class="card data-card h-100">
                <div class="card-body text-center p-2">
                    <div class="text-secondary" style="font-size:0.7rem;">Starting Amount</div>
                    <div class="fw-semibold" style="font-size:0.85rem;">RM {{ number_format($snapshot['starting_amount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card data-card h-100">
                <div class="card-body text-center p-2">
                    <div class="text-secondary" style="font-size:0.7rem;">Unpaid Accrual</div>
                    <div class="fw-semibold" style="font-size:0.85rem;" id="accruedSalarySummary">RM {{ number_format($snapshot['accrued_salary'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card data-card h-100">
                <div class="card-body text-center p-2">
                    <div class="text-secondary" style="font-size:0.7rem;">Net Transactions</div>
                    <div class="fw-semibold" style="font-size:0.85rem;">RM {{ number_format($snapshot['net_transactions'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Log form --}}
    <div class="card data-card mb-3">
        <div class="card-body p-2">
            <h2 class="h6 mb-2">{{ $editingTransactionId ? 'Edit Transaction' : 'Log New Transaction' }}</h2>
            <form wire:submit="save">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;" for="type">Type</label>
                        <select wire:model.live="type" class="form-select form-select-sm" id="type" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        @if (isset($errors['type'])) <div class="text-danger" style="font-size:0.7rem;">{{ implode(' ', $errors['type']) }}</div> @endif
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;" for="datetime">Date &amp; Time</label>
                        <input wire:model="datetime" class="form-control form-control-sm" type="text" id="datetime" placeholder="DD/MM/YYYY HH:MM" required>
                        @if (isset($errors['datetime'])) <div class="text-danger" style="font-size:0.7rem;">{{ implode(' ', $errors['datetime']) }}</div> @endif
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;" for="category_id">Category</label>
                        <select wire:model="category_id" class="form-select form-select-sm" id="category_id" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors['category_id'])) <div class="text-danger" style="font-size:0.7rem;">{{ implode(' ', $errors['category_id']) }}</div> @endif
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;" for="amount">Amount</label>
                        <input wire:model="amount" class="form-control form-control-sm" type="number" min="0.01" step="0.01" id="amount" required>
                        @if (isset($errors['amount'])) <div class="text-danger" style="font-size:0.7rem;">{{ implode(' ', $errors['amount']) }}</div> @endif
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" style="font-size:0.75rem;" for="note">Note</label>
                    <textarea wire:model="note" class="form-control form-control-sm" id="note" rows="2"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-dark btn-sm flex-grow-1" wire:loading.attr="disabled">
                        {{ $editingTransactionId ? 'Update Transaction' : 'Save Transaction' }}
                    </button>
                    @if ($editingTransactionId)
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cancelEdit">Cancel</button>
                    @endif
                </div>
            </form>
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
                            <button class="btn btn-danger btn-sm" wire:click="delete">Delete</button>
                            <button class="btn btn-secondary btn-sm" wire:click="cancelDelete">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Recent transactions table --}}
    <div class="card data-card">
        <div class="card-body p-2">
            <h2 class="h6 mb-2">Recent Transactions</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0" style="font-size:0.8rem;">
                    <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Category</th>
                        <th>Note</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>{{ $tx['datetime'] ? \Carbon\Carbon::parse($tx['datetime'])->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') : '' }}</td>
                            <td>{{ $tx['category']['name'] ?? '' }}</td>
                            <td>{{ $tx['note'] ?? '' }}</td>
                            <td class="text-end">
                                @if ($tx['type'] === 'income')
                                    <span class="text-primary">RM {{ number_format($tx['amount'], 2) }}</span>
                                @else
                                    <span class="text-danger">RM {{ number_format($tx['amount'], 2) }}</span>
                                @endif
                            </td>
                            <td class="text-center" style="white-space: nowrap;">
                                <button class="btn btn-sm py-0 px-1 border-0 me-1" wire:click="edit({{ $tx['id'] }})" title="Edit" style="color: #000;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5-.5-.5z"/>
                                    </svg>
                                </button>
                                <button class="btn btn-sm py-0 px-1 border-0" wire:click="confirmDelete({{ $tx['id'] }})" title="Delete" style="color: #dc3545;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No transactions yet</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
