<?php

use App\Models\User;
use App\Services\AuthSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.auth.card');

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(AuthSyncService $authSync): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create/select local profile first (no real local password system)
        $profile = User::firstOrCreate(
            ['email' => $this->email],
            [
                'name' => $this->name,
                // users table typically requires password; set a random one and never use it
                'password' => Str::random(64),
            ]
        );

        // Keep name fresh locally
        $profile->forceFill(['name' => $this->name])->save();

        Auth::login($profile);
        session()->regenerate();

        try {
            $ok = $authSync->connectRegister($profile, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);

            if ($ok) {
                session()->flash('status', 'Account connected!');
            } else {
                session()->flash('status', 'Profile created. Connect failed—try again later.');
            }
        } catch (\Throwable $e) {
            Log::error('Tiber register/connect failed: '.$e->getMessage(), ['exception' => $e]);
            session()->flash('status', 'Profile created. Connect failed—try again later.');
        }

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit.prevent="register" method="POST" class="flex flex-col gap-6">
        @csrf

        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" :placeholder="__('Full name')" />
        <flux:input wire:model="email" :label="__('Email address')" type="email" required autocomplete="email" placeholder="email@example.com" />
        <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" :placeholder="__('Password')" viewable />
        <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" :placeholder="__('Confirm password')" viewable />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
            <span wire:loading wire:target="register">{{ __('Creating account...') }}</span>
        </flux:button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>{{ __('Already have an account?') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</div>
