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

    public bool $gameOver = false;
    public ?string $endReason = null;

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

        $this->gameOver = (bool) ($snapshot['gameOver'] ?? false);
        $this->endReason = $snapshot['endReason'] ?? null;
    }

    public function openCardMenu(string $cardId): void
    {
        $this->selectedCardId = $cardId;
        $this->showCardMenu = true;
    }

    public function closeCardMenu(): void
    {
        $this->showCardMenu = false;
        $this->dispatch('modal-closed');
    }

    public function playSelected(GameService $game): void
    {
        if (!$this->selectedCardId) return;

        $result = $game->playCard($this->sessionId, $this->playerId, $this->selectedCardId);

        // fire effects first; overlay is wire:ignore
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

    public function newGame(GameService $game): void
    {
        $this->sessionId = (string) Str::uuid();
        $this->applySnapshot($game->snapshot($this->sessionId));

        $this->showCardMenu = false;

        $this->dispatch('hand-updated');
        $this->dispatch('planets-updated');
        $this->dispatch('modal-closed');
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

    $endReasonLabel = fn (?string $reason) => match ($reason) {
        'normal' => 'Normal end (hands empty)',
        'ships_exhausted_planets_remaining' => 'Ships exhausted (planets remain)',
        'player_out_of_cards_early' => 'Player out of cards early',
        default => $reason ? str($reason)->headline() : 'Game over',
    };
@endphp

<div
    class="h-dvh w-full bg-zinc-950 text-zinc-100 overflow-hidden"
    x-data="portOreadTable()"
    x-init="init()"
    x-on:hand-updated.window="refreshHandSwiper()"
    x-on:planets-updated.window="refreshPlanetSwiper()"
    x-on:modal-closed.window="restoreHandIndexSoon()"
    x-on:battle-accepted.window="nudgePlanet($event.detail.planetMove)"
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

    {{-- Planet Stage (Swiper) --}}
    <div class="px-4 pt-4">
        <div
            class="rounded-3xl border border-white/10 bg-white/5 p-4 transition-transform duration-300 ease-out"
            :class="planetNudge === 'down' ? 'translate-y-6' : (planetNudge === 'up' ? '-translate-y-6' : '')"
        >
            <div class="flex items-center justify-between">
                <div class="text-sm text-zinc-400">Planet(s) in the pot</div>

                <div class="flex items-center gap-2">
                    {{-- desktop-friendly nav --}}
                    <button
                        type="button"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200"
                        x-ref="planetPrev"
                        x-on:click="planetSwiper?.slidePrev()"
                        :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                    >
                        ◀
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200"
                        x-ref="planetNext"
                        x-on:click="planetSwiper?.slideNext()"
                        :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                    >
                        ▶
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <div class="swiper" x-ref="planetSwiper">
                    <div class="swiper-wrapper">
                        @foreach($planets as $planet)
                            <div class="swiper-slide" wire:key="planet-{{ $planet['id'] }}-{{ $loop->index }}">
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="text-lg font-semibold truncate">
                                                {{ $planet['name'] ?? '—' }}
                                            </div>
                                            <div class="mt-1 text-sm text-zinc-300 line-clamp-2">
                                                {{ $planet['flavor'] ?? '' }}
                                            </div>
                                        </div>

                                        <div class="flex gap-2 shrink-0">
                                            <div class="h-10 w-10 rounded-full border border-white/10 bg-white/5 grid place-items-center text-xs">
                                                {{ $planet['type'] ?? '' }}
                                            </div>
                                            <div class="h-10 w-10 rounded-full border border-white/10 bg-white/5 grid place-items-center text-sm font-semibold">
                                                {{ $planet['vp'] ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- pagination bullets (clickable) --}}
                    <div class="mt-2 flex justify-center" x-ref="planetPagination"></div>
                </div>
            </div>

            @if(count($planets) > 1)
                <div class="mt-2 text-xs text-zinc-500">
                    Tip: on desktop you can use ◀ ▶, bullet dots, or arrow keys.
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
                        <div class="swiper-slide" data-card-id="{{ $card['id'] }}" wire:key="hand-{{ $card['id'] }}">
                            <div
                                class="select-none"
                                x-on:click="handleHandClick({{ $loop->index }}, '{{ $card['id'] }}')"
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

        <div class="mt-2 text-xs text-zinc-500">
            Click peeking cards to center them. Click the centered card to play.
        </div>
    </div>

    {{-- Select / Play Modal --}}
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

                    @if(($selected['isMerc'] ?? false) === true)
                        <div class="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3">
                            <div class="text-xs text-zinc-400">Mercenary</div>
                            <div class="mt-1 text-sm font-semibold">
                                {{ $selected['merc']['name'] ?? 'Mercenary' }}
                            </div>
                            <div class="mt-2 text-sm text-zinc-300">
                                <span class="font-semibold">{{ $mercAbilityTitle($selected['merc']['ability_type'] ?? null) }}:</span>
                                {!! $mercAbilityDescription($selected['merc']['ability_type'] ?? null, $selected['merc']['params'] ?? []) !!}
                            </div>
                        </div>
                    @endif
                @endif

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button class="rounded-2xl bg-white/10 px-3 py-3 text-sm" wire:click="playSelected">
                        Play
                    </button>
                    <button class="rounded-2xl bg-white/5 px-3 py-3 text-sm" wire:click="closeCardMenu">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Game Over --}}
    <div x-cloak x-show="$wire.gameOver" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/80"></div>

        <div class="absolute inset-0 grid place-items-center p-4">
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-zinc-950 p-4">
                <div class="text-lg font-semibold">Game Over</div>
                <div class="mt-1 text-sm text-zinc-300">
                    {{ $endReasonLabel($endReason) }}
                </div>

                <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-zinc-400">Your VP</div>
                    <div class="text-2xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button class="rounded-2xl bg-white/10 px-3 py-3 text-sm" wire:click="newGame">
                        New Game
                    </button>
                    <button class="rounded-2xl bg-white/5 px-3 py-3 text-sm" x-on:click="$wire.gameOver = false">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Battle Overlay (Accept-based, blocks input) --}}
    <div
        wire:ignore
        class="fixed inset-0 z-60"
        x-cloak
        x-show="$store.battle && $store.battle.visible"
        x-on:game-effects.window="$store.battle && $store.battle.run($event.detail.effects)"
    >
        <div class="absolute inset-0 bg-black/80"></div>

        <div class="absolute inset-0 grid place-items-center">
            <div class="w-full max-w-md px-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <img :src="$store.battle.playerImg" class="w-full rounded-2xl border border-white/10" />
                        <div class="absolute inset-0 rounded-2xl"
                             :class="$store.battle.outcome === 'win' ? 'ring-2 ring-white/70' : 'ring-0'"></div>
                    </div>
                    <div class="relative">
                        <img :src="$store.battle.enemyImg" class="w-full rounded-2xl border border-white/10" />
                        <div class="absolute inset-0 rounded-2xl"
                             :class="$store.battle.outcome === 'loss' ? 'ring-2 ring-white/70' : 'ring-0'"></div>
                    </div>
                </div>

                <div class="mt-4 text-center text-sm text-zinc-200">
                    <span x-show="$store.battle.outcome === 'win'">You win the battle.</span>
                    <span x-show="$store.battle.outcome === 'loss'">You lose the battle.</span>
                    <span x-show="$store.battle.outcome === 'tie'">Tie — the pot escalates.</span>
                </div>

                <div class="mt-4 flex justify-center">
                    <button
                        type="button"
                        class="rounded-2xl bg-white/10 px-4 py-3 text-sm"
                        x-on:click="$store.battle.accept()"
                    >
                        Accept
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function portOreadTable() {
        return {
            handSwiper: null,
            planetSwiper: null,
            handIndex: 0,

            // used for win/loss planet nudge
            planetNudge: null,

            init() {
                this.ensureBattleStore();
                this.initHandSwiper();
                this.initPlanetSwiper();
            },

            nudgePlanet(move) {
                if (!move || move === 'none') return;
                this.planetNudge = move;
                setTimeout(() => this.planetNudge = null, 350);
            },

            ensureBattleStore() {
                const build = () => ({
                    visible: false,
                    playerImg: '',
                    enemyImg: '',
                    outcome: 'tie',
                    planetMove: 'none',

                    run(effects) {
                        if (!effects || !effects.length) return;
                        const e = effects.find(x => x.type === 'battle_resolve');
                        if (!e) return;

                        this.playerImg = e.player?.img || '';
                        this.enemyImg  = e.enemy?.img  || '';
                        this.outcome   = e.outcome || 'tie';
                        this.planetMove = e.planetMove || 'none';

                        // SINGLE PLAYER: stay up until user accepts
                        this.visible = true;
                    },

                    accept() {
                        const move = this.planetMove || 'none';
                        this.visible = false;
                        this.planetMove = 'none';

                        window.dispatchEvent(new CustomEvent('battle-accepted', {
                            detail: { planetMove: move }
                        }));
                    }
                });

                const setStore = () => {
                    if (!window.Alpine || !window.Alpine.store) return;
                    if (!window.Alpine.store('battle')) {
                        window.Alpine.store('battle', build());
                    }
                };

                setStore();
                document.addEventListener('alpine:init', setStore);
            },

            initHandSwiper() {
                if (!window.Swiper) return;

                this.handSwiper = new Swiper(this.$refs.handSwiper, {
                    slidesPerView: 1.35,
                    centeredSlides: true,
                    spaceBetween: 14,
                });

                this.handSwiper.on('slideChange', () => {
                    this.handIndex = this.handSwiper.activeIndex;
                    this.syncSelectedToActiveSlide();
                });

                this.handIndex = this.handSwiper.activeIndex || 0;
                this.syncSelectedToActiveSlide();
            },

            initPlanetSwiper() {
                if (!window.Swiper) return;

                this.planetSwiper = new Swiper(this.$refs.planetSwiper, {
                    slidesPerView: 1,
                    spaceBetween: 10,
                    grabCursor: true,
                    simulateTouch: true,
                    keyboard: { enabled: true },
                    mousewheel: { forceToAxis: true },

                    // clickable dots
                    pagination: {
                        el: this.$refs.planetPagination,
                        clickable: true,
                    },
                });
            },

            refreshHandSwiper() {
                this.$nextTick(() => {
                    const idx = this.handIndex;

                    if (this.handSwiper) {
                        this.handSwiper.update();
                        this.handSwiper.slideTo(Math.min(idx, this.handSwiper.slides.length - 1), 0);
                    } else {
                        this.initHandSwiper();
                    }

                    this.syncSelectedToActiveSlide();
                });
            },

            refreshPlanetSwiper() {
                this.$nextTick(() => {
                    const current = this.planetSwiper ? this.planetSwiper.activeIndex : 0;

                    if (this.planetSwiper) {
                        this.planetSwiper.update();
                        // keep index if still valid; else clamp
                        const max = Math.max(0, this.planetSwiper.slides.length - 1);
                        this.planetSwiper.slideTo(Math.min(current, max), 0);
                    } else {
                        this.initPlanetSwiper();
                    }
                });
            },

            restoreHandIndexSoon() {
                this.$nextTick(() => {
                    if (!this.handSwiper) return;
                    this.handSwiper.update();
                    this.handSwiper.slideTo(Math.min(this.handIndex, this.handSwiper.slides.length - 1), 0);
                });
            },

            handleHandClick(index, cardId) {
                if (!this.handSwiper) {
                    this.$wire.openCardMenu(cardId);
                    return;
                }

                const active = this.handSwiper.activeIndex;

                // peeking click centers only
                if (index !== active) {
                    this.handIndex = index;
                    this.handSwiper.slideTo(index);
                    return;
                }

                // center click opens modal
                this.$wire.openCardMenu(cardId);
            },

            syncSelectedToActiveSlide() {
                if (!this.handSwiper) return;
                const slide = this.handSwiper.slides[this.handSwiper.activeIndex];
                const id = slide?.dataset?.cardId;
                if (id) this.$wire.selectedCardId = id;
            }
        };
    }
</script>
