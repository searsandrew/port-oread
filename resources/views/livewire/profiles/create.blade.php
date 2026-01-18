<?php

use App\Models\User;
use App\Services\CurrentProfile;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.auth.card');

new class extends Component
{
    public string $name = '';
    public ?string $email = null;

    public function create(CurrentProfile $current): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $profile = User::create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            // random password; we are not using local password auth
            'password' => Str::random(64),
        ]);

        $current->set($profile);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create a Profile')" :description="__('This stays on your device. You can connect an account later.')" />

    <form wire:submit.prevent="create" method="POST" class="flex flex-col gap-6">
        @csrf

        <flux:input
            wire:model="name"
            :label="__('Profile name')"
            type="text"
            required
            autofocus
            :placeholder="__('Commander name')"
        />

        <flux:input
            wire:model="email"
            :label="__('Email (optional)')"
            type="email"
            autocomplete="email"
            placeholder="email@example.com"
        />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="create">{{ __('Create Profile') }}</span>
            <span wire:loading wire:target="create">{{ __('Creating...') }}</span>
        </flux:button>
    </form>

    <flux:link :href="route('profiles.index')" wire:navigate class="text-center">
        {{ __('Back to profiles') }}
    </flux:link>
</div>
