<?php

use App\Models\Setting;
use Livewire\Component;

new class extends Component
{
    public string $theme = 'light';

    public function mount(): void
    {
        $this->theme = Setting::getValue('theme', 'light');
    }

    public function toggleTheme(): void
    {
        $this->theme = $this->theme === 'light' ? 'dark' : 'light';
        Setting::setValue('theme', $this->theme);
        $this->dispatch('theme-changed', theme: $this->theme);
    }
};
?>

<button
    type="button"
    class="module-nav-row module-nav-action module-nav-theme-row"
    wire:click="toggleTheme"
    aria-label="Switch to {{ $theme === 'light' ? 'dark' : 'light' }} mode"
>
    <span class="module-nav-label">{{ $theme === 'light' ? 'Dark mode' : 'Light mode' }}</span>
    <span class="module-nav-btn">
        <i class="fa-solid {{ $theme === 'light' ? 'fa-moon' : 'fa-sun' }}" aria-hidden="true"></i>
    </span>
</button>
