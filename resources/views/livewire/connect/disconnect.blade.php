<?php

use App\Services\AuthSyncService;
use App\Services\CurrentProfile;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(CurrentProfile $current, AuthSyncService $authSync): void
    {
        $profile = $current->get();

        if ($profile) {
            $authSync->disconnect($profile);
        }

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>
