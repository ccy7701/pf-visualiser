<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Counter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @livewireStyles
    <style>
        body {
            background: #f8f9fa;
        }

        .counter-wrap {
            min-height: 48vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
        }

        .counter-value {
            font-size: clamp(2.8rem, 9vw, 6rem);
            font-weight: 700;
            letter-spacing: 0.02em;
            line-height: 1.1;
        }

        .counter-label {
            font-size: 0.95rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .data-card {
            border: 0;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body>
<div class="container py-4 py-md-5">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="counter-wrap mb-4">
        <div class="counter-label mb-2">Current Counter</div>
        <div id="counterValue" class="counter-value">RM {{ number_format($snapshot['counter'], 2) }}</div>
        <div class="text-muted mt-2">
            As of <span id="asOfValue">{{ $snapshot['as_of'] }}</span>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card data-card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Starting Amount</div>
                    <div class="h5 mb-0">RM {{ number_format($snapshot['starting_amount'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card data-card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Net Transactions</div>
                    <div class="h5 mb-0">RM {{ number_format($snapshot['net_transactions'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card data-card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Accrued Salary</div>
                    <div id="accruedSalaryValue" class="h5 mb-0">RM {{ number_format($snapshot['accrued_salary'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card data-card">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Log Transaction</h2>
                    <form method="POST" action="{{ route('transactions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="type">Type</label>
                            <select class="form-select" name="type" id="type" required>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="datetime">Date and Time</label>
                            <input class="form-control" type="datetime-local" name="datetime" id="datetime" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select" name="category_id" id="category_id" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" data-type="{{ $category->type }}">{{ ucfirst($category->type ?? 'general') }} - {{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="amount">Amount</label>
                            <input class="form-control" type="number" min="0.01" step="0.01" name="amount" id="amount" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="note">Note</label>
                            <textarea class="form-control" name="note" id="note" rows="3"></textarea>
                        </div>

                        <button class="btn btn-dark w-100" type="submit">Save Transaction</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card data-card">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Recent Transactions</h2>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Date and Time</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($transactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->datetime?->format('Y-m-d H:i') }}</td>
                                    <td>{{ ucfirst($transaction->type) }}</td>
                                    <td>{{ $transaction->category?->name }}</td>
                                    <td class="text-end">RM {{ number_format($transaction->amount, 2) }}</td>
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
    </div>

    <livewire:workday-calendar />
</div>

<script>
    const counterElement = document.getElementById('counterValue');
    const asOfElement = document.getElementById('asOfValue');
    const accruedSalaryElement = document.getElementById('accruedSalaryValue');
    const datetimeInput = document.getElementById('datetime');
    const typeInput = document.getElementById('type');
    const categoryInput = document.getElementById('category_id');

    let currentValue = Number({{ $snapshot['counter'] }});
    let accruedSalaryValue = Number({{ $snapshot['accrued_salary'] }});
    let incrementPerSecond = Number({{ $snapshot['increment_per_second'] }});

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function renderCounter() {
        counterElement.textContent = `RM ${formatter.format(currentValue)}`;
    }

    function renderAccruedSalary() {
        accruedSalaryElement.textContent = `RM ${formatter.format(accruedSalaryValue)}`;
    }

    function filterCategories() {
        if (!typeInput || !categoryInput) {
            return;
        }

        const selectedType = typeInput.value;
        let firstVisible = null;

        Array.from(categoryInput.options).forEach((option) => {
            const isVisible = !option.dataset.type || option.dataset.type === selectedType;
            option.hidden = !isVisible;

            if (isVisible && !firstVisible) {
                firstVisible = option;
            }
        });

        if (firstVisible) {
            categoryInput.value = firstVisible.value;
        }
    }

    function tick() {
        currentValue += incrementPerSecond;
        accruedSalaryValue += incrementPerSecond;

        renderCounter();
        renderAccruedSalary();
    }

    async function syncSnapshot() {
        const response = await fetch('{{ route('counter.snapshot') }}', {
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        currentValue = Number(data.counter);
        accruedSalaryValue = Number(data.accrued_salary);
        incrementPerSecond = Number(data.increment_per_second);

        renderCounter();
        renderAccruedSalary();
        asOfElement.textContent = data.as_of;
    }

    if (datetimeInput) {
        const now = new Date();
        const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        datetimeInput.value = local;
    }

    if (typeInput) {
        typeInput.addEventListener('change', filterCategories);
    }

    filterCategories();
    renderCounter();
    renderAccruedSalary();
    setInterval(tick, 1000);
    setInterval(syncSnapshot, 60000);
</script>
@livewireScripts
</body>
</html>
