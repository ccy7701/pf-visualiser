<?php

use App\Models\Setting;
use Livewire\Component;

new class extends Component
{
    public string $startingAmount = '0.00';
    public string $statusMessage = '';

    public function mount(): void
    {
        $this->startingAmount = (string) Setting::getValue('starting_amount', '0.00');
    }

    public function save(): void
    {
        $this->validate([
            'startingAmount' => ['required', 'numeric', 'min:0'],
        ]);

        Setting::setValue('starting_amount', number_format((float) $this->startingAmount, 2, '.', ''));

        $this->statusMessage = 'Settings saved successfully.';
        $this->dispatch('counter-updated');
    }
};
?>

<div>
    @if ($statusMessage)
        <div class="alert alert-success py-2 px-2 mb-3" style="font-size:0.75rem;">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="card data-card mb-3">
        <div class="card-body p-2">
            {{-- Starting Amount row --}}
            <div class="row align-items-center g-2 mb-2">
                <div class="col-8">
                    <label class="form-label" style="font-size:0.85rem; margin-bottom:0;">Starting Amount (RM)</label>
                </div>
                <div class="col-4">
                    <input wire:model="startingAmount" class="form-control form-control-sm text-start" min="0" step="0.01" type="number">
                    @error('startingAmount')
                        <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button wire:click="save" class="btn btn-dark btn-sm w-100" type="button" wire:loading.attr="disabled">Save Settings</button>
        </div>
    </div>
</div>
