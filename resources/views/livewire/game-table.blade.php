<?php

use function Livewire\Volt\{state};
use App\Game\GameService;

state([
    'sessionId' => 'local-1',
    'playerId' => 'p1',

    // Render state
    'hud' => ['score' => 0, 'credits' => 0],
    'planets' => [
        ['id' => 'p1', 'name' => 'Vesper Reach', 'flavor' => 'Cold rings. Hot politics.', 'type' => 'A', 'vp' => 2, 'art' => null],
    ],
    'hand' => [
        ['id' => 'c1', 'img' => '/images/cards/placeholder-1.png'],
        ['id' => 'c2', 'img' => '/images/cards/placeholder-2.png'],
        ['id' => 'c3', 'img' => '/images/cards/placeholder-3.png'],
    ],

    // UI state
    'selectedCardId' => null,

    // Modal state
    'showCardModal' => false,
    'modalCardImg' => null,
]);

$mount = function (GameService $game) {
    // Later: pull a real snapshot from stellar-skirmish.
    // For now, pick the first card as selected if none.
    if (is_null($this->selectedCardId) && count($this->hand)) {
        $this->selectedCardId = $this->hand[0]['id'];
    }

    // When you're ready to use the engine:
    // $snap = $game->snapshot($this->sessionId);
    // $this->applySnapshot($snap);
};

$applySnapshot = function (array $snap) {
    $this->hud = $snap['hud'] ?? $this->hud;
    $this->planets = $snap['planets'] ?? $this->planets;
    $this->hand = $snap['hand'] ?? $this->hand;
    $this->selectedCardId = $snap['selectedCardId'] ?? ($this->hand[0]['id'] ?? null);
};

$selectCard = function (?string $cardId) {
    $this->selectedCardId = $cardId;
};

$playSelected = function (GameService $game) {
    if (!$this->selectedCardId) return;

    // Call the driver (local engine now, online later)
    $result = $game->playCard($this->sessionId, $this->playerId, $this->selectedCardId);

    // 1) Kick off animations (overlay listens for this)
    $effects = $result['effects'] ?? [];
    if (!empty($effects)) {
        $this->dispatch('game-effects', effects: $effects);
    }

    // 2) Update UI snapshot/state
    if (isset($result['snapshot'])) {
        $this->applySnapshot($result['snapshot']);
    }

    // 3) Re-init swipers because DOM changed
    $this->dispatch('hand-updated');
    $this->dispatch('planets-updated');
};

$showCardInfo = function (string $cardId) {
    $card = collect($this->hand)->firstWhere('id', $cardId);

    $this->modalCardImg = $card['img'] ?? null;
    $this->showCardModal = true;
};

$closeCardInfo = function () {
    $this->showCardModal = false;
    $this->modalCardImg = null;
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
            <div id="planet-stage" class="absolute inset-0">
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
            </div>

            {{-- Battle overlay region (we’ll animate ships in here later) --}}
            <div id="battle-overlay" class="pointer-events-none absolute inset-0 z-20" x-data="battleOverlay()" x-on:game-effects.window="run($event.detail.effects)"></div>
        </div>
    </div>

    {{-- 3) HAND CAROUSEL --}}
    <div class="shrink-0 p-3 border-t border-zinc-800 bg-zinc-950/85" x-data="handUI(@entangle('selectedCardId'))" @click.self="closeControls()">
        <div class="swiper" x-data
            x-init="window.initHandSwiper($el, {
                    onCenterChanged: (cardId) => $wire.selectCard(cardId),
                    onSwipeUpPlay: () => $wire.playSelected(),
                  });

                  $wire.on('hand-updated', () => {
                    window.initHandSwiper($el, {
                      onCenterChanged: (cardId) => $wire.selectCard(cardId),
                      onSwipeUpPlay: () => $wire.playSelected(),
                    });
                  });
                ">
            <div class="swiper-wrapper">
                @foreach($hand as $card)
                    <div class="swiper-slide" style="width: 74%;">
                        <div class="relative">
                            <button type="button" class="block w-full rounded-2xl overflow-hidden border border-zinc-700 bg-zinc-900" @click.stop="toggleControls('{{ $card['id'] }}')" :class="isSelected('{{ $card['id'] }}') ? 'ring-2 ring-zinc-200/25' : ''">
                                <img src="{{ $card['img'] }}" alt="" class="w-full h-[260px] object-cover select-none" draggable="false" data-card-id="{{ $card['id'] }}">
                            </button>

                            {{-- Controls (only for selected card AND only when open) --}}
                            <div class="absolute left-2 right-2 -bottom-3 translate-y-full" x-show="controlsOpenFor('{{ $card['id'] }}')" x-transition.opacity>
                                <div class="rounded-2xl border border-zinc-700 bg-zinc-950/95 backdrop-blur px-2 py-2 flex gap-2">
                                    <button class="flex-1 px-3 py-2 rounded-xl bg-zinc-100 text-zinc-900 text-sm font-semibold" @click.stop="$wire.playSelected(); closeControls()">
                                        Play
                                    </button>

                                    <button class="px-3 py-2 rounded-xl border border-zinc-700 bg-zinc-900 text-sm" @click.stop="closeControls()">
                                        Cancel
                                    </button>

                                    <button class="px-3 py-2 rounded-xl border border-zinc-700 bg-zinc-900 text-sm" @click.stop="$wire.showCardInfo('{{ $card['id'] }}')">
                                        Info
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
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

<script>
    window.handUI = (selectedEntangle) => ({
        selected: selectedEntangle,
        controlsFor: null,

        isSelected(id) { return this.selected === id; },

        toggleControls(id) {
            // only allow controls for the selected (center) card
            if (this.selected !== id) {
                // if you clicked a non-centered card, just close controls;
                // swiper click-to-center will update selected shortly after
                this.controlsFor = null;
                return;
            }
            this.controlsFor = (this.controlsFor === id) ? null : id;
        },

        controlsOpenFor(id) {
            return this.controlsFor === id && this.selected === id;
        },

        closeControls() { this.controlsFor = null; },
    });
</script>

