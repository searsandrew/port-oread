<?php

use App\Models\User;
use App\Services\AuthSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.auth.card');

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(AuthSyncService $authSync): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Ensure there is a local profile for this email
        $profile = User::firstOrCreate(
            ['email' => $this->email],
            [
                'name' => 'Commander',
                'password' => Str::random(64),
            ]
        );

        Auth::login($profile, $this->remember);
        session()->regenerate();

        try {
            $ok = $authSync->connectLogin($profile, $this->email, $this->password);

            session()->flash('status', $ok ? 'Account connected!' : 'Logged into local profile. Connect failed—try again later.');
        } catch (\Throwable $e) {
            Log::error('Tiber login/connect failed: '.$e->getMessage(), ['exception' => $e]);
            session()->flash('status', 'Logged into local profile. Connect failed—try again later.');
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Connect your account')" :description="__('Connect to sync purchases, fleets, and stats.')" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit.prevent="login" method="POST" class="flex flex-col gap-6">
        @csrf

        <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus autocomplete="email" placeholder="email@example.com" />

        <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="current-password" :placeholder="__('Password')" viewable />

        <flux:checkbox wire:model="remember" :label="__('Remember this profile on this device')" />

        <flux:button variant="primary" type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">{{ __('Connect') }}</span>
            <span wire:loading wire:target="login">{{ __('Connecting...') }}</span>
        </flux:button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>{{ __('Need an account?') }}</span>
        <flux:link :href="route('register')" wire:navigate>{{ __('Create one') }}</flux:link>
    </div>
</div>
