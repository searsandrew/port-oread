<?php

namespace App\Game\Drivers;

use App\Game\Contracts\GameDriver;

class LocalSkirmishDriver implements GameDriver
{
    public function snapshot(string $sessionId): array
    {
        $state = $this->loadState($sessionId);

        // TODO (later): hydrate engine from $state and return a render snapshot.
        // $engine = \StellarSkirmish\Engine::fromState($state['engine'] ?? []);
        // return $engine->toRenderSnapshot();

        return $state['snapshot'] ?? $this->emptySnapshot();
    }

    public function playCard(string $sessionId, string $playerId, string $cardId): array
    {
        $state = $this->loadState($sessionId);

        // --- PLACEHOLDER SNAPSHOT (until engine is wired) ---
        // In the real version, you’ll compute this from the engine.
        $snapshot = $state['snapshot'] ?? $this->emptySnapshot();

        // Remove the played card from hand (placeholder behavior)
        $snapshot['hand'] = array_values(array_filter(
            $snapshot['hand'] ?? [],
            fn ($c) => ($c['id'] ?? null) !== $cardId
        ));

        $snapshot['selectedCardId'] = $snapshot['hand'][0]['id'] ?? null;

        // --- EFFECTS (this is what the battle overlay consumes) ---
        // In the real version, $engineResult becomes the source of truth,
        // and normalizeEffects() translates it into UI effects.
        //
        // For now, we emit a fake battle_resolve so you can see animations.
        $effects = [[
            'type' => 'battle_resolve',

            // Preferred: images (fastest path for overlay)
            'player' => ['img' => $this->cardImgFromId($cardId)],
            'enemy'  => ['img' => $this->cardImgFromId('enemy-placeholder')],

            // win|loss|tie (placeholder)
            'outcome' => 'win',

            // optional, derived if omitted
            'planetMove' => 'down',
        ]];

        // Save state
        $state['snapshot'] = $snapshot;
        $this->saveState($sessionId, $state);

        return [
            'snapshot' => $snapshot,
            'effects'  => $effects,
        ];

        // --- REAL ENGINE VERSION (replace the placeholder block above) ---
        // $engine = \StellarSkirmish\Engine::fromState($state['engine'] ?? []);
        // $engineResult = $engine->playCard($playerId, $cardId);
        //
        // $snapshot = $engine->toRenderSnapshot();
        // $effects = $this->normalizeEffects($engineResult, $snapshot);
        //
        // $state['engine'] = $engine->state();
        // $state['snapshot'] = $snapshot;
        // $this->saveState($sessionId, $state);
        //
        // return [
        //   'snapshot' => $snapshot,
        //   'effects'  => $effects,
        // ];
    }

    /**
     * Map a card ID to an image path.
     * Replace this with your real mapping later (DB, manifest, engine snapshot, etc).
     */
    private function cardImgFromId(string $cardId): string
    {
        // Example convention:
        return "/images/cards/{$cardId}.png";
    }

    /**
     * Translate whatever stellar-skirmish returns into UI effects the overlay understands.
     * We’ll implement this once we see $engineResult’s structure.
     */
    private function normalizeEffects(mixed $engineResult, array $snapshot): array
    {
        // Example outcome extraction (placeholder)
        $outcome = 'tie';

        // Ideally: you pull the two battle cards from either engineResult or snapshot.
        $playerImg = $snapshot['lastBattle']['player']['img'] ?? null;
        $enemyImg  = $snapshot['lastBattle']['enemy']['img'] ?? null;

        if (!$playerImg || !$enemyImg) {
            return [];
        }

        return [[
            'type' => 'battle_resolve',
            'player' => ['img' => $playerImg],
            'enemy'  => ['img' => $enemyImg],
            'outcome' => $outcome,
            'planetMove' => $outcome === 'win' ? 'down' : ($outcome === 'loss' ? 'up' : 'none'),
        ]];
    }

    private function emptySnapshot(): array
    {
        return [
            'hud' => ['score' => 0, 'credits' => 0],
            'planets' => [
                ['id' => 'p1', 'name' => 'Vesper Reach', 'flavor' => 'Cold rings. Hot politics.', 'type' => 'A', 'vp' => 2, 'art' => null],
            ],
            'hand' => [
                ['id' => 'c1', 'img' => '/images/cards/placeholder-1.png'],
                ['id' => 'c2', 'img' => '/images/cards/placeholder-2.png'],
                ['id' => 'c3', 'img' => '/images/cards/placeholder-3.png'],
            ],
            'selectedCardId' => 'c1',
        ];
    }

    private function loadState(string $sessionId): array
    {
        return cache()->get("game:{$sessionId}", []);
    }

    private function saveState(string $sessionId, array $state): void
    {
        cache()->put("game:{$sessionId}", $state, now()->addHours(12));
    }
}
