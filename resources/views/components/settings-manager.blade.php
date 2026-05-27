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
    }
};
?>

<div>
    <div class="card data-card">
        <div class="card-body p-2">
            <div class="mb-2">
                <label class="form-label" style="font-size:0.75rem;">Starting Amount (RM)</label>
                <input wire:model="startingAmount" class="form-control form-control-sm" min="0" step="0.01" type="number">
                @error('startingAmount')
                    <div class="text-danger" style="font-size:0.7rem;">{{ $message }}</div>
                @enderror
            </div>

            <button wire:click="save" class="btn btn-dark btn-sm w-100" type="button" wire:loading.attr="disabled">Save Settings</button>

            @if ($statusMessage)
                <div class="alert alert-success py-1 px-2 mt-2 mb-0" style="font-size:0.75rem;">
                    {{ $statusMessage }}
                </div>
            @endif
        </div>
    </div>
</div>