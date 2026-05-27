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
                        <th class="text-end">Salary (RM)</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td>{{ $schedule['effective_from'] }}</td>
                            <td>{{ $schedule['effective_until'] }}</td>
                            <td class="text-end">{{ $schedule['monthly_net_salary'] }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button wire:click="startEdit({{ $schedule['id'] }})" class="btn btn-outline-primary" type="button">Edit</button>
                                    <button wire:click="deleteSchedule({{ $schedule['id'] }})" class="btn btn-outline-danger" type="button" wire:confirm="Delete this schedule?">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No salary schedules found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card data-card mb-3">
        <div class="card-body p-2">
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label" style="font-size:0.75rem;">Effective From</label>
                    <input wire:model="newEffectiveFrom" class="form-control form-control-sm" placeholder="DD/MM/YYYY" type="text">
                    @error('newEffectiveFrom') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-6">
                    <label class="form-label" style="font-size:0.75rem;">Effective Until</label>
                    <input wire:model="newEffectiveUntil" class="form-control form-control-sm" placeholder="DD/MM/YYYY" type="text">
                    @error('newEffectiveUntil') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label" style="font-size:0.75rem;">Monthly Net Salary</label>
                    <input wire:model="newMonthlyNetSalary" class="form-control form-control-sm" min="0.01" step="0.01" type="number">
                    @error('newMonthlyNetSalary') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-6">
                    <label class="form-label" style="font-size:0.75rem;">Notes</label>
                    <input wire:model="newNotes" class="form-control form-control-sm" type="text">
                </div>
            </div>

            <button wire:click="addSchedule" class="btn btn-dark btn-sm w-100" type="button" wire:loading.attr="disabled">Add Schedule</button>
        </div>
    </div>

    @if ($editingId)
        <div class="card data-card mb-3">
            <div class="card-body p-2">
                <h3 class="small fw-semibold mb-2">Edit Schedule #{{ $editingId }}</h3>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;">Effective From</label>
                        <input wire:model="editEffectiveFrom" class="form-control form-control-sm" placeholder="DD/MM/YYYY" type="text">
                        @error('editEffectiveFrom') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;">Effective Until</label>
                        <input wire:model="editEffectiveUntil" class="form-control form-control-sm" placeholder="DD/MM/YYYY" type="text">
                        @error('editEffectiveUntil') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;">Monthly Net Salary</label>
                        <input wire:model="editMonthlyNetSalary" class="form-control form-control-sm" min="0.01" step="0.01" type="number">
                        @error('editMonthlyNetSalary') <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:0.75rem;">Notes</label>
                        <input wire:model="editNotes" class="form-control form-control-sm" type="text">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button wire:click="saveEdit" class="btn btn-dark btn-sm flex-fill" type="button" wire:loading.attr="disabled">Save Changes</button>
                    <button wire:click="cancelEdit" class="btn btn-outline-secondary btn-sm flex-fill" type="button">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>