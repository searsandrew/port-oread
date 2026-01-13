<?php

use App\Exceptions\TiberApiException;
use App\Services\AuthSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(AuthSyncService $authSync): void
    {
        Log::info('Registration started for: '.$this->email);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $apiRegistered = false;

        try {
            Log::info('Attempting API registration for: '.$this->email);
            // Attempt API registration
            $apiRegistered = $authSync->register([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);

            if (! $apiRegistered) {
                Log::warning('API registration returned false (no token) for: '.$this->email);
                throw new TiberApiException('The registration server did not return an authentication token.', 0);
            }
            Log::info('API registration successful for: '.$this->email);
        } catch (TiberApiException $e) {
            if (! $e->isValidationError()) {
                Log::warning('Registration API error ('.$e->getStatus().'), but proceeding with local user creation: '.$e->getMessage());
                session()->flash('status', 'Account created locally. The online server is currently unreachable or having issues, so some features may be limited until you can sync.');
            } else {
                Log::error('Registration API validation error: '.$e->getMessage());
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => __('Registration failed: ').$e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Unexpected API registration error: '.$e->getMessage(), ['exception' => $e]);
            Log::warning('Proceeding with local user creation after unexpected error.');
            session()->flash('status', 'Account created locally due to a technical issue with the sync server.');
        }

        try {
            Log::info('Creating local user for: '.$this->email);
            // Create local user
            $user = \App\Models\User::updateOrCreate(
                ['email' => $this->email],
                [
                    'name' => $this->name,
                    'password' => $this->password,
                ]
            );
            Log::info('Local user created/updated: '.$user->id);

            Auth::login($user);
            Log::info('User logged in locally: '.$user->id);

            $this->redirect(route('dashboard', absolute: false), navigate: true);
        } catch (\Exception $e) {
            Log::error('Failed to create local user: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}; ?>

<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form wire:submit="register" class="flex flex-col gap-6">
            <!-- Name -->
            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                wire:model="email"
                :label="__('Email address')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
                    <span wire:loading wire:target="register">{{ __('Creating account...') }}</span>
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>
