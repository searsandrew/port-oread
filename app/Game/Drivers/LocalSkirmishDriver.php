<?php

namespace App\Game\Drivers;

use App\Game\Contracts\GameDriver;
use StellarSkirmish\{GameEngine, GameConfig, GameState, Planet, Mercenary};

class LocalSkirmishDriver implements GameDriver
{
    public function __construct(
        private readonly GameEngine $engine,
    ) {}

    public function snapshot(string $sessionId): array
    {
        [$state, $meta] = $this->load($sessionId);

        if (!$state) {
            $state = $this->engine->startNewGame(GameConfig::standardTwoPlayer());
            $meta = [
                'last_play' => [], // [playerId => ['cardValue' => int, 'img' => string]]
            ];
            $this->save($sessionId, $state, $meta);
        }

        return $this->toSnapshot($state);
    }

    public function playCard(string $sessionId, string $playerId, string $cardId): array
    {
        [$state, $meta] = $this->load($sessionId);

        if (!$state) {
            $state = $this->engine->startNewGame(GameConfig::standardTwoPlayer());
            $meta = ['last_play' => []];
        }

        $pid = (int) $playerId;
        $cardValue = $this->cardValueFromId($cardId);

        $before = $state;

        // record for UI overlay
        $meta['last_play'][$pid] = [
            'cardValue' => $cardValue,
            'img' => $this->cardImgFromValue($cardValue),
        ];

        $after = $this->engine->playCard($state, playerId: $pid, cardValue: $cardValue);

        // --- simple single-device local opponent ---
        // If 2-player game and P2 hasn't played yet for this battle, auto play P2.
        if (
            $after->playerCount === 2
            && ($after->currentPlays[2] ?? null) === null
            && ($after->hands[2] ?? []) !== []
        ) {
            $enemyCard = $this->pickEnemyCardValue($after);
            $meta['last_play'][2] = [
                'cardValue' => $enemyCard,
                'img' => $this->cardImgFromValue($enemyCard),
            ];
            $after = $this->engine->playCard($after, playerId: 2, cardValue: $enemyCard);
        }

        $effects = $this->effectsFromTransition($before, $after, $meta);

        if (!empty($effects)) {
            // clear for next battle animation
            $meta['last_play'] = [];
        }

        $this->save($sessionId, $after, $meta);

        return [
            'snapshot' => $this->toSnapshot($after),
            'effects' => $effects,
        ];
    }

    private function pickEnemyCardValue(GameState $state): int
    {
        $hand = $state->hands[2] ?? [];
        // simple strategy: random
        return $hand[array_rand($hand)];
    }

    private function toSnapshot(GameState $state): array
    {
        $scores = $state->scores();

        $hud = [
            'score' => (int) ($scores[1] ?? 0),
            'credits' => 0,
            'pot_vp' => (int) array_sum(array_map(fn (Planet $p) => $p->victoryPoints, $state->planetPot)),
        ];

        // planet stage: show pot as swipe-able list (ties add more)
        $planets = array_map(function (Planet $p) {
            $name = property_exists($p, 'name') ? ($p->name ?? 'Unknown Planet') : 'Unknown Planet';
            $flavor = property_exists($p, 'flavor') ? ($p->flavor ?? '') : '';
            $type = '';

            if (property_exists($p, 'planetClass') && $p->planetClass !== null) {
                // planetClass is likely an enum
                $type = is_object($p->planetClass) && property_exists($p->planetClass, 'value')
                    ? (string) $p->planetClass->value
                    : (string) $p->planetClass;
            }

            return [
                'id' => $p->id,
                'name' => $name,
                'flavor' => $flavor,
                'type' => $type,
                'vp' => (int) $p->victoryPoints,
                'art' => null,
            ];
        }, $state->planetPot);

        // hand: player 1 ship values
        $handValues = $state->hands[1] ?? [];

        // mercs: map baseStrength -> merc for player 1
        $mercMap = $this->mercsByStrengthForPlayer($state, 1);

        $hand = array_map(function (int $v) use ($mercMap) {
            $merc = $mercMap[$v] ?? null;

            return [
                'id' => (string) $v,
                'img' => $this->cardImgFromValue($v),

                // UI hint
                'isMerc' => $merc !== null,

                // optional: used by Info modal
                'merc' => $merc ? [
                    'id' => $merc->id,
                    'name' => $merc->name,
                    'ability_type' => $merc->abilityType->value,
                    'params' => $merc->params,
                    'base_strength' => $merc->baseStrength,
                ] : null,
            ];
        }, $handValues);

        return [
            'hud' => $hud,
            'planets' => $planets,
            'hand' => $hand,
            'selectedCardId' => $hand[0]['id'] ?? null,
            'gameOver' => $state->gameOver,
            'endReason' => $state->endReason?->value,
        ];
    }

    private function mercsByStrengthForPlayer(GameState $state, int $playerId): array
    {
        $map = [];
        foreach (($state->mercenaries[$playerId] ?? []) as $merc) {
            /** @var Mercenary $merc */
            $map[$merc->baseStrength] = $merc;
        }
        return $map;
    }

    private function effectsFromTransition(GameState $before, GameState $after, array $meta): array
    {
        // winner if claimed planets increased
        $winnerId = $this->detectWinnerId($before, $after);

        // tie escalation if pot grew and no winner
        $potBefore = count($before->planetPot ?? []);
        $potAfter  = count($after->planetPot ?? []);
        $tieEscalated = ($winnerId === null) && ($potAfter > $potBefore);

        // also allow for resolveBattle clearing plays, if it does that
        $playsReset = ($this->allPlayersHavePlayed($before) && !$this->allPlayersHavePlayed($after));

        if ($winnerId === null && !$tieEscalated && !$playsReset) {
            return [];
        }

        $outcome = 'tie';
        if ($winnerId === 1) $outcome = 'win';
        if ($winnerId !== null && $winnerId !== 1) $outcome = 'loss';

        $lp = $meta['last_play'] ?? [];

        $p1Val = $lp[1]['cardValue'] ?? ($before->currentPlays[1] ?? null);
        $p2Val = $lp[2]['cardValue'] ?? ($before->currentPlays[2] ?? null);

        $p1Img = $lp[1]['img'] ?? (is_int($p1Val) ? $this->cardImgFromValue($p1Val) : null);
        $p2Img = $lp[2]['img'] ?? (is_int($p2Val) ? $this->cardImgFromValue($p2Val) : null);

        if (!$p1Img || !$p2Img) {
            return [];
        }

        return [[
            'type' => 'battle_resolve',
            'player' => ['img' => $p1Img],
            'enemy'  => ['img' => $p2Img],
            'outcome' => $outcome,
            'planetMove' => $outcome === 'win' ? 'down' : ($outcome === 'loss' ? 'up' : 'none'),
            'tieEscalated' => $tieEscalated,
        ]];
    }

    private function detectWinnerId(GameState $before, GameState $after): ?int
    {
        for ($pid = 1; $pid <= $after->playerCount; $pid++) {
            $beforeCount = count($before->claimedPlanets[$pid] ?? []);
            $afterCount  = count($after->claimedPlanets[$pid] ?? []);
            if ($afterCount > $beforeCount) {
                return $pid;
            }
        }
        return null;
    }

    private function allPlayersHavePlayed(GameState $state): bool
    {
        for ($pid = 1; $pid <= $state->playerCount; $pid++) {
            if (($state->currentPlays[$pid] ?? null) === null) return false;
        }
        return true;
    }

    private function cardValueFromId(string $cardId): int
    {
        if (ctype_digit($cardId)) return (int) $cardId;
        throw new \RuntimeException("Card ID '{$cardId}' is not numeric. Implement cardValueFromId() mapping.");
    }

    private function cardImgFromValue(int $value): string
    {
        return "/images/cards/{$value}.png";
    }

    private function load(string $sessionId): array
    {
        $raw = cache()->get("game:{$sessionId}");
        if (!is_array($raw)) return [null, null];

        $stateArr = $raw['state'] ?? null;
        $meta = $raw['meta'] ?? ['last_play' => []];

        $state = is_array($stateArr) ? GameState::fromArray($stateArr) : null;

        return [$state, $meta];
    }

    private function save(string $sessionId, GameState $state, array $meta): void
    {
        cache()->put("game:{$sessionId}", [
            'state' => $state->toArray(),
            'meta' => $meta,
        ], now()->addHours(12));
    }
}
