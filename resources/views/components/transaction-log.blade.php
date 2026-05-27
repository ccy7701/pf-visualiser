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
    ];

    public array $transactions = [];

    public array $categories = [];

    public string $type = 'income';

    public string $datetime = '';

    public string $category_id = '';

    public string $amount = '';

    public string $note = '';

    public string $statusMessage = '';

    public array $errors = [];

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

        $this->categories = Category::query()
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
        $filtered = array_values(array_filter($this->categories, fn ($cat) => ! $cat['type'] || $cat['type'] === $this->type));

        if (count($filtered) > 0 && ! in_array($this->category_id, array_column($filtered, 'id'))) {
            $this->category_id = (string) $filtered[0]['id'];
        }
    }

    public function setInitialDatetime(): void
    {
        $now = now('Asia/Kuala_Lumpur');
        $this->datetime = $now->format('d/m/Y H:i');
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

        Transaction::query()->create($validated);

        $this->reset(['amount', 'note']);
        $this->statusMessage = 'Transaction recorded successfully.';
        $this->setInitialDatetime();

        // Refresh data via CounterService
        $counterService = app(CounterService::class);
        $this->loadData($counterService);
    }
};
?>

<div>
    @if ($statusMessage)
        <div class="alert alert-success py-1 px-2 small mb-3">{{ $statusMessage }}</div>
    @endif

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
                    <div class="text-secondary" style="font-size:0.7rem;">Net Transactions</div>
                    <div class="fw-semibold" style="font-size:0.85rem;">RM {{ number_format($snapshot['net_transactions'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card data-card h-100">
                <div class="card-body text-center p-2">
                    <div class="text-secondary" style="font-size:0.7rem;">Accrued Salary</div>
                    <div class="fw-semibold" style="font-size:0.85rem;">RM {{ number_format($snapshot['accrued_salary'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Log form --}}
    <div class="card data-card mb-3">
        <div class="card-body p-2">
            <h2 class="h6 mb-2">Log New Transaction</h2>
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
                                <option value="{{ $category['id'] }}">{{ ucfirst($category['type'] ?? 'general') }} - {{ $category['name'] }}</option>
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
                <button type="submit" class="btn btn-dark btn-sm w-100" wire:loading.attr="disabled">Save Transaction</button>
            </form>
        </div>
    </div>

    {{-- Recent transactions table --}}
    <div class="card data-card">
        <div class="card-body p-2">
            <h2 class="h6 mb-2">Recent Transactions</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0" style="font-size:0.8rem;">
                    <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th class="text-end">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>{{ $tx['datetime'] ? \Carbon\Carbon::parse($tx['datetime'])->format('d/m/Y H:i') : '' }}</td>
                            <td>{{ ucfirst($tx['type']) }}</td>
                            <td>{{ $tx['category']['name'] ?? '' }}</td>
                            <td class="text-end">RM {{ number_format($tx['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No transactions yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
