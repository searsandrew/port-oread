<?php

use App\Models\User;
use App\Services\CurrentProfile;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.auth.card');

new class extends Component
{
    public function mount(CurrentProfile $current): void
    {
        $profiles = User::query()->orderBy('name')->get();

        if ($profiles->count() === 1) {
            $current->set($profiles->first());
            $this->redirect(route('dashboard', absolute: false), navigate: true);
        }
    }

    public function select(string $id, CurrentProfile $current): void
    {
        $profile = User::findOrFail($id);
        $current->set($profile);

        // If any legacy global token exists, move it under this profile
        app(\App\Services\LocalStoreService::class)->migrateLegacyAuthToProfile($profile->id);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function with(): array
    {
        return [
            'profiles' => User::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Choose a Profile')" :description="__('Select who’s playing on this device.')" />

    <div class="flex flex-col gap-3">
        @forelse ($profiles as $profile)
            <button type="button" wire:click="select('{{ $profile->id }}')" class="w-full text-left rounded-xl border border-zinc-200 dark:border-zinc-800 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $profile->name }}
                    </div>
                    <div class="text-sm text-zinc-500">
                        {{ $profile->email ?? __('Offline profile') }}
                    </div>
                </div>

                @if (!empty($profile->tiber_user_id))
                    <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                        {{ __('Connected') }}
                    </div>
                @else
                    <div class="mt-1 text-xs text-zinc-500">
                        {{ __('Not connected') }}
                    </div>
                @endif
            </button>
        @empty
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('No profiles yet. Create one to start playing.') }}
            </div>
        @endforelse
    </div>

    <flux:button :href="route('profiles.create')" wire:navigate variant="primary" class="w-full">
        {{ __('Create Profile') }}
    </flux:button>
</div>
