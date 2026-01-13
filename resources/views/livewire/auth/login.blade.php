<?php

use App\Services\AuthSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(AuthSyncService $authSync): void
    {
        Log::info('Login attempt for: '.$this->email);

        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 1) Try API login first
        try {
            if ($authSync->login($this->email, $this->password)) {
                $userData = $authSync->getUser();
                Log::info('Sync login successful for: '.$this->email);

                $user = \App\Models\User::updateOrCreate(
                    ['email' => $this->email],
                    [
                        'name' => $userData['name'] ?? 'Commander',
                        'password' => $this->password, // hashed cast on model will hash :contentReference[oaicite:6]{index=6}
                    ]
                );

                Auth::login($user, $this->remember);
                session()->regenerate();

                $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
                return;
            }
        } catch (\Throwable $e) {
            Log::error('API login error: '.$e->getMessage(), ['exception' => $e]);
            session()->flash('status', 'Online sync is unavailable right now — attempting offline login.');
        }

        // 2) Fallback: local/offline login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            Log::info('Offline login successful for: '.$this->email);

            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        Log::warning('Login failed for: '.$this->email);
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }
}; ?>

<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form wire:submit="login" class="flex flex-col gap-6">
            <!-- Email Address -->
            <flux:input
                wire:model="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    wire:model="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox wire:model="remember" :label="__('Remember me')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts.auth>
