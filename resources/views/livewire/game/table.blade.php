<?php

use App\Game\GameService;
use App\Services\AuthSyncService;
use App\Services\CurrentProfile;
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

    private function resolveSessionKey(?\App\Models\User $profile): string
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

    public function mount(GameService $game, AuthSyncService $authSync, CurrentProfile $current): void
    {
        // Identify who is playing (if anyone is selected).
        $profile = $current->get();
        $this->user = $profile ? $authSync->userDataFor($profile) : null;

        // Resolve a stable session key (per profile) and restore the active sessionId if it exists.
        $this->sessionKey = $this->resolveSessionKey($profile);

        $this->sessionId ??= cache()->get($this->sessionKey) ?: (string) Str::uuid();

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
        // Start a new run with the chosen player faction.
        // (We may retry a few times if the engine randomly mirrors factions.)
        $maxAttempts = 8;
        $attempt = 0;

        do {
            $snapshot = $game->startNewGame($this->sessionId, ['player_faction' => $faction]);
            $enemy = $snapshot['factions']['enemy'] ?? null;
            $attempt++;
        } while ($enemy === $faction && $attempt < $maxAttempts);

        $this->applySnapshot($snapshot);

        // Nudge Alpine to refresh any Swiper instances after the DOM morph.
        $this->dispatch('hand-updated');
        $this->dispatch('planets-updated');

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
    x-data="portOreadTable()"
    x-init="init()"
    x-on:hand-updated.window="refreshHandSwiper()"
    x-on:planets-updated.window="refreshPlanetSwiper()"
    x-on:modal-closed.window="restoreHandIndexSoon()"
    x-on:battle-accepted.window="nudgePlanet($event.detail.planetMove)"
>

    @include('components.game.table.faction-selection', get_defined_vars())
    @include('components.game.table.hud', get_defined_vars())
    @include('components.game.table.planet-stage', get_defined_vars())
    @include('components.game.table.hand-carousel', get_defined_vars())
    @include('components.game.table.card-menu-modal', get_defined_vars())
    @include('components.game.table.planet-modal', get_defined_vars())
    @include('components.game.table.game-over', get_defined_vars())
    @include('components.game.table.battle-overlay', get_defined_vars())
    @include('components.game.table.scripts', get_defined_vars())

</div>
