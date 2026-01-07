<?php

namespace App\Game\Drivers;

use App\Game\Contracts\GameDriver;
use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\Mercenary;
use StellarSkirmish\Planet;

class LocalSkirmishDriver implements GameDriver
{
    public function __construct(
        private readonly GameEngine $engine,
    ) {}

    public function snapshot(string $sessionId): array
    {
        [$state, $meta] = $this->load($sessionId);

        if (! $state) {
            return ['needs_faction' => true];
        }

        return $this->toSnapshot($state, $meta);
    }

    public function startNewGame(string $sessionId, array $options = []): array
    {
        $playerFaction = $options['player_faction'] ?? 'neupert';
        $enemyFaction = $options['enemy_faction'] ?? collect(['neupert', 'wami', 'rogers'])->random();

        $state = $this->engine->startNewGame(GameConfig::standardTwoPlayer());
        $meta = [
            'last_play' => [],
            'factions' => [
                1 => $playerFaction,
                2 => $enemyFaction,
            ],
        ];

        $this->save($sessionId, $state, $meta);

        return $this->toSnapshot($state, $meta);
    }

    public function playCard(string $sessionId, string $playerId, string $cardId): array
    {
        [$state, $meta] = $this->load($sessionId);

        if (! $state) {
            return $this->startNewGame($sessionId);
        }

        $pid = (int) $playerId;
        $cardValue = $this->cardValueFromId($cardId);

        $before = clone $state;

        $factions = $meta['factions'] ?? [1 => 'neupert', 2 => 'neupert'];
        $pidFaction = $factions[$pid] ?? 'neupert';

        $meta['last_play'][$pid] = [
            'cardValue' => $cardValue,
            'img' => $this->cardImgFromValue($cardValue, $pidFaction),
        ];

        $after = $this->engine->playCard($state, playerId: $pid, cardValue: $cardValue);

        // simple single-device opponent
        if (
            $after->playerCount === 2
            && ($after->currentPlays[2] ?? null) === null
            && ($after->hands[2] ?? []) !== []
        ) {
            $enemyCard = $this->pickEnemyCardValue($after);
            $enemyFaction = $factions[2] ?? 'neupert';

            $meta['last_play'][2] = [
                'cardValue' => $enemyCard,
                'img' => $this->cardImgFromValue($enemyCard, $enemyFaction),
            ];
            $after = $this->engine->playCard($after, playerId: 2, cardValue: $enemyCard);
        }

        $effects = $this->effectsFromTransition($before, $after, $meta);

        if (! empty($effects)) {
            $meta['last_play'] = [];
        }

        $this->save($sessionId, $after, $meta);

        return [
            'snapshot' => $this->toSnapshot($after, $meta),
            'effects' => $effects,
        ];
    }

    private function pickEnemyCardValue(GameState $state): int
    {
        $hand = $state->hands[2] ?? [];

        return $hand[array_rand($hand)];
    }

    private function toSnapshot(GameState $state, array $meta = []): array
    {
        $factions = $meta['factions'] ?? [1 => 'neupert', 2 => 'neupert'];
        $playerFaction = $factions[1] ?? 'neupert';
        $enemyFaction = $factions[2] ?? 'neupert';

        // --- planet stage ---
        // Engine does not reveal/add to pot until the first play.
        // UX: show a preview planet if pot is empty.
        $stagePlanets = $state->planetPot;

        if ($stagePlanets === []) {
            $idx = $state->currentPlanetIndex ?? 0;
            $next = $state->planetDeck[$idx] ?? null;
            if ($next instanceof Planet) {
                $stagePlanets = [$next];
            }
        }

        $planets = array_map(function (Planet $p) {
            $name = property_exists($p, 'name') ? ($p->name ?? 'Unknown Planet') : 'Unknown Planet';
            $flavor = property_exists($p, 'flavor') ? ($p->flavor ?? '') : '';

            $type = '';
            if (property_exists($p, 'planetClass') && $p->planetClass !== null) {
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
        }, $stagePlanets);

        $hud = [
            'score' => (int) (($state->scores()[1] ?? 0)),
            'credits' => 0,
            'pot_vp' => (int) array_sum(array_map(fn (Planet $p) => $p->victoryPoints, $stagePlanets)),
        ];

        // --- hand ---
        $handValues = $state->hands[1] ?? [];
        $mercMap = $this->mercsByStrengthForPlayer($state, 1);

        $hand = array_map(function (int $v) use ($mercMap, $playerFaction) {
            $merc = $mercMap[$v] ?? null;

            return [
                'id' => (string) $v,
                'img' => $this->cardImgFromValue($v, $playerFaction),

                'isMerc' => $merc !== null,
                'merc' => $merc ? [
                    'id' => $merc->id,
                    'name' => $merc->name,
                    'ability_type' => $merc->abilityType->value,
                    'params' => $merc->params,
                    'base_strength' => $merc->baseStrength,
                ] : null,
            ];
        }, $handValues);

        // keep selection if still in hand; otherwise pick first card
        $selected = null;
        foreach ($hand as $c) {
            if (($c['id'] ?? null) === ($this->loadSelectedCardId($state) ?? null)) {
                $selected = $c['id'];
                break;
            }
        }
        $selected ??= ($hand[0]['id'] ?? null);

        return [
            'hud' => $hud,
            'planets' => $planets,
            'hand' => $hand,
            'selectedCardId' => $selected,
            'gameOver' => $state->gameOver,
            'endReason' => $state->endReason?->value,
            'factions' => [
                'player' => $playerFaction,
                'enemy' => $enemyFaction,
            ],
        ];
    }

    // If you later persist selected card into meta/state, wire it here.
    private function loadSelectedCardId(GameState $state): ?string
    {
        return null;
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
        $winnerId = $this->detectWinnerId($before, $after);

        $potBefore = count($before->planetPot ?? []);
        $potAfter = count($after->planetPot ?? []);
        $tieEscalated = ($winnerId === null) && ($potAfter > $potBefore);

        if ($winnerId === null && ! $tieEscalated) {
            return [];
        }

        $outcome = 'tie';
        if ($winnerId === 1) {
            $outcome = 'win';
        }
        if ($winnerId !== null && $winnerId !== 1) {
            $outcome = 'loss';
        }

        $factions = $meta['factions'] ?? [1 => 'neupert', 2 => 'neupert'];

        $lp = $meta['last_play'] ?? [];

        $p1Val = $lp[1]['cardValue'] ?? null;
        $p2Val = $lp[2]['cardValue'] ?? null;

        $p1Img = $lp[1]['img'] ?? (is_int($p1Val) ? $this->cardImgFromValue($p1Val, $factions[1] ?? 'neupert') : null);
        $p2Img = $lp[2]['img'] ?? (is_int($p2Val) ? $this->cardImgFromValue($p2Val, $factions[2] ?? 'neupert') : null);

        if (! $p1Img || ! $p2Img) {
            return [];
        }

        return [[
            'type' => 'battle_resolve',
            'player' => ['img' => $p1Img, 'strength' => $p1Val],
            'enemy' => ['img' => $p2Img, 'strength' => $p2Val],
            'outcome' => $outcome,
            'planetMove' => $outcome === 'win' ? 'down' : ($outcome === 'loss' ? 'up' : 'none'),
            'tieEscalated' => $tieEscalated,
        ]];
    }

    private function detectWinnerId(GameState $before, GameState $after): ?int
    {
        for ($pid = 1; $pid <= $after->playerCount; $pid++) {
            $beforeCount = count($before->claimedPlanets[$pid] ?? []);
            $afterCount = count($after->claimedPlanets[$pid] ?? []);
            if ($afterCount > $beforeCount) {
                return $pid;
            }
        }

        return null;
    }

    private function cardValueFromId(string $cardId): int
    {
        if (ctype_digit($cardId)) {
            return (int) $cardId;
        }
        throw new \RuntimeException("Card ID '{$cardId}' is not numeric. Implement cardValueFromId() mapping.");
    }

    private function cardImgFromValue(int $value, string $faction): string
    {
        return "/images/cards/{$faction}/{$value}.png";
    }

    private function load(string $sessionId): array
    {
        $raw = cache()->get("game:{$sessionId}");
        if (! is_array($raw)) {
            return [null, null];
        }

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
