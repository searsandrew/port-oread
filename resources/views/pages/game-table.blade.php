<?php

use App\Game\GameService;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new class extends Component
{
    public ?string $sessionId = null;
    public string $playerId = '1';

    public array $hud = ['score' => 0, 'credits' => 0, 'pot_vp' => 0];
    public array $planets = [];
    public array $hand = [];
    public ?string $selectedCardId = null;

    public bool $showCardMenu = false;
    public bool $showInfoModal = false;

    public function mount(GameService $game): void
    {
        $this->sessionId ??= (string) Str::uuid();
        $this->applySnapshot($game->snapshot($this->sessionId));
    }

    private function applySnapshot(array $snapshot): void
    {
        $this->hud = $snapshot['hud'] ?? $this->hud;
        $this->planets = $snapshot['planets'] ?? [];
        $this->hand = $snapshot['hand'] ?? [];

        $this->selectedCardId = $snapshot['selectedCardId']
            ?? ($this->hand[0]['id'] ?? null);
    }

    public function openCardMenu(string $cardId): void
    {
        $this->selectedCardId = $cardId;
        $this->showCardMenu = true;
    }

    public function closeCardMenu(): void
    {
        $this->showCardMenu = false;
    }

    public function openInfo(): void
    {
        $this->showCardMenu = false;
        $this->showInfoModal = true;
    }

    public function closeInfo(): void
    {
        $this->showInfoModal = false;
    }

    public function playSelected(GameService $game): void
    {
        if (!$this->selectedCardId) return;

        $result = $game->playCard($this->sessionId, $this->playerId, $this->selectedCardId);

        // fire effects first (overlay listens for this)
        $effects = $result['effects'] ?? [];
        if (!empty($effects)) {
            $this->dispatch('game-effects', effects: $effects);
        }

        if (isset($result['snapshot'])) {
            $this->applySnapshot($result['snapshot']);
        }

        $this->showCardMenu = false;

        $this->dispatch('hand-updated');
        $this->dispatch('planets-updated');
    }
};
?>

@php
    $mercAbilityTitle = fn (?string $type) => match ($type) {
        'overpower_fifteen' => 'Overpower Fifteen',
        'reveal_opponents_corp' => 'Intel Leak',
        'win_all_ties' => 'Tie Breaker',
        'return_once' => 'One More Run',
        'discard_planet_draw_new' => 'Scorched Contract',
        'peek_next_planet' => 'Recon Scan',
        default => 'Mercenary Ability',
    };

    $mercAbilityDescription = fn (?string $type, array $params = []) => match ($type) {
        'overpower_fifteen'
            => 'When played: if the opponent played <span class="font-semibold">15</span>, treat your strength as <span class="font-semibold">16</span> for this battle.',
        'reveal_opponents_corp'
            => 'When played: reveal the opponent’s Corporation.',
        'win_all_ties'
            => 'When played: if this battle would be a tie, you win instead.',
        'return_once'
            => 'After this battle: return this card to your hand <span class="font-semibold">once</span>.',
        'discard_planet_draw_new'
            => 'When played: discard the current planet and reveal a new one into the pot.',
        'peek_next_planet'
            => 'When played: look at the next planet in the deck.',
        default
            => 'No description available.',
    };
@endphp

<div
    class="h-dvh w-full bg-zinc-950 text-zinc-100 overflow-hidden"
    x-data="portOreadTable()"
    x-init="init()"
    x-on:hand-updated.window="refreshHandSwiper()"
>
    {{-- HUD --}}
    <div class="px-4 pt-4">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="text-xs text-zinc-400">Score</div>
                <div class="text-xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
            </div>

            <div class="space-y-1 text-right">
                <div class="text-xs text-zinc-400">Pot VP</div>
                <div class="text-xl font-semibold">{{ $hud['pot_vp'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    {{-- Planet Stage --}}
    <div class="px-4 pt-4">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm text-zinc-400">Planet up for grabs</div>
                    <div class="mt-1 text-lg font-semibold truncate">
                        {{ ($planets[0]['name'] ?? '—') }}
                    </div>
                    <div class="mt-1 text-sm text-zinc-300 line-clamp-2">
                        {{ ($planets[0]['flavor'] ?? '') }}
                    </div>
                </div>

                <div class="flex gap-2 shrink-0">
                    <div class="h-10 w-10 rounded-full border border-white/10 bg-white/5 grid place-items-center text-xs">
                        {{ ($planets[0]['type'] ?? '') }}
                    </div>
                    <div class="h-10 w-10 rounded-full border border-white/10 bg-white/5 grid place-items-center text-sm font-semibold">
                        {{ ($planets[0]['vp'] ?? 0) }}
                    </div>
                </div>
            </div>

            @if(count($planets) > 1)
                <div class="mt-3 text-xs text-zinc-400">
                    Tie stack: {{ count($planets) }} planets in the pot.
                </div>
            @endif
        </div>
    </div>

    {{-- Hand Carousel --}}
    <div class="px-4 pt-6">
        <div class="text-sm text-zinc-400 mb-2">Your hand</div>

        <div class="relative">
            <div class="swiper" x-ref="handSwiper">
                <div class="swiper-wrapper">
                    @foreach($hand as $card)
                        <div class="swiper-slide">
                            <div
                                class="select-none"
                                x-on:click="handleCardClick({{ $loop->index }}, '{{ $card['id'] }}')"
                                x-on:pointerdown="pointerStart($event, {{ $loop->index }})"
                                x-on:pointerup="pointerEnd($event, '{{ $card['id'] }}', {{ $loop->index }})"
                            >
                                <div class="relative">
                                    <img
                                        src="{{ $card['img'] }}"
                                        class="block w-full h-auto rounded-2xl border border-white/10"
                                        draggable="false"
                                    />

                                    {{-- merc pip --}}
                                    @if(($card['isMerc'] ?? false) === true)
                                        <div class="absolute top-2 right-2 h-3 w-3 rounded-full border border-white/30 bg-white/20"></div>
                                    @endif
                                </div>

                                <div class="mt-2 text-center text-xs text-zinc-400">
                                    Strength {{ $card['id'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-zinc-950 to-transparent"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-zinc-950 to-transparent"></div>
        </div>
    </div>

    {{-- Card Menu Dialog --}}
    <div x-cloak x-show="$wire.showCardMenu" class="fixed inset-0 z-40">
        <div class="absolute inset-0 bg-black/70" x-on:click="$wire.closeCardMenu()"></div>

        <div class="absolute inset-0 grid place-items-center p-4">
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-zinc-950 p-4">
                @php($selected = collect($hand)->firstWhere('id', $selectedCardId))

                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold">Selected Card</div>
                    <button class="text-zinc-400" wire:click="closeCardMenu">✕</button>
                </div>

                @if($selected)
                    <img src="{{ $selected['img'] }}" class="mt-3 w-full rounded-2xl border border-white/10" draggable="false" />
                @endif

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <button class="rounded-2xl bg-white/10 px-3 py-3 text-sm" wire:click="playSelected">
                        Play
                    </button>
                    <button class="rounded-2xl bg-white/5 px-3 py-3 text-sm" wire:click="closeCardMenu">
                        Cancel
                    </button>
                    <button class="rounded-2xl bg-white/5 px-3 py-3 text-sm" wire:click="openInfo">
                        Info
                    </button>
                </div>

                <div class="mt-2 text-xs text-zinc-500">
                    Swipe up on the active card to play instantly.
                </div>
            </div>
        </div>
    </div>

    {{-- Info Modal --}}
    <div x-cloak x-show="$wire.showInfoModal" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/70" x-on:click="$wire.closeInfo()"></div>

        <div class="absolute inset-0 grid place-items-center p-4">
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-zinc-950 p-4">
                @php($selected = collect($hand)->firstWhere('id', $selectedCardId))

                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold">Card Info</div>
                    <button class="text-zinc-400" wire:click="closeInfo">✕</button>
                </div>

                @if($selected)
                    <img src="{{ $selected['img'] }}" class="mt-3 w-full rounded-2xl border border-white/10" draggable="false" />

                    @if(($selected['isMerc'] ?? false) === true)
                        <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs">
                            <span class="h-2 w-2 rounded-full bg-white/40"></span>
                            Mercenary
                        </div>

                        <div class="mt-3">
                            <div class="text-sm font-semibold">
                                {{ $selected['merc']['name'] ?? 'Mercenary' }}
                            </div>

                            <div class="mt-2 rounded-2xl border border-white/10 bg-white/5 p-3">
                                <div class="text-sm font-semibold">
                                    {{ $mercAbilityTitle($selected['merc']['ability_type'] ?? null) }}
                                </div>
                                <div class="mt-1 text-sm text-zinc-300">
                                    {!! $mercAbilityDescription($selected['merc']['ability_type'] ?? null, $selected['merc']['params'] ?? []) !!}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 text-sm text-zinc-300">Standard ship card.</div>
                    @endif
                @else
                    <div class="mt-4 text-sm text-zinc-400">No card selected.</div>
                @endif

                <div class="mt-4">
                    <button class="w-full rounded-2xl bg-white/10 px-3 py-3 text-sm" wire:click="closeInfo">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Battle Overlay (IMPORTANT: wire:ignore so Livewire never breaks Alpine scope) --}}
    <div
        wire:ignore
        id="battle-overlay"
        class="fixed inset-0 z-60"
        x-data="battleOverlay()"
        x-on:game-effects.window="run($event.detail.effects)"
        x-show="visible"
        x-cloak
    >
        <div class="absolute inset-0 bg-black/80"></div>

        <div class="absolute inset-0 grid place-items-center">
            <div class="w-full max-w-md px-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <img :src="playerImg" class="w-full rounded-2xl border border-white/10" />
                        <div class="absolute inset-0 rounded-2xl" :class="outcome === 'win' ? 'ring-2 ring-white/70' : 'ring-0'"></div>
                    </div>
                    <div class="relative">
                        <img :src="enemyImg" class="w-full rounded-2xl border border-white/10" />
                        <div class="absolute inset-0 rounded-2xl" :class="outcome === 'loss' ? 'ring-2 ring-white/70' : 'ring-0'"></div>
                    </div>
                </div>

                <div class="mt-4 text-center text-sm text-zinc-200">
                    <span x-show="outcome === 'win'">You win the battle.</span>
                    <span x-show="outcome === 'loss'">You lose the battle.</span>
                    <span x-show="outcome === 'tie'">Tie — the pot escalates.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function portOreadTable() {
        return {
            handSwiper: null,

            // gesture state
            startY: null,
            startX: null,
            startTime: null,
            startIdx: null,
            pointerId: null,

            init() {
                this.initHandSwiper();
            },

            initHandSwiper() {
                if (!window.Swiper) return;

                this.handSwiper = new Swiper(this.$refs.handSwiper, {
                    slidesPerView: 1.35,
                    centeredSlides: true,
                    spaceBetween: 14,
                    slideToClickedSlide: true,
                });
            },

            refreshHandSwiper() {
                this.$nextTick(() => {
                    if (this.handSwiper) this.handSwiper.update();
                    else this.initHandSwiper();
                });
            },

            // Clicking peeking card advances; clicking active opens menu
            handleCardClick(index, cardId) {
                if (!this.handSwiper) {
                    this.$wire.openCardMenu(cardId);
                    return;
                }

                const active = this.handSwiper.activeIndex;

                if (index !== active) {
                    this.handSwiper.slideTo(index);
                    return;
                }

                this.$wire.openCardMenu(cardId);
            },

            pointerStart(e, index) {
                // If a modal is open, don't track swipe-to-play
                if (this.$wire.showCardMenu || this.$wire.showInfoModal) return;

                this.pointerId = e.pointerId;
                this.startY = (typeof e.clientY === 'number') ? e.clientY : null;
                this.startX = (typeof e.clientX === 'number') ? e.clientX : null;
                this.startTime = performance.now();
                this.startIdx = this.handSwiper ? this.handSwiper.activeIndex : index;
            },

            pointerEnd(e, cardId, index) {
                if (this.$wire.showCardMenu || this.$wire.showInfoModal) return;
                if (this.startY === null || this.startX === null || this.pointerId !== e.pointerId) return;

                const endY = (typeof e.clientY === 'number') ? e.clientY : null;
                const endX = (typeof e.clientX === 'number') ? e.clientX : null;
                if (endY === null || endX === null) return;

                const dy = endY - this.startY;
                const dx = endX - this.startX;
                const elapsed = performance.now() - (this.startTime ?? performance.now());

                // Reset immediately so we never "carry" a gesture between clicks
                this.startY = this.startX = this.startTime = null;
                this.pointerId = null;

                // Require a *real* vertical swipe:
                // - fast-ish gesture
                // - mostly vertical
                // - large upward travel
                if (elapsed > 900) return;
                if (Math.abs(dx) > 60) return;
                if (dy > -90) return; // must be upward by at least 90px

                const active = this.handSwiper ? this.handSwiper.activeIndex : index;
                if (index !== active) return;

                this.$wire.selectedCardId = cardId;
                this.$wire.playSelected();
            },
        };
    }

    function battleOverlay() {
        return {
            visible: false,
            playerImg: '',
            enemyImg: '',
            outcome: 'tie',

            run(effects) {
                if (!effects || !effects.length) return;

                const e = effects.find(x => x.type === 'battle_resolve');
                if (!e) return;

                this.playerImg = e.player?.img || '';
                this.enemyImg  = e.enemy?.img  || '';
                this.outcome   = e.outcome || 'tie';

                // show overlay briefly
                this.visible = true;
                setTimeout(() => this.visible = false, 900);
            }
        };
    }
</script>
