<?php

use App\Game\GameService;
use App\Services\AuthSyncService;
use App\Services\CurrentProfile;
use App\Models\User;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;
use Illuminate\Support\Str;

layout('components.layouts.app');

new class extends Component {
    public ?string $sessionId = null;
    public ?string $sessionKey = null;

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

    public ?int $savedAt = null;
    public bool $showResumeGate = false;

    private function resolveSessionKey(?User $profile): string
    {
        // Profile-scoped key so each profile can resume its own game.
        // If no profile is selected, fall back to a "guest" bucket.
        return $profile?->id
            ? "skirmish:active_session:profile:{$profile->id}"
            : "skirmish:active_session:guest";
    }

    private function persistActiveSessionId(string $sessionKey, string $sessionId): void
    {
        // Keep the pointer around for a while so navigation / relaunch resumes.
        cache()->put($sessionKey, $sessionId, now()->addDays(30));
    }


    private function hasSavedRun(?string $sessionId): bool
    {
        if (! $sessionId) return false;

        $raw = cache()->get("game:{$sessionId}");
        if (! is_array($raw)) return false;

        return isset($raw['state']) && is_array($raw['state']);
    }

    public function resumeGame(): void
    {
        $this->showResumeGate = false;
    }

    public function abandonAndNewGame(): void
    {
        if ($this->sessionId) {
            cache()->forget("game:{$this->sessionId}");
        }

        $this->sessionId = (string) Str::uuid();
        if ($this->sessionKey) {
            $this->persistActiveSessionId($this->sessionKey, $this->sessionId);
        }

        $this->showResumeGate = false;
        $this->isSelectingFaction = true;
        $this->gameOver = false;
        $this->endReason = null;

        $this->showCardMenu = false;
        $this->showPlanetModal = false;
        $this->selectedPlanet = null;

        $this->dispatch('hand-updated');
        $this->dispatch('planets-updated');
        $this->dispatch('modal-closed');
    }

    public function mount(GameService $game, AuthSyncService $authSync, CurrentProfile $current): void
    {
        // Identify who is playing (if anyone is selected).
        $profile = $current->get();
        $this->user = $profile ? $authSync->userDataFor($profile) : null;

        // Resolve a stable session key (per profile) and restore the active sessionId if it exists.
        $this->sessionKey = $this->resolveSessionKey($profile);

        $existing = cache()->get($this->sessionKey);

        if (is_string($existing) && $this->hasSavedRun($existing)) {
            $this->sessionId = $existing;
            $this->showResumeGate = true;
        }

        $this->sessionId ??= (string) Str::uuid();

        // Persist the pointer immediately (so even "needs faction" screens resume correctly).
        $this->persistActiveSessionId($this->sessionKey, $this->sessionId);

        // Load game snapshot from the driver using the *stable* sessionId.
        $this->applySnapshot($game->snapshot($this->sessionId));

        // Ensure local catalog exists (offline-first)
        if (empty($authSync->getPlanets())) {
            $authSync->syncPlanets(force: true);
        }

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
        $this->savedAt = isset($snapshot['savedAt']) ? (int) $snapshot['savedAt'] : null;

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

        $this->gameOver = (bool)($snapshot['gameOver'] ?? false);
        $this->endReason = $snapshot['endReason'] ?? null;
    }

    public function selectFaction(GameService $game, string $faction): void
    {
        $this->applySnapshot($game->startNewGame($this->sessionId, ['player_faction' => $faction]));

        // Make sure we still resume this same run later.
        if ($this->sessionKey) {
            $this->persistActiveSessionId($this->sessionKey, $this->sessionId);
        }
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
        if (! $this->selectedCardId) return;

        $result = $game->playCard($this->sessionId, $this->playerId, $this->selectedCardId);

        // fire effects first; overlay is wire:ignore
        $effects = $result['effects'] ?? [];
        if (! empty($effects)) {
            $this->dispatch('game-effects', effects: $effects);
        }

        if (isset($result['snapshot'])) {
            $this->applySnapshot($result['snapshot']);
        }

        $this->showCardMenu = false;

        // Ensure we keep resuming the same game after plays.
        if ($this->sessionKey) {
            $this->persistActiveSessionId($this->sessionKey, $this->sessionId);
        }

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

        // Point this profile at the new run so leaving/returning resumes the new one.
        if ($this->sessionKey) {
            $this->persistActiveSessionId($this->sessionKey, $this->sessionId);
        }

        $this->dispatch('hand-updated');
        $this->dispatch('planets-updated');
        $this->dispatch('modal-closed');
    }

    /**
     * Compatibility hook: if any JS/snippets call `$wire.toJSON()`,
     * provide a safe JSON payload of the current table state.
     */
    public function toJSON(): string
    {
        return json_encode([
            'sessionId'      => $this->sessionId,
            'hud'            => $this->hud,
            'planets'        => $this->planets,
            'hand'           => $this->hand,
            'selectedCardId' => $this->selectedCardId,
            'savedAt'        => $this->savedAt,
            'gameOver'       => $this->gameOver,
            'endReason'      => $this->endReason,
            'factions'       => [
                'player' => $this->playerFaction,
                'enemy'  => $this->enemyFaction,
            ],
        ]) ?: '{}';
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
    class="h-dvh w-full bg-transparent text-zinc-100 overflow-hidden flex flex-col"
    x-data="portOreadTable({{ (int) ($savedAt ?? 0) }})"
    x-init="init()"
    x-effect="syncSavedAt($wire.savedAt)"
    x-on:hand-updated.window="refreshHandSwiper()"
    x-on:planets-updated.window="refreshPlanetSwiper()"
    x-on:modal-closed.window="restoreHandIndexSoon()"
    x-on:battle-accepted.window="nudgePlanet($event.detail.planetMove)"
>

    {{-- Resume / New Game Gate --}}
    @if($showResumeGate)
        <div class="fixed inset-0 z-[120] grid place-items-center p-6">
            <div class="absolute inset-0 bg-black/70"></div>

            <div class="relative w-full max-w-sm rounded-[2rem] border border-white/10 bg-zinc-950/90 backdrop-blur p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-[0.25em] text-zinc-400">Port Oread</div>
                    <div class="text-[10px] uppercase tracking-widest text-zinc-500" x-text="savedLabel"></div>
                </div>

                <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="text-xs text-zinc-400">Score</div>
                    <div class="text-3xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
                    <div class="mt-2 text-xs text-zinc-500">
                        Pot VP: <span class="text-zinc-200 font-semibold">{{ $hud['pot_vp'] ?? 0 }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="rounded-2xl bg-white/10 hover:bg-white/15 px-3 py-3 text-sm font-semibold"
                        wire:click="resumeGame"
                    >
                        Resume
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl bg-amber-500/20 hover:bg-amber-500/30 px-3 py-3 text-sm font-semibold text-amber-200"
                        wire:click="abandonAndNewGame"
                    >
                        New Game
                    </button>
                </div>

                <div class="mt-3 text-[11px] text-zinc-500 leading-snug">
                    Resume keeps your current run. New Game abandons it and starts fresh.
                </div>
            </div>
        </div>
    @endif

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
                        <div
                            class="w-40 aspect-[3/4] mb-6 rounded-2xl overflow-hidden border border-white/20 shadow-2xl">
                            <img src="{{ asset('/images/cards/' . $faction . '/1.png') }}"
                                 class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500"
                                 alt="{{ ucfirst($faction) }}">
                        </div>
                        <h2 class="text-2xl font-black uppercase tracking-tighter italic">{{ $faction }}</h2>
                        <div
                            class="mt-2 h-1 w-8 bg-white/20 group-hover:w-16 group-hover:bg-white transition-all duration-500"></div>
                    </button>
                @endforeach
            </div>

            <p class="mt-16 text-xs text-zinc-500 uppercase tracking-widest animate-pulse">Awaiting faction
                authorization...</p>
        </div>
    @endif
    {{-- HUD --}}
    <div class="px-4 pt-4 shrink-0 transition-opacity duration-300" >
        <div class="flex items-center justify-between mb-4">
            @if($user)
                <div class="flex items-center gap-3">
                    <div
                        class="h-8 w-8 rounded-full bg-zinc-800 border border-white/10 flex items-center justify-center overflow-hidden">
                        <span class="text-[10px] font-bold">{{ substr($user['name'] ?? $user['email'], 0, 1) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="text-[10px] font-bold uppercase tracking-widest text-zinc-100">{{ $user['name'] ?? 'Commander' }}</span>
                        <span class="text-[8px] text-zinc-500 uppercase tracking-tighter">{{ count($availablePlanets) }} Planets Synced</span>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <flux:link :href="route('login')"
                               class="text-[10px] uppercase tracking-widest text-zinc-500 hover:text-white transition-colors">
                        Login to Sync
                    </flux:link>
                </div>
            @endif

            <div class="flex items-center gap-4 text-[10px] uppercase tracking-widest text-zinc-500">
                <span x-text="savedLabel"></span>
                <div class="h-1.5 w-1.5 rounded-full {{ $user ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-700' }}"
                     title="{{ $user ? 'Online' : 'Offline' }}"></div>
                <button wire:click="sync" class="text-zinc-500 hover:text-white transition-colors"
                        wire:loading.class="animate-spin">
                    <flux:icon.arrow-path class="size-4"/>
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
    {{-- Planet Stage (Swiper) --}}
    <div
        class="px-4 pt-4 shrink-0"
        x-on:click="activeArea = 'planets'"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm text-zinc-400">Planet(s) in the pot</div>

            <div class="flex items-center gap-2">
                {{-- desktop-friendly nav --}}
                <button
                    type="button"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200 hover:bg-white/10 transition-colors"
                    x-ref="planetPrev"
                    x-on:click="planetSwiper?.slidePrev()"
                    :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                >
                    ◀
                </button>
                <button
                    type="button"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200 hover:bg-white/10 transition-colors"
                    x-ref="planetNext"
                    x-on:click="planetSwiper?.slideNext()"
                    :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                >
                    ▶
                </button>
            </div>
        </div>

        <div class="mt-3">
            <div class="swiper max-w-[22rem] sm:max-w-[26rem] mx-auto overflow-visible planet-swiper" x-ref="planetSwiper">
                <div class="swiper-wrapper overflow-visible">
                    @foreach($planets as $planet)
                        @php
                            $filename = $planet['filename'] ?? null;

                            // Gameplay planet image (round orb)
                            $planetImg = $planet['img'] ?? ($filename ? asset('/images/planets/' . $filename) : null);

                            // Modal uses the *card* art version.
                            $planetCardImg =
                                $planet['card_img']
                                ?? ($filename ? asset('/images/cards/planets/' . $filename) : null)
                                ?? (is_string($planetImg) ? str_replace('/images/planets/', '/images/cards/planets/', $planetImg) : null);

                            $planetName  = $planet['name'] ?? 'Unknown';
                            $planetClass = $planet['class'] ?? $planet['planet_class'] ?? null;
                            $planetType  = $planet['type'] ?? $planet['planet_type'] ?? null;
                            $planetVp    = $planet['vp'] ?? 0;
                        @endphp

                        <div class="swiper-slide planet-slide" wire:key="planet-{{ $planet['id'] }}-{{ $loop->index }}">
                            <button
                                type="button"
                                class="planet-node group relative block mx-auto w-[18rem] sm:w-[20rem] aspect-square select-none"
                                wire:click="openPlanetModal('{{ $planet['id'] }}')"
                            >
                                {{-- aura + scanline layer --}}
                                <span class="absolute inset-0 z-0 rounded-full planet-aura"></span>
                                {{-- planet image --}}
                                @if($planetImg)
                                    <img
                                        src="{{ $planetImg }}"
                                        class="relative z-20 w-full h-full rounded-full object-contain planet-orb"
                                        alt="{{ $planetName }}"
                                        draggable="false"
                                    />
                                    <span
                                        x-show="planetCount > 1"
                                        style="display: none"
                                        class="absolute left-4 bottom-4 z-30 inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/40 px-2 py-1 text-[10px] uppercase tracking-widest text-zinc-200"
                                    >
                                            <span class="inline-flex gap-1">
                                                <template x-for="i in planetCount" :key="i">
                                                    <span
                                                        class="h-1.5 w-1.5 rounded-full"
                                                        :class="(i - 1) === planetIndex ? 'bg-amber-300/80' : 'bg-white/25'"
                                                    ></span>
                                                </template>
                                            </span>
                                            <span class="text-zinc-300" x-text="`${planetCount} in pot`"></span>
                                        </span>
                                @else
                                    <div class="relative z-10 w-full h-full rounded-full grid place-items-center bg-white/5 border border-white/10 text-xs text-zinc-300">
                                        No image
                                    </div>
                                @endif

                                {{-- top left: class --}}
                                @if($planetClass)
                                    <span class="absolute top-3 left-3 z-20 rounded-full bg-zinc-950/70 border border-white/10 px-2.5 py-1 backdrop-blur">
                                    {{-- icon hook (drop in your SVG component later) --}}
                                        {{-- <x-icons.planet-class :value="$planetClass" class="size-3 text-amber-300" /> --}}
                                    <span class="text-[9px] uppercase tracking-widest text-zinc-200">{{ $planetClass }}</span>
                                </span>
                                @endif

                                {{-- top right: type --}}
                                @if($planetType)
                                    <span class="absolute top-3 right-3 z-20 rounded-full bg-zinc-950/70 border border-white/10 px-2.5 py-1 backdrop-blur">
                                    {{-- <x-icons.planet-type :value="$planetType" class="size-3 text-amber-300" /> --}}
                                    <span class="text-[9px] uppercase tracking-widest text-zinc-200">{{ $planetType }}</span>
                                </span>
                                @endif

                                {{-- bottom label --}}
                                <span class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 w-[86%] text-center">
                                <span class="block planet-title text-white font-black uppercase tracking-wider text-base sm:text-lg leading-tight truncate">
                                    {{ $planetName }}
                                </span>
                                <span class="mt-1 inline-flex items-center justify-center gap-2">
                                    <span class="text-[11px] text-amber-300 font-black tracking-widest">{{ $planetVp }} VP</span>
                                </span>
                            </span>

                                {{-- stash card image for modal --}}
                                <span class="hidden" data-planet-card-img="{{ $planetCardImg }}"></span>
                            </button>
                        </div>
                    @endforeach
                </div>

                {{-- pagination bullets (clickable) --}}
                <div class="mt-2 flex justify-center" x-ref="planetPagination"></div>
            </div>
        </div>

        @if(count($planets) > 1)
            <div class="mt-2 text-xs text-zinc-500">
                Tip: swipe on mobile; on desktop you can use ◀ ▶, bullet dots, or arrow keys.
            </div>
        @endif
    </div>



    {{-- Spacer to push hand to bottom --}}
    <div class="flex-1"></div>

    {{-- Hand Carousel --}}
    <div
        class="px-4 pb-8 pt-6 shrink-0 transition-all duration-500"
        x-on:click="activeArea = 'hand'"
    >
        <div class="relative">
            <div class="swiper" x-ref="handSwiper">
                <div class="swiper-wrapper overflow-visible">
                    @foreach($hand as $card)
                        <div class="swiper-slide" data-card-id="{{ $card['id'] }}" wire:key="hand-{{ $card['id'] }}">
                            <div class="select-none">
                                <div class="relative">
                                    <img
                                        src="{{ asset($card['img']) }}"
                                        class="block w-full h-auto rounded-2xl border border-white/10"
                                        draggable="false"
                                    />

                                    {{-- merc pip --}}
                                    @if(($card['isMerc'] ?? false) === true)
                                        <div
                                            class="absolute top-2 right-2 h-3 w-3 rounded-full border border-white/30 bg-white/20"></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div
                class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-zinc-950 to-transparent z-10"></div>
            <div
                class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-zinc-950 to-transparent z-10"></div>
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
            @php
                $selected = collect($hand)->firstWhere('id', $selectedCardId);
            @endphp

            @if($selected)
                <div class="px-4">
                    <img src="{{ asset($selected['img']) }}"
                         class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl" draggable="false"/>
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
                        <button class="py-6 text-lg font-bold hover:bg-white/5 active:bg-white/10 transition-colors"
                                wire:click="playSelected">
                            PLAY
                        </button>
                        <button
                            class="py-6 text-lg font-medium text-zinc-400 border-l border-white/10 hover:bg-white/5 active:bg-white/10 transition-colors"
                            wire:click="closeCardMenu">
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
                    @php
                        /**
                         * Turn a public path (e.g. "/images/...") into a full URL via asset().
                         * If it is already an absolute URL, leave it alone.
                         */
                        $pathToUrl = function (?string $path): ?string {
                            if (! $path) return null;

                            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                                return $path;
                            }

                            return asset(ltrim($path, '/'));
                        };

                        $spImgUrl = $pathToUrl($selectedPlanet['img'] ?? null);
                        $spCardUrl = $pathToUrl($selectedPlanet['card_img'] ?? null)
                            ?? ($spImgUrl ? str_replace('/images/planets/', '/images/cards/planets/', $spImgUrl) : null);
                    @endphp

                    <img
                        src="{{ $spCardUrl ?? $spImgUrl }}"
                        class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl"
                        alt="{{ $selectedPlanet['name'] ?? 'Planet' }}"
                        draggable="false"
                    />
                </div>

                <div class="bg-zinc-950 border-t border-white/10 safe-area-bottom">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold">{{ $selectedPlanet['name'] }}</h2>
                                <p class="text-zinc-400 uppercase tracking-widest text-xs mt-1">{{ $selectedPlanet['type'] }}</p>
                            </div>
                            <div
                                class="h-12 w-12 rounded-full border border-amber-400/20 bg-amber-400/10 grid place-items-center text-amber-400 font-black">
                                {{ $selectedPlanet['vp'] }}
                            </div>
                        </div>

                        <p class="mt-4 text-zinc-300 leading-relaxed italic">
                            "{{ $selectedPlanet['flavor'] }}"
                        </p>
                    </div>

                    <div class="border-t border-white/5">
                        <button
                            class="w-full py-6 text-lg font-medium text-zinc-400 hover:bg-white/5 active:bg-white/10 transition-colors"
                            wire:click="closePlanetModal">
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
                        <div class="shatter-container rounded-2xl overflow-hidden"
                             :class="{ 'winner-highlight': $store.battle.outcome === 'win', 'shattered': $store.battle.shattered === 'player' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece"
                                     :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.playerImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'win'"
                             class="absolute -top-4 -left-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">
                            Winner
                        </div>
                    </div>

                    {{-- Enemy Card --}}
                    <div
                        class="absolute w-40 aspect-[3/4] transition-all duration-1000"
                        :class="$store.battle.shattered === 'player' ? 'z-30 scale-125 translate-x-0' : ($store.battle.shattered === 'enemy' ? 'opacity-0 scale-50 translate-x-24' : 'translate-x-24')"
                    >
                        <div class="shatter-container rounded-2xl overflow-hidden"
                             :class="{ 'winner-highlight': $store.battle.outcome === 'loss', 'shattered': $store.battle.shattered === 'enemy' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece"
                                     :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.enemyImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'loss'"
                             class="absolute -top-4 -right-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">
                            Winner
                        </div>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <div x-show="$store.battle.outcome === 'win'" class="text-2xl font-bold text-white tracking-tight">
                        VICTORY
                    </div>
                    <div x-show="$store.battle.outcome === 'loss'"
                         class="text-2xl font-bold text-zinc-500 tracking-tight">DEFEAT
                    </div>
                    <div x-show="$store.battle.outcome === 'tie'"
                         class="text-2xl font-bold text-amber-400 tracking-tight animate-bounce">TIE!
                    </div>

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
                        <div
                            class="absolute inset-0 bg-zinc-200 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @once
        <style>
            /* --- Planet HUD (CSS-only) --- */
            @keyframes planetGlow {
                0%, 100% { box-shadow: 0 0 0 rgba(245, 158, 11, 0), 0 0 0 rgba(79,116,146,0); }
                50%      { box-shadow: 0 0 22px rgba(79,116,146,.55), 0 0 14px rgba(245,158,11,.18); }
            }

            @keyframes planetScan {
                0%   { background-position: 0 0, 0 0; }
                100% { background-position: 0 240px, 0 0; }
            }

            @keyframes planetFloat {
                0%,100% { transform: translateY(0); }
                50%     { transform: translateY(-4px); }
            }

            .planet-node {
                animation: planetFloat 7s ease-in-out infinite;
            }

            .planet-orb {
                background: radial-gradient(circle at 30% 30%, rgba(79,116,146,.18), rgba(11,36,66,.30) 55%, rgba(0,0,0,0) 75%);
                filter: drop-shadow(0 12px 28px rgba(0,0,0,.55));
            }

            .planet-aura {
                border: 1px solid rgba(255,255,255,.10);
                background:
                    radial-gradient(circle at 20% 20%, rgba(79,116,146,.30), rgba(11,36,66,.22) 45%, rgba(0,0,0,0) 70%),
                    radial-gradient(circle at 70% 75%, rgba(245,158,11,.10), rgba(0,0,0,0) 60%);
                animation: planetGlow 3.6s ease-in-out infinite;
                overflow: hidden;
            }

            .planet-aura::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    repeating-linear-gradient(
                        to bottom,
                        rgba(255,255,255,.08) 0px,
                        rgba(255,255,255,.08) 1px,
                        rgba(0,0,0,0) 3px,
                        rgba(0,0,0,0) 6px
                    );
                opacity: .12;
                mix-blend-mode: overlay;
                animation: planetScan 4.25s linear infinite;
                pointer-events: none;
            }

            .planet-aura::after {
                content: "";
                position: absolute;
                inset: -2px;
                border-radius: 9999px;
                border: 2px solid rgba(79,116,146,.25);
                opacity: .5;
                pointer-events: none;
            }

            .planet-title {
                text-shadow: 0 1px 10px rgba(0,0,0,.70);
            }

            .planet-node:focus-visible {
                outline: 2px solid rgba(245,158,11,.55);
                outline-offset: 4px;
                border-radius: 9999px;
            }

            /* --- Planet swiper "peek" behavior (like ships) --- */
            .planet-swiper { overflow: visible; }
            .planet-swiper .swiper-wrapper { overflow: visible; }

            .planet-swiper .swiper-slide {
                transition: transform 260ms ease, filter 260ms ease, opacity 260ms ease;
                opacity: .45;
                filter: blur(5px) grayscale(.45);
                transform: scale(.90);
            }

            /* Only show the NEXT planet peeking on the right. Hide the previous one to avoid
               left-peek confusion in LTR. */
            .planet-swiper .swiper-slide-prev {
                opacity: 0;
                filter: blur(10px) grayscale(.60);
                transform: scale(.86);
                pointer-events: none;
            }

            .planet-swiper .swiper-slide-next {
                opacity: .72;
                filter: blur(3px) grayscale(.28);
                transform: translateX(10%) scale(.93);
            }

            .planet-swiper .swiper-slide-active {
                opacity: 1;
                filter: none;
                transform: scale(1);
            }

        </style>

        <script>
            function portOreadTable(initialSavedAt) {
                return {
                    savedAt: initialSavedAt || null,
                    savedLabel: '',

                    handSwiper: null,
                    planetSwiper: null,
                    planetCount: 0,
                    planetIndex: 0,
                    lastPlanetCount: null,
                    handIndex: 0,
                    activeArea: 'hand',

                    // used for win/loss planet nudge
                    planetNudge: null,

                    init() {
                        this.ensureBattleStore();
                        this.tickSavedLabel();
                        setInterval(() => this.tickSavedLabel(), 25000);

                        this.initHandSwiper();
                        this.initPlanetSwiper();
                    },

                    syncSavedAt(v) {
                        if (!v) return;
                        const next = parseInt(v, 10);
                        if (!Number.isFinite(next) || next <= 0) return;
                        this.savedAt = next;
                        this.tickSavedLabel();
                    },

                    tickSavedLabel() {
                        if (!this.savedAt) {
                            this.savedLabel = 'Not saved';
                            return;
                        }

                        const now = Math.floor(Date.now() / 1000);
                        const diff = Math.max(0, now - this.savedAt);

                        if (diff < 10) {
                            this.savedLabel = 'Saved just now';
                            return;
                        }
                        if (diff < 60) {
                            this.savedLabel = `Saved ${diff}s ago`;
                            return;
                        }

                        const mins = Math.floor(diff / 60);
                        if (mins < 60) {
                            this.savedLabel = `Saved ${mins}m ago`;
                            return;
                        }

                        const hrs = Math.floor(mins / 60);
                        if (hrs < 24) {
                            this.savedLabel = `Saved ${hrs}h ago`;
                            return;
                        }

                        const days = Math.floor(hrs / 24);
                        this.savedLabel = `Saved ${days}d ago`;
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
                                this.enemyImg = e.enemy?.img || '';
                                this.outcome = e.outcome || 'tie';
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
                                    detail: {planetMove: move, potEscalated: escalated}
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
                        if (!this.$refs?.handSwiper) return;

                        this.handSwiper = new Swiper(this.$refs.handSwiper, {
                            slidesPerView: 1.35,
                            centeredSlides: true,
                            spaceBetween: 14,
                            slideToClickedSlide: false,
                        });

                        this.handSwiper.on('slideChange', () => {
                            this.handIndex = this.handSwiper.activeIndex;
                            this.syncSelectedToActiveSlide();
                        });

                        // Click behavior:
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
                        if (!this.$refs?.planetSwiper) return;

                        this.planetSwiper = new Swiper(this.$refs.planetSwiper, {
                            slidesPerView: 1.15,
                            centeredSlides: true,
                            spaceBetween: 18,
                            grabCursor: true,
                            simulateTouch: true,
                            // UX: invert swipe so swiping LEFT feels like "back" (prev),
                            // swiping RIGHT feels like "forward" (next) for your mental model.
                            touchRatio: -1,
                            slideToClickedSlide: true,
                            keyboard: { enabled: true },
                            mousewheel: { forceToAxis: true },
                            pagination: {
                                el: this.$refs.planetPagination,
                                clickable: true,
                                dynamicBullets: true,
                            },
                        });

                        // keep Alpine in sync for the in-orb pot dots + labels
                        this.planetCount = this.planetSwiper.slides.length;
                        this.planetIndex = this.planetSwiper.activeIndex;

                        this.planetSwiper.on('slideChange', () => {
                            this.planetIndex = this.planetSwiper.activeIndex;
                            this.planetCount = this.planetSwiper.slides.length;
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
                            const len = this.planetSwiper ? this.planetSwiper.slides.length : (this.$refs?.planetSwiper?.querySelectorAll?.('.swiper-slide')?.length ?? 0);

                            if (this.planetSwiper) {
                                this.planetSwiper.update();
                            } else {
                                this.initPlanetSwiper();
                            }

                            if (!this.planetSwiper) return;

                            // Update counts for dot UI
                            this.planetCount = this.planetSwiper.slides.length;

                            // Only "snap" when the planet stack changed (tie escalation / resolve)
                            if (this.lastPlanetCount !== this.planetCount) {
                                this.lastPlanetCount = this.planetCount;

                                // Chronological order: [first, second, third]
                                // Always show the newest planet (last index)
                                const newest = Math.max(0, this.planetCount - 1);
                                this.planetSwiper.slideTo(newest, 0);
                                this.planetIndex = newest;
                            } else {
                                // keep index in sync if user swiped
                                this.planetIndex = this.planetSwiper.activeIndex;
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
                    },
                };
            }
        </script>
    @endonce
</div>
