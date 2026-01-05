<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $hand = [];

    public function mount(): void
    {
        $this->hand = [
            ['id' => 'c1', 'name' => 'Kestrel Wing', 'cost' => 2, 'art' => null],
            ['id' => 'c2', 'name' => 'Dockside Bribe', 'cost' => 1, 'art' => null],
            ['id' => 'c3', 'name' => 'Orbital Feint', 'cost' => 3, 'art' => null],
            ['id' => 'c4', 'name' => 'Mercenary Contract', 'cost' => 2, 'art' => null],
            ['id' => 'c5', 'name' => 'Planetfall Raid', 'cost' => 4, 'art' => null],
        ];
    }

    public function playCard(string $cardId): void
    {
        $this->hand = array_values((array) array_filter($this->hand, fn($c) => $c['id'] !== $cardId));

        $this->dispatch('hand-updated');
    }
}; ?>

<component>
    <div class="h-full w-full flex flex-col bg-zinc-950 text-zinc-100">
        {{-- Top HUD (placeholder) --}}
        <div class="px-4 py-3 border-b border-zinc-800">
            <div class="flex items-center justify-between">
                <div class="text-sm text-zinc-300">Port Oread</div>
                <div class="text-sm text-zinc-400">T+00:00</div>
            </div>
        </div>

        {{-- Board area (placeholder) --}}
        <div class="flex-1 p-4">
            <div class="h-full rounded-xl border border-zinc-800 bg-zinc-900/30 flex items-center justify-center text-zinc-500">
                Board / lanes / battlefield goes here
            </div>
        </div>

        {{-- Hand area --}}
        <div class="p-3 border-t border-zinc-800 bg-zinc-950/80">
            <div class="swiper" x-data="{ ready: false }"
                 x-init="
                    window.initHandSwiper($el);
                    ready = true;

                    $wire.on('hand-updated', () => {
                        // Livewire updated DOM, re-init Swiper
                        window.initHandSwiper($el);
                    });
                ">
                <div class="swiper-wrapper">
                    @foreach($hand as $card)
                        <div class="swiper-slide" style="width: 160px;">
                            <div class="flex flex-col gap-2">
                                {{-- Tap to flip card --}}
                                <div class="card-flip w-full h-[220px]" x-data="{ flipped: false }"
                                    @click="
                                        const s = $root.closest('.swiper');
                                        if (s && s.__isHandDragging && s.__isHandDragging()) return;
                                        flipped = !flipped;
                                    ">
                                    <div class="card-flip-inner" :class="{ 'is-flipped': flipped }">
                                        {{-- Front --}}
                                        <div class="card-face card-front bg-zinc-800 border border-zinc-700">
                                            <div class="p-3 h-full flex flex-col">
                                                <div class="flex items-start justify-between">
                                                    <div class="text-xs text-zinc-300">Cost</div>
                                                    <div class="text-lg font-bold">{{ $card['cost'] }}</div>
                                                </div>
                                                <div class="mt-auto">
                                                    <div class="text-sm font-semibold leading-tight">{{ $card['name'] }}</div>
                                                    <div class="text-xs text-zinc-400">Tap to flip</div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Back --}}
                                        <div class="card-face card-back bg-zinc-900 border border-zinc-700">
                                            <div class="p-3 h-full flex flex-col">
                                                <div class="text-xs text-zinc-400">Card back</div>
                                                <div class="mt-auto text-xs text-zinc-500">
                                                    Rules text / tags / faction icons later
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Play button --}}
                                <button class="w-full text-xs px-3 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700" wire:click="playCard('{{ $card['id'] }}')">
                                    {{ __('Play') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</component>
