<?php

use App\Game\GameService;
use App\Services\AuthSyncService;
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

    public bool $showPlanetModal = false;
    public ?array $selectedPlanet = null;

    public bool $gameOver = false;
    public ?string $endReason = null;

    public bool $isSelectingFaction = false;
    public ?string $playerFaction = null;
    public ?string $enemyFaction = null;

    public ?array $user = null;
    public array $availablePlanets = [];

    public function mount(GameService $game, AuthSyncService $authSync): void
    {
        $this->sessionId ??= (string) Str::uuid();
        $this->applySnapshot($game->snapshot($this->sessionId));

        $this->user = $authSync->getUser();
        $this->availablePlanets = $authSync->getPlanets();
    }

    public function sync(AuthSyncService $authSync): void
    {
        if ($authSync->syncPlanets()) {
            $this->availablePlanets = $authSync->getPlanets();
            $this->dispatch('planets-synced');
        }
    }

    private function applySnapshot(array $snapshot): void
    {
        if ($snapshot['needs_faction'] ?? false) {
            $this->isSelectingFaction = true;
            return;
        }

        $this->isSelectingFaction = false;

        $this->hud = $snapshot['hud'] ?? $this->hud;
        $this->planets = $snapshot['planets'] ?? [];
        $this->hand = $snapshot['hand'] ?? [];

        $this->playerFaction = $snapshot['factions']['player'] ?? null;
        $this->enemyFaction = $snapshot['factions']['enemy'] ?? null;

        $this->selectedCardId = $snapshot['selectedCardId']
            ?? ($this->hand[0]['id'] ?? null);

        $this->gameOver = (bool) ($snapshot['gameOver'] ?? false);
        $this->endReason = $snapshot['endReason'] ?? null;
    }

    public function selectFaction(GameService $game, string $faction): void
    {
        $this->applySnapshot($game->startNewGame($this->sessionId, ['player_faction' => $faction]));
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

    public function openPlanetModal(string $planetId): void
    {
        $this->selectedPlanet = collect($this->planets)->firstWhere('id', $planetId);
        $this->showPlanetModal = true;
    }

    public function closePlanetModal(): void
    {
        $this->showPlanetModal = false;
        $this->selectedPlanet = null;
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
        $this->isSelectingFaction = true;
        $this->gameOver = false;
        $this->endReason = null;

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
    class="h-dvh w-full bg-zinc-950 text-zinc-100 overflow-hidden flex flex-col"
    x-data="portOreadTable()"
    x-init="init()"
    x-on:hand-updated.window="refreshHandSwiper()"
    x-on:planets-updated.window="refreshPlanetSwiper()"
    x-on:modal-closed.window="restoreHandIndexSoon()"
    x-on:battle-accepted.window="nudgePlanet($event.detail.planetMove)"
>
    {{-- Faction Selection --}}
    @if($isSelectingFaction)
        <div class="fixed inset-0 z-[100] bg-zinc-950 flex flex-col items-center justify-center p-6 text-center">
            <h2 class="text-xs text-zinc-500 uppercase tracking-[0.3em] mb-4">Tactical Deployment</h2>
            <h1 class="text-4xl font-bold tracking-tighter mb-12 italic">PORT OREAD</h1>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full max-w-5xl">
                @foreach(['neupert', 'wami', 'rogers'] as $faction)
                    <button
                        wire:click="selectFaction('{{ $faction }}')"
                        class="group relative flex flex-col items-center p-8 rounded-[2rem] border border-white/10 bg-white/5 hover:bg-white/10 transition-all hover:-translate-y-2 active:scale-95"
                    >
                        <div class="w-40 aspect-[3/4] mb-6 rounded-2xl overflow-hidden border border-white/20 shadow-2xl">
                            <img src="/images/cards/{{ $faction }}/1.png" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500" alt="{{ ucfirst($faction) }}">
                        </div>
                        <h2 class="text-2xl font-black uppercase tracking-tighter italic">{{ $faction }}</h2>
                        <div class="mt-2 h-1 w-8 bg-white/20 group-hover:w-16 group-hover:bg-white transition-all duration-500"></div>
                    </button>
                @endforeach
            </div>

            <p class="mt-16 text-xs text-zinc-500 uppercase tracking-widest animate-pulse">Awaiting faction authorization...</p>
        </div>
    @endif
    {{-- HUD --}}
    <div class="px-4 pt-4 shrink-0 transition-opacity duration-300" :class="activeArea !== 'hand' && activeArea !== 'planets' ? 'opacity-50' : 'opacity-100'">
        <div class="flex items-center justify-between mb-4">
            @if($user)
                <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-zinc-800 border border-white/10 flex items-center justify-center overflow-hidden">
                        <span class="text-[10px] font-bold">{{ substr($user['name'] ?? $user['email'], 0, 1) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-zinc-100">{{ $user['name'] ?? 'Commander' }}</span>
                        <span class="text-[8px] text-zinc-500 uppercase tracking-tighter">{{ count($availablePlanets) }} Planets Synced</span>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <flux:link :href="route('login')" class="text-[10px] uppercase tracking-widest text-zinc-500 hover:text-white transition-colors">Login to Sync</flux:link>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <div class="h-1.5 w-1.5 rounded-full {{ $user ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-700' }}" title="{{ $user ? 'Online' : 'Offline' }}"></div>
                <button wire:click="sync" class="text-zinc-500 hover:text-white transition-colors" wire:loading.class="animate-spin">
                    <flux:icon.arrow-path class="size-4" />
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="text-xs text-zinc-400">Score</div>
                <div class="text-xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
            </div>

            <div class="space-y-1 text-right">
                <div class="text-xs text-zinc-400">Pot VP</div>
                <div
                    class="text-xl font-semibold transition-all duration-500"
                    :class="$store.battle?.potEscalated ? 'pot-escalate text-amber-400' : ''"
                >
                    {{ $hud['pot_vp'] ?? 0 }}
                </div>
            </div>
        </div>
    </div>

    {{-- Planet Stage (Swiper) --}}
    <div
        class="px-4 pt-4 shrink-0 transition-all duration-500"
        :class="activeArea !== 'planets' ? 'opacity-40 grayscale-[0.5] scale-[0.98]' : 'opacity-100 scale-100'"
        x-on:click="activeArea = 'planets'"
    >
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
                                <div
                                    class="relative aspect-[3/4] w-32 mx-auto rounded-2xl border border-white/10 bg-white/5 overflow-hidden cursor-pointer active:scale-95 transition-transform group"
                                    wire:click="openPlanetModal('{{ $planet['id'] }}')"
                                >
                                    <img src="{{ $planet['img'] }}" class="w-full h-full object-cover grayscale-[0.2] group-hover:grayscale-0 transition-all" alt="{{ $planet['name'] }}">

                                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent p-3 flex flex-col justify-end">
                                        <div class="text-[10px] font-bold text-white/90 uppercase tracking-widest truncate">{{ $planet['name'] }}</div>
                                        <div class="flex justify-between items-center mt-1">
                                            <div class="px-1.5 py-0.5 rounded-md bg-white/10 border border-white/10 text-[8px] text-zinc-300 uppercase">{{ $planet['type'] }}</div>
                                            <div class="text-xs font-black text-amber-400">{{ $planet['vp'] }} VP</div>
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

    {{-- Spacer to push hand to bottom --}}
    <div class="flex-1"></div>

    {{-- Hand Carousel --}}
    <div
        class="px-4 pb-8 pt-6 shrink-0 transition-all duration-500"
        :class="activeArea !== 'hand' ? 'opacity-40 grayscale-[0.5] scale-[0.98]' : 'opacity-100 scale-100'"
        x-on:click="activeArea = 'hand'"
    >
        <div class="relative">
            <div class="swiper" x-ref="handSwiper">
                <div class="swiper-wrapper">
                    @foreach($hand as $card)
                        <div class="swiper-slide" data-card-id="{{ $card['id'] }}" wire:key="hand-{{ $card['id'] }}">
                            <div class="select-none">
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
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-zinc-950 to-transparent z-10"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-zinc-950 to-transparent z-10"></div>
        </div>
    </div>

    {{-- Select / Play Modal --}}
    <div
        x-cloak
        x-show="$wire.showCardMenu"
        class="fixed inset-0 z-40 flex flex-col justify-end"
    >
        <div
            class="absolute inset-0 bg-black/60"
            x-show="$wire.showCardMenu"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="$wire.closeCardMenu()"
        ></div>

        <div
            class="relative flex flex-col"
            x-show="$wire.showCardMenu"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            style="will-change: transform;"
        >
            @php($selected = collect($hand)->firstWhere('id', $selectedCardId))

            @if($selected)
                <div class="px-4">
                    <img src="{{ $selected['img'] }}" class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl" draggable="false" />
                </div>

                <div class="bg-zinc-950 border-t border-white/10 safe-area-bottom">
                    @if(($selected['isMerc'] ?? false) === true)
                        <div class="p-4 text-center border-b border-white/5 bg-white/5">
                            <div class="text-[10px] text-zinc-400 uppercase tracking-widest">Mercenary</div>
                            <div class="mt-1 text-sm font-bold">
                                {{ $selected['merc']['name'] ?? 'Mercenary' }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-300 leading-relaxed">
                                <span class="font-bold text-white">{{ $mercAbilityTitle($selected['merc']['ability_type'] ?? null) }}:</span>
                                {!! $mercAbilityDescription($selected['merc']['ability_type'] ?? null, $selected['merc']['params'] ?? []) !!}
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2">
                        <button class="py-6 text-lg font-bold hover:bg-white/5 active:bg-white/10 transition-colors" wire:click="playSelected">
                            PLAY
                        </button>
                        <button class="py-6 text-lg font-medium text-zinc-400 border-l border-white/10 hover:bg-white/5 active:bg-white/10 transition-colors" wire:click="closeCardMenu">
                            CANCEL
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Planet View Modal --}}
    <div
        x-cloak
        x-show="$wire.showPlanetModal"
        class="fixed inset-0 z-40 flex flex-col justify-end"
    >
        <div
            class="absolute inset-0 bg-black/60"
            x-show="$wire.showPlanetModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="$wire.closePlanetModal()"
        ></div>

        <div
            class="relative flex flex-col"
            x-show="$wire.showPlanetModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            style="will-change: transform;"
        >
            @if($selectedPlanet)
                <div class="px-4">
                    <img src="{{ $selectedPlanet['img'] }}" class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl" draggable="false" />
                </div>

                <div class="bg-zinc-950 border-t border-white/10 safe-area-bottom">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold">{{ $selectedPlanet['name'] }}</h2>
                                <p class="text-zinc-400 uppercase tracking-widest text-xs mt-1">{{ $selectedPlanet['type'] }}</p>
                            </div>
                            <div class="h-12 w-12 rounded-full border border-amber-400/20 bg-amber-400/10 grid place-items-center text-amber-400 font-black">
                                {{ $selectedPlanet['vp'] }}
                            </div>
                        </div>

                        <p class="mt-4 text-zinc-300 leading-relaxed italic">
                            "{{ $selectedPlanet['flavor'] }}"
                        </p>
                    </div>

                    <div class="border-t border-white/5">
                        <button class="w-full py-6 text-lg font-medium text-zinc-400 hover:bg-white/5 active:bg-white/10 transition-colors" wire:click="closePlanetModal">
                            CLOSE
                        </button>
                    </div>
                </div>
            @endif
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
                <div class="relative h-80 w-full flex items-center justify-center">
                    {{-- Player Card --}}
                    <div
                        class="absolute w-40 aspect-[3/4] transition-all duration-1000"
                        :class="$store.battle.shattered === 'enemy' ? 'z-30 scale-125 translate-x-0' : ($store.battle.shattered === 'player' ? 'opacity-0 scale-50 -translate-x-24' : '-translate-x-24')"
                    >
                        <div class="shatter-container rounded-2xl overflow-hidden" :class="{ 'winner-highlight': $store.battle.outcome === 'win', 'shattered': $store.battle.shattered === 'player' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece" :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.playerImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'win'" class="absolute -top-4 -left-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">Winner</div>
                    </div>

                    {{-- Enemy Card --}}
                    <div
                        class="absolute w-40 aspect-[3/4] transition-all duration-1000"
                        :class="$store.battle.shattered === 'player' ? 'z-30 scale-125 translate-x-0' : ($store.battle.shattered === 'enemy' ? 'opacity-0 scale-50 translate-x-24' : 'translate-x-24')"
                    >
                        <div class="shatter-container rounded-2xl overflow-hidden" :class="{ 'winner-highlight': $store.battle.outcome === 'loss', 'shattered': $store.battle.shattered === 'enemy' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece" :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.enemyImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'loss'" class="absolute -top-4 -right-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">Winner</div>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <div x-show="$store.battle.outcome === 'win'" class="text-2xl font-bold text-white tracking-tight">VICTORY</div>
                    <div x-show="$store.battle.outcome === 'loss'" class="text-2xl font-bold text-zinc-500 tracking-tight">DEFEAT</div>
                    <div x-show="$store.battle.outcome === 'tie'" class="text-2xl font-bold text-amber-400 tracking-tight animate-bounce">TIE!</div>

                    <div class="mt-2 text-sm text-zinc-400 h-10 flex items-center justify-center">
                        <span x-text="$store.battle.message"></span>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <button
                        type="button"
                        class="group relative px-8 py-3 bg-white text-black font-bold rounded-full overflow-hidden transition-all hover:scale-105 active:scale-95"
                        x-on:click="$store.battle.accept()"
                    >
                        <span class="relative z-10">CONTINUE</span>
                        <div class="absolute inset-0 bg-zinc-200 translate-y-full group-hover:translate-y-0 transition-transform"></div>
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
            activeArea: 'hand',

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

            getShatterStyle(i) {
                const rows = 4;
                const cols = 4;
                const r = Math.floor(i / cols);
                const c = i % cols;
                const w = 100 / cols;
                const h = 100 / rows;
                const tx = (Math.random() - 0.5) * 500;
                const ty = (Math.random() - 0.5) * 500;
                const tr = (Math.random() - 0.5) * 500;
                return `clip-path: inset(${r * h}% ${(cols - 1 - c) * w}% ${(rows - 1 - r) * h}% ${c * w}%); --tx: ${tx}px; --ty: ${ty}px; --tr: ${tr}deg;`;
            },

            ensureBattleStore() {
                const build = () => ({
                    visible: false,
                    playerImg: '',
                    enemyImg: '',
                    outcome: 'tie',
                    planetMove: 'none',
                    shattered: null,
                    potEscalated: false,
                    message: '',

                    run(effects) {
                        if (!effects || !effects.length) return;
                        const e = effects.find(x => x.type === 'battle_resolve');
                        if (!e) return;

                        this.playerImg = e.player?.img || '';
                        this.enemyImg  = e.enemy?.img  || '';
                        this.outcome   = e.outcome || 'tie';
                        this.planetMove = e.planetMove || 'none';
                        this.shattered = null;
                        this.potEscalated = (this.outcome === 'tie');
                        this.message = this.pickMessage(this.outcome, e.player?.strength || 0, e.enemy?.strength || 0);

                        // SINGLE PLAYER: stay up until user accepts
                        this.visible = true;

                        if (this.outcome === 'win') {
                            setTimeout(() => this.shattered = 'enemy', 600);
                        } else if (this.outcome === 'loss') {
                            setTimeout(() => this.shattered = 'player', 600);
                        }
                    },

                    pickMessage(outcome, pStr, eStr) {
                        if (outcome === 'tie') return "The conflict escalates. Pot VP increased!";

                        const diff = Math.abs(pStr - eStr);
                        const tier = diff >= 8 ? 'crushing' : (diff >= 3 ? 'solid' : 'narrow');

                        const pools = {
                            win: {
                                crushing: [
                                    "Total annihilation. The planet is yours.",
                                    "The enemy was vaporized. Sector claimed.",
                                    "Crushing dominance. Resistance was futile.",
                                    "Absolute conquest achieved. A glorious day.",
                                    "The enemy fled in terror. You claimed the planet."
                                ],
                                solid: [
                                    "You claimed the planet.",
                                    "Victory is yours! The planet is secured.",
                                    "The sector falls under your control.",
                                    "Resistance has been crushed.",
                                    "Strategic victory. Sector secured."
                                ],
                                narrow: [
                                    "A hard-fought victory. The planet is yours.",
                                    "Narrowly secured the sector.",
                                    "The enemy retreats, but barely.",
                                    "A foothold established, by the skin of your teeth.",
                                    "Victory, though at a cost."
                                ]
                            },
                            loss: {
                                crushing: [
                                    "Our forces were annihilated. The planet is lost.",
                                    "A humiliating rout. The enemy reigns supreme.",
                                    "The enemy had ruthlessly occupied the planet, leaving nothing behind.",
                                    "Complete catastrophic failure. Sector lost.",
                                    "We were overwhelmed. The enemy holds the world."
                                ],
                                solid: [
                                    "The enemy had ruthlessly occupied the planet.",
                                    "Our forces were repelled.",
                                    "Sector lost to enemy control.",
                                    "The enemy's grip on this world tightens.",
                                    "Withdrawal confirmed. The planet is lost."
                                ],
                                narrow: [
                                    "A bitter defeat. We almost had them.",
                                    "The enemy held their ground by a narrow margin.",
                                    "Forced to retreat. So close, yet so far.",
                                    "The sector remains contested, but out of our hands.",
                                    "They held on by a thread."
                                ]
                            }
                        };

                        const pool = pools[outcome][tier];
                        return pool[Math.floor(Math.random() * pool.length)];
                    },

                    accept() {
                        const move = this.planetMove || 'none';
                        const escalated = this.potEscalated;
                        this.visible = false;
                        this.planetMove = 'none';
                        this.shattered = null;
                        this.potEscalated = false;

                        window.dispatchEvent(new CustomEvent('battle-accepted', {
                            detail: { planetMove: move, potEscalated: escalated }
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
                    // IMPORTANT: we handle click ourselves, so Swiper doesn't auto-slide then we open modal
                    slideToClickedSlide: false,
                });

                this.handSwiper.on('slideChange', () => {
                    this.handIndex = this.handSwiper.activeIndex;
                    this.syncSelectedToActiveSlide();
                });

                // ✅ Click behavior:
                // - click peeker => center it
                // - click active => open modal
                this.handSwiper.on('click', (swiper) => {
                    this.activeArea = 'hand';
                    const idx = swiper.clickedIndex;
                    if (idx === null || idx === undefined) return;

                    const slide = swiper.slides[idx];
                    const cardId = slide?.dataset?.cardId;
                    if (!cardId) return;

                    const active = swiper.activeIndex;

                    // peeker click centers only
                    if (idx !== active) {
                        this.handIndex = idx;
                        swiper.slideTo(idx);
                        return;
                    }

                    // active click opens modal
                    this.handIndex = active;
                    this.$wire.openCardMenu(cardId);
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

                this.planetSwiper.on('click', () => {
                    this.activeArea = 'planets';
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

            // kept for now (no longer used by slides)
            handleHandClick(index, cardId) {
                if (!this.handSwiper) {
                    this.$wire.openCardMenu(cardId);
                    return;
                }

                const active = this.handSwiper.activeIndex;

                if (index !== active) {
                    this.handIndex = index;
                    this.handSwiper.slideTo(index);
                    return;
                }

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
