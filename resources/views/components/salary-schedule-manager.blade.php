<?php

use App\Models\SalarySchedule;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public array $schedules = [];

    public string $newEffectiveFrom = '';
    public string $newEffectiveUntil = '';
    public string $newMonthlyNetSalary = '';
    public string $newNotes = '';

    public ?int $editingId = null;
    public string $editEffectiveFrom = '';
    public string $editEffectiveUntil = '';
    public string $editMonthlyNetSalary = '';
    public string $editNotes = '';

    public function mount(): void
    {
        $this->loadSchedules();
    }

    public function loadSchedules(): void
    {
        $this->schedules = SalarySchedule::query()
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (SalarySchedule $schedule) => [
                'id' => $schedule->id,
                'effective_from' => $schedule->effective_from?->format('d/m/Y'),
                'effective_until' => $schedule->effective_until?->format('d/m/Y') ?? '—',
                'monthly_net_salary' => number_format((float) $schedule->monthly_net_salary, 2),
                'notes' => $schedule->notes,
            ])
            ->toArray();
    }

    public function addSchedule(): void
    {
        $this->validate([
            'newEffectiveFrom' => ['required', 'date_format:d/m/Y'],
            'newEffectiveUntil' => ['nullable', 'date_format:d/m/Y'],
            'newMonthlyNetSalary' => ['required', 'numeric', 'min:0.01'],
            'newNotes' => ['nullable', 'string'],
        ]);

        SalarySchedule::query()->create([
            'effective_from' => Carbon::createFromFormat('d/m/Y', $this->newEffectiveFrom, 'Asia/Kuala_Lumpur')->toDateString(),
            'effective_until' => $this->newEffectiveUntil
                ? Carbon::createFromFormat('d/m/Y', $this->newEffectiveUntil, 'Asia/Kuala_Lumpur')->toDateString()
                : null,
            'monthly_net_salary' => $this->newMonthlyNetSalary,
            'notes' => $this->newNotes ?: null,
        ]);

        $this->reset(['newEffectiveFrom', 'newEffectiveUntil', 'newMonthlyNetSalary', 'newNotes']);
        $this->loadSchedules();
        $this->dispatch('counter-updated');
    }

    public function startEdit(int $id): void
    {
        $schedule = SalarySchedule::query()->findOrFail($id);

        $this->editingId = $schedule->id;
        $this->editEffectiveFrom = $schedule->effective_from?->format('d/m/Y') ?? '';
        $this->editEffectiveUntil = $schedule->effective_until?->format('d/m/Y') ?? '';
        $this->editMonthlyNetSalary = (string) $schedule->monthly_net_salary;
        $this->editNotes = $schedule->notes ?? '';
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'editEffectiveFrom' => ['required', 'date_format:d/m/Y'],
            'editEffectiveUntil' => ['nullable', 'date_format:d/m/Y'],
            'editMonthlyNetSalary' => ['required', 'numeric', 'min:0.01'],
            'editNotes' => ['nullable', 'string'],
        ]);

        SalarySchedule::query()
            ->whereKey($this->editingId)
            ->update([
                'effective_from' => Carbon::createFromFormat('d/m/Y', $this->editEffectiveFrom, 'Asia/Kuala_Lumpur')->toDateString(),
                'effective_until' => $this->editEffectiveUntil
                    ? Carbon::createFromFormat('d/m/Y', $this->editEffectiveUntil, 'Asia/Kuala_Lumpur')->toDateString()
                    : null,
                'monthly_net_salary' => $this->editMonthlyNetSalary,
                'notes' => $this->editNotes ?: null,
            ]);

        $this->cancelEdit();
        $this->loadSchedules();
        $this->dispatch('counter-updated');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editEffectiveFrom', 'editEffectiveUntil', 'editMonthlyNetSalary', 'editNotes']);
    }

    public function deleteSchedule(int $id): void
    {
        SalarySchedule::query()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        $this->loadSchedules();
        $this->dispatch('counter-updated');
    }
};
?>

<div>
    <div class="card data-card mb-3">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:0.78rem;">
                    <thead>
                    <tr>
                        <th>From</th>
                        <th>Until</th>
                        <th>Note</th>
                        <th class="text-end">Salary (RM)</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td>{{ $schedule['effective_from'] }}</td>
                            <td>{{ $schedule['effective_until'] }}</td>
                            <td>{{ $schedule['notes'] }}</td>
                            <td class="text-end">{{ $schedule['monthly_net_salary'] }}</td>
                            <td class="text-end" style="white-space: nowrap;">
                                <button class="btn btn-sm py-0 px-1 border-0 me-1" wire:click="startEdit({{ $schedule['id'] }})" title="Edit" style="color: #000;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5-.5-.5z"/>
                                    </svg>
                                </button>
                                <button class="btn btn-sm py-0 px-1 border-0" wire:click="deleteSchedule({{ $schedule['id'] }})" title="Delete" style="color: #dc3545;" wire:confirm="Delete this schedule?">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                            <td colspan="4" class="text-center text-muted">No salary schedules found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($editingId)
        <div class="card data-card mb-3">
            <div class="card-body p-2">
                <h3 class="small fw-semibold mb-2">Edit Schedule #{{ $editingId }}</h3>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label for="editEffectiveFrom" class="form-label" style="font-size:0.75rem;">Effective From</label>
                        <input id="editEffectiveFrom" wire:model="editEffectiveFrom" class="form-control form-control-sm datepicker" placeholder="DD/MM/YYYY" type="text" data-target="editEffectiveFrom" autocomplete="off">
                        @error('editEffectiveFrom') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="editEffectiveUntil" class="form-label" style="font-size:0.75rem;">Effective Until</label>
                        <input id="editEffectiveUntil" wire:model="editEffectiveUntil" class="form-control form-control-sm datepicker" placeholder="DD/MM/YYYY" type="text" data-target="editEffectiveUntil" autocomplete="off">
                        @error('editEffectiveUntil') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label for="editMonthlyNetSalary" class="form-label" style="font-size:0.75rem;">Monthly Net Salary</label>
                        <input id="editMonthlyNetSalary" wire:model="editMonthlyNetSalary" class="form-control form-control-sm" min="0.01" step="0.01" type="number">
                        @error('editMonthlyNetSalary') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="editNotes" class="form-label" style="font-size:0.75rem;">Notes</label>
                        <input id="editNotes" wire:model="editNotes" class="form-control form-control-sm" type="text">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button wire:click="saveEdit" class="btn btn-dark btn-sm flex-fill" type="button" wire:loading.attr="disabled">Save Changes</button>
                    <button wire:click="cancelEdit" class="btn btn-outline-secondary btn-sm flex-fill" type="button">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    <div class="card data-card mb-3">
        <div class="card-body p-2">
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label for="newEffectiveFrom" class="form-label" style="font-size:0.75rem;">Effective From</label>
                    <input id="newEffectiveFrom" wire:model="newEffectiveFrom" class="form-control form-control-sm" placeholder="DD/MM/YYYY" type="text">
                    @error('newEffectiveFrom') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-6">
                    <label for="newEffectiveUntil" class="form-label" style="font-size:0.75rem;">Effective Until</label>
                    <input id="newEffectiveUntil" wire:model="newEffectiveUntil" class="form-control form-control-sm datepicker" placeholder="DD/MM/YYYY" type="text" data-target="newEffectiveUntil" autocomplete="off">
                    @error('newEffectiveUntil') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label for="newMonthlyNetSalary" class="form-label" style="font-size:0.75rem;">Monthly Net Salary</label>
                    <input id="newMonthlyNetSalary" wire:model="newMonthlyNetSalary" class="form-control form-control-sm" min="0.01" step="0.01" type="number">
                    @error('newMonthlyNetSalary') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-6">
                    <label for="newNotes" class="form-label" style="font-size:0.75rem;">Notes</label>
                    <input id="newNotes" wire:model="newNotes" class="form-control form-control-sm" type="text">
                </div>
            </div>

            <div class="row justify-content-center mt-3">
                <div class="col-4">
                    <button wire:click="addSchedule" class="btn btn-dark btn-sm w-100" type="button" wire:loading.attr="disabled">Add Schedule</button>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    function initSalaryScheduleDatepickers() {
        if (typeof flatpickr === 'undefined') return;

        $wire.$el.querySelectorAll('.datepicker:not(.flatpickr-input)').forEach((el) => {
            const target = el.dataset.target;
            if (!target) return;

            flatpickr(el, {
                dateFormat: 'd/m/Y',
                allowInput: true,
                onChange: function (selectedDates, dateStr) {
                    $wire.set(target, dateStr);
                },
            });
        });
    }

    initSalaryScheduleDatepickers();

    Livewire.hook('morph.updated', ({ component }) => {
        if (component.id !== $wire.$id) return;

        initSalaryScheduleDatepickers();
    });
</script>
@endscript
