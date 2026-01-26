<?php

use App\Models\User;
use App\Services\CurrentProfile;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.volt-auth');

new class extends Component
{
    public bool $on = false;

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
        $current = app(\App\Services\CurrentProfile::class);

        $currentUser = null;
        if (method_exists($current, 'get')) {
            $currentUser = $current->get();
        } elseif (method_exists($current, 'current')) {
            $currentUser = $current->current();
        } elseif (method_exists($current, 'user')) {
            $currentUser = $current->user();
        }

        return [
            'profiles' => User::query()->orderBy('name')->get(),
            'currentId' => $currentUser?->id,
        ];
    }
}; ?>

<div class="p-6">
    <native:top-bar :title="__('Profile')" :subtitle="__('Select who\'s playing on this device.')">
        <native:top-bar-action
            id="dashboard"
            label="Dashboard"
            icon="home"
            :url="route('dashboard')"
        />
        <native:top-bar-action
            id="settings"
            icon="settings"
            label="Settings"
            url="route('settings.index')"
        />
    </native:top-bar>

    <div class="flex flex-col gap-3">
        @forelse ($profiles as $profile)
            @php $isCurrent = !empty($currentId) && $currentId === $profile->id; @endphp

            <x-button click="select('{{ $profile->id }}')" align="start" size="tile" :on="$isCurrent" :disabled="$isCurrent">
                <div class="flex w-full items-center gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-white">
                            {{ $profile->name }}
                        </div>

                        @if ($isCurrent)
                            <div class="mt-1 text-xs text-amber-300/90">
                                {{ __('Selected') }}
                            </div>
                        @elseif (!empty($profile->tiber_user_id))
                            <div class="mt-1 text-xs text-emerald-400">
                                {{ __('Connected') }}
                            </div>
                        @else
                            <div class="mt-1 text-xs text-generic-light opacity-80">
                                {{ __('Not connected') }}
                            </div>
                        @endif
                    </div>

                    <flux:avatar :user="$profile" size="lg" circle class="shrink-0 ml-auto" />
                </div>
            </x-button>
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
