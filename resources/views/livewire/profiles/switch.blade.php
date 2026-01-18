<?php

use App\Services\CurrentProfile;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(CurrentProfile $current): void
    {
        $current->forget();

        $this->redirect(route('profiles.index', absolute: false), navigate: true);
    }
}; ?>
