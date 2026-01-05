<?php

use function Livewire\Volt\{state};

state([
    'score' => 0,
    'credits' => 0,
    'planets' => [
        // single planet to start; in tie, this becomes 2+ planets
        ['id' => 'p1', 'name' => 'Vesper Reach', 'flavor' => 'Cold rings. Hot politics.', 'type' => 'A', 'vp' => 2, 'art' => null],
    ],
    'hand' => [
        ['id' => 'c1', 'img' => '/images/cards/placeholder-1.png'],
        ['id' => 'c2', 'img' => '/images/cards/placeholder-2.png'],
        ['id' => 'c3', 'img' => '/images/cards/placeholder-3.png'],
    ],
    'selectedCardId' => null,
    'showPlanetModal' => false,
]);

$selectCard = function (string $cardId) {
    $this->selectedCardId = $cardId;
};

$playSelected = function () {
    if (!$this->selectedCardId) return;

    // TODO: send to engine; for now just remove it
    $this->hand = array_values(array_filter($this->hand, fn($c) => $c['id'] !== $this->selectedCardId));
    $this->selectedCardId = null;

    $this->dispatch('hand-updated');
    // Later: dispatch('battle-resolve', ...)
};

$openPlanet = function () {
    $this->showPlanetModal = true;
};

$closePlanet = function () {
    $this->showPlanetModal = false;
};

?>

<div class="h-dvh w-full bg-zinc-950 text-zinc-100 flex flex-col">

    {{-- 1) TOP HUD --}}
    <div class="shrink-0 px-4 py-3 border-b border-zinc-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="text-sm">
                    <span class="text-zinc-400">Score</span>
                    <span class="font-semibold">{{ $score }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-zinc-400">Credits</span>
                    <span class="font-semibold">{{ $credits }}</span>
                </div>
            </div>

            {{-- Flux button later; plain for now --}}
            <button class="text-xs px-3 py-2 rounded-lg border border-zinc-700 bg-zinc-900 hover:bg-zinc-800">
                Profile
            </button>
        </div>
    </div>

    {{-- 2) PLANET / BATTLEFIELD --}}
    <div class="flex-1 p-4">
        <div class="h-full rounded-2xl border border-zinc-800 bg-zinc-900/25 relative overflow-hidden">

            {{-- Planet swiper only matters in tie state; safe even with 1 planet --}}
            <div
                class="swiper h-full"
                x-data
                x-init="
                    window.initPlanetSwiper?.($el);
                    $wire.on('planets-updated', () => window.initPlanetSwiper?.($el));
                "
            >
                <div class="swiper-wrapper h-full">
                    @foreach($planets as $planet)
                        <div class="swiper-slide h-full" style="width: 86%;">
                            <button
                                type="button"
                                class="w-full h-full text-left p-4 flex flex-col justify-between"
                                wire:click="openPlanet"
                            >
                                <div class="flex items-start justify-between">
                                    {{-- Type + VP “circles” --}}
                                    <div class="flex gap-2">
                                        <div class="w-10 h-10 rounded-full border border-zinc-700 bg-zinc-950/40 flex items-center justify-center text-sm font-semibold">
                                            {{ $planet['type'] }}
                                        </div>
                                    </div>

                                    <div class="w-10 h-10 rounded-full border border-zinc-700 bg-zinc-950/40 flex items-center justify-center text-sm font-semibold">
                                        {{ $planet['vp'] }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-lg font-semibold leading-tight">{{ $planet['name'] }}</div>
                                    <div class="text-sm text-zinc-400 mt-1">{{ $planet['flavor'] }}</div>
                                    <div class="text-xs text-zinc-500 mt-2">Tap to view card</div>
                                </div>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Battle overlay region (we’ll animate ships in here later) --}}
            <div class="pointer-events-none absolute inset-0" id="battle-overlay"></div>

        </div>
    </div>

    {{-- 3) HAND CAROUSEL --}}
    <div
        class="shrink-0 p-3 border-t border-zinc-800 bg-zinc-950/85"
        x-data="{ showPlay: false }"
    >
        <div
            class="swiper"
            x-data
            x-init="
                window.initHandSwiper?.($el, {
                    onCenterChanged: (cardId) => $wire.selectCard(cardId),
                    onSwipeUpPlay: () => $wire.playSelected(),
                });
                $wire.on('hand-updated', () => window.initHandSwiper?.($el, {
                    onCenterChanged: (cardId) => $wire.selectCard(cardId),
                    onSwipeUpPlay: () => $wire.playSelected(),
                }));
            "
        >
            <div class="swiper-wrapper">
                @foreach($hand as $card)
                    <div class="swiper-slide" style="width: 74%;">
                        <div class="relative">
                            {{-- Card image --}}
                            <div class="rounded-2xl overflow-hidden border border-zinc-700 bg-zinc-900">
                                <img
                                    src="{{ $card['img'] }}"
                                    alt=""
                                    class="w-full h-[260px] object-cover select-none"
                                    draggable="false"
                                    data-card-id="{{ $card['id'] }}"
                                >
                            </div>

                            {{-- Play button appears for centered card --}}
                            <div class="mt-2">
                                <button
                                    class="w-full px-3 py-2 rounded-xl border border-zinc-700 bg-zinc-900 hover:bg-zinc-800 text-sm"
                                    wire:click="playSelected"
                                >
                                    Play
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Optional: show which card is “selected” --}}
        <div class="mt-2 text-xs text-zinc-500">
            Selected: <span class="text-zinc-300">{{ $selectedCardId ?? '—' }}</span>
        </div>
    </div>

    {{-- Planet modal (simple for now; swap to Flux Sheet/Modal) --}}
    @if($showPlanetModal)
        <div class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
            <div class="w-full max-w-md rounded-2xl border border-zinc-700 bg-zinc-950 p-4">
                <div class="flex items-center justify-between">
                    <div class="font-semibold">Planet Card</div>
                    <button class="text-sm text-zinc-400 hover:text-zinc-200" wire:click="closePlanet">Close</button>
                </div>

                <div class="mt-3 rounded-xl border border-zinc-800 bg-zinc-900/40 h-[380px] flex items-center justify-center text-zinc-500">
                    Full planet card art goes here
                </div>
            </div>
        </div>
    @endif
</div>
