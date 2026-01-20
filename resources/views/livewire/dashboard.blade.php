<?php

use App\Services\CurrentProfile;
use App\Services\LocalStoreService;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component
{
    public function with(CurrentProfile $current, LocalStoreService $store): array
    {
        $profile = $current->get();

        $token = $profile
            ? $store->getForProfile($profile->id, 'auth_token')
            : null;

        // Placeholder for “accumulated points”
        $points = 0;

        return [
            'profile' => $profile,
            'isConnected' => (bool) $token,
            'points' => $points,
        ];
    }
}; ?>

<div class="max-w-5xl mx-auto p-4 space-y-4">

    <!-- Top bar -->
    <div class="flex rounded-2xl   border border-zinc-800 bg-linear-to-b from-generic-dark to-generic-light">
        <div class="flex w-full h-full pl-4 pr-2 py-1 rounded-2xl items-center justify-between inset-shadow-red-500" style="background-image: url('{{ asset('/images/grid-hex.png') }}'); background-repeat: repeat; background-size: 25px;">
            <div class="flex items-center gap-3">
                @if ($isConnected)
                    <span class="flex flex-row min-w-18 px-2.5 items-center justify-between rounded-full bg-amber-100 text-amber-800 inner-shadow-amber-500">
                        <flux:icon.velium class="size-4" />
                        <span class="font-mono">{{ $points }}</span>
                    </span>
                @endif
            </div>

            <!-- Profile dropdown -->
            <div class="relative">
                <flux:dropdown>
                    <flux:avatar size="lg" class="border-2 border-generic-light" as="button" circle badge badge:circle :badge:color="($isConnected ? 'green' : 'zinc')" :badge:variant="($isConnected ? 'solid' : 'outline')" src="{{ 'https://unavatar.io/' . $profile->email }}" />
                    <flux:menu>
                        @if (!$isConnected)
                            <flux:menu.item icon:trailing="signal-slash" :href="route('login')" wire:navigate>{{ __('Click to Connect') }}</flux:menu.item>
                        @endif
                        <flux:menu.item :href="route('settings.index')" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        <flux:menu.item :href="route('profiles.switch')" wire:navigate>{{ __('Switch Profile') }}</flux:menu.item>
                        @if ($isConnected)
                            <flux:menu.separator />
                            <flux:menu.item variant="danger" :href="route('connect.disconnect')">{{ __('Disconnect') }}</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </div>

    <!-- Hero / media slot -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="aspect-video bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center">
            <div class="text-zinc-500 text-sm">
                Media slot (video / teaser / rotating art) — placeholder
            </div>
        </div>
    </div>

    <!-- Primary actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <x-button size="lg"><span class="text-blue-100 text-xl font-thin uppercase shadow-inner tracking-wider">{{ __('Online Play') }}</span></x-button>
        <x-button href="route('game.table')"><span class="text-blue-100 text-lg font-thin uppercase shadow-inner tracking-wider">{{ __('Offline vs AI') }}</span></x-button>
        <x-button><span class="text-blue-100 text-lg font-thin uppercase shadow-inner tracking-wider">{{ __('Lore') }}</span></x-button>
    </div>
</div>
