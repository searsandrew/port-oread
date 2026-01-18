<?php

use App\Services\CurrentProfile;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component
{
    public function with(CurrentProfile $current): array
    {
        return [
            'profile' => $current->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Settings</h1>
            <p class="text-sm text-zinc-500">Device and profile preferences.</p>
        </div>

        <flux:link :href="route('dashboard')" wire:navigate>Back</flux:link>
    </div>

    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 p-4">
        <div class="text-sm text-zinc-500">Active profile</div>
        <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
            {{ $profile?->name ?? 'Unknown' }}
        </div>
        <div class="text-sm text-zinc-500">
            {{ $profile?->email ?? 'Offline profile' }}
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 p-4">
        <div class="font-medium text-zinc-900 dark:text-zinc-100">More settings coming soon</div>
        <div class="text-sm text-zinc-500">Biometrics, audio, accessibility, etc.</div>
    </div>
</div>
