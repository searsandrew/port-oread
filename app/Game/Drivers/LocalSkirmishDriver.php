<?php

namespace App\Game\Drivers;

use App\Game\Contracts\GameDriver;
use App\Models\Planet as PlanetModel;
use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\Mercenary;
use StellarSkirmish\Planet;
use StellarSkirmish\PlanetClass;

class LocalSkirmishDriver implements GameDriver
{
    public function __construct(
        private readonly GameEngine $engine,
    ) {
    }

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
        $enemyFaction  = $options['enemy_faction'] ?? collect(['neupert', 'wami', 'rogers'])->random();

        $config = $this->buildGameConfig();
        $state  = $this->engine->startNewGame($config);

        $meta = [
            'last_play' => [],
            'factions'  => [
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

        $pid       = (int) $playerId;
        $cardValue = $this->cardValueFromId($cardId);

        $before = clone $state;

        $factions   = $meta['factions'] ?? [1 => 'neupert', 2 => 'neupert'];
        $pidFaction = $factions[$pid] ?? 'neupert';

        $meta['last_play'][$pid] = [
            'cardValue' => $cardValue,
            'img'       => $this->cardImgFromValue($cardValue, $pidFaction),
        ];

        $after = $this->engine->playCard($state, playerId: $pid, cardValue: $cardValue);

        // Simple single-device opponent
        if (
            $after->playerCount === 2
            && ($after->currentPlays[2] ?? null) === null
            && ($after->hands[2] ?? []) !== []
        ) {
            $enemyCard    = $cardValue; // $this->pickEnemyCardValue($after);
            $enemyFaction = $factions[2] ?? 'neupert';

            $meta['last_play'][2] = [
                'cardValue' => $enemyCard,
                'img'       => $this->cardImgFromValue($enemyCard, $enemyFaction),
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
            'effects'  => $effects,
        ];
    }

    private function pickEnemyCardValue(GameState $state): int
    {
        $hand = $state->hands[2] ?? [];

        return $hand[array_rand($hand)];
    }

    private function toSnapshot(GameState $state, array $meta = []): array
    {
        $factions      = $meta['factions'] ?? [1 => 'neupert', 2 => 'neupert'];
        $playerFaction = $factions[1] ?? 'neupert';
        $enemyFaction  = $factions[2] ?? 'neupert';

        // --- planet stage ---
        $stagePlanets = $state->planetPot ?? [];

        // UX: if pot is empty, show preview planet from deck
        if ($stagePlanets === []) {
            $idx  = $state->currentPlanetIndex ?? 0;
            $next = $state->planetDeck[$idx] ?? null;
            if ($next instanceof Planet) {
                $stagePlanets = [$next];
            }
        }
        $planetMetaById = cache()->remember('planets:metaById', now()->addHours(6), function () {
            return PlanetModel::query()
                ->get(['id', 'name', 'flavor', 'type', 'class', 'victory_point_value', 'filename'])
                ->keyBy('id')
                ->map(fn (PlanetModel $m) => [
                    'name'     => $m->name,
                    'flavor'   => $m->flavor,
                    'type'     => $m->type,
                    'class'    => $m->class,
                    'vp'       => (int) $m->victory_point_value,
                    'filename' => $m->filename,
                ])
                ->all();
        });

        $planets = array_map(function (Planet $p) use ($planetMetaById) {
            $meta = $planetMetaById[$p->id] ?? null;

            $name        = $meta['name'] ?? ($p->name ?? 'Unknown Planet');
            $flavor      = $meta['flavor'] ?? ($p->description ?? '');
            $planetClass = $meta['class'] ?? ($p->planetClass?->value ?? null);
            $planetType  = $meta['type'] ?? null;
            $vp          = $meta['vp'] ?? (int) $p->victoryPoints;

            // DB filename is stored in imageLink for convenience.
            $filename = $meta['filename'] ?? ($p->imageLink ?? null);

            return [
                'id'       => $p->id,
                'name'     => $name,
                'flavor'   => $flavor,
                'type'     => $planetType,
                'class'    => $planetClass,
                'vp'       => $vp,
                'filename' => $filename,
                'img'      => $filename ? "/images/planets/{$filename}" : null,
                'card_img' => $filename ? "/images/cards/planets/{$filename}" : null,
            ];
        }, $stagePlanets);

        $hud = [
            'score'   => (int) (($state->scores()[1] ?? 0)),
            'credits' => 0,
            'pot_vp'  => (int) array_sum(array_map(fn (Planet $p) => $p->victoryPoints, $stagePlanets)),
        ];

        // --- hand ---
        $handValues = $state->hands[1] ?? [];
        $mercMap    = $this->mercsByStrengthForPlayer($state, 1);

        $hand = array_map(function (int $v) use ($mercMap, $playerFaction) {
            $merc = $mercMap[$v] ?? null;

            return [
                'id'  => (string) $v,
                'img' => $this->cardImgFromValue($v, $playerFaction),

                'isMerc' => $merc !== null,
                'merc'   => $merc ? [
                    'id'            => $merc->id,
                    'name'          => $merc->name,
                    'ability_type'  => $merc->abilityType->value,
                    'params'        => $merc->params,
                    'base_strength' => $merc->baseStrength,
                ] : null,
            ];
        }, $handValues);

        $selected = $hand[0]['id'] ?? null;

        return [
            'hud'            => $hud,
            'planets'        => $planets,
            'hand'           => $hand,
            'selectedCardId' => $selected,
            'gameOver'       => $state->gameOver,
            'endReason'      => $state->endReason?->value,
            'savedAt'        => isset($meta['saved_at']) ? (int) $meta['saved_at'] : null,
            'factions'       => [
                'player' => $playerFaction,
                'enemy'  => $enemyFaction,
            ],
        ];
    }

    private function buildGameConfig(): GameConfig
    {
        $dbPlanets = PlanetModel::query()
            ->where('is_standard', true)
            ->orderBy('name')
            ->get();

        if ($dbPlanets->isEmpty()) {
            $dbPlanets = PlanetModel::query()->orderBy('name')->get();
        }

        /** @var Planet[] $enginePlanets */
        $enginePlanets = $dbPlanets->map(function (PlanetModel $m) {
            return new Planet(
                id: (string) $m->id,
                victoryPoints: (int) $m->victory_point_value,
                name: $m->name,
                description: $m->flavor,
                planetClass: $m->class ? PlanetClass::tryFrom($m->class) : null,
                imageLink: $m->filename, // stash filename for snapshot URLs
                abilities: [],
            );
        })->all();

        $enginePlanets = $this->secureShuffle($enginePlanets);

        return new GameConfig(
            playerCount: 2,
            planets: $enginePlanets,
            fleetValues: range(1, 15),
            seed: null,
        );
    }

    /**
     * Truly random shuffle (uses random_int).
     *
     * @template T
     * @param array<int, T> $items
     * @return array<int, T>
     */
    private function secureShuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
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

        $potBefore    = count($before->planetPot ?? []);
        $potAfter     = count($after->planetPot ?? []);
        $tieEscalated = ($winnerId === null) && ($potAfter > $potBefore);

        if ($winnerId === null && ! $tieEscalated) {
            return [];
        }

        $outcome = 'tie';
        if ($winnerId === 1) $outcome = 'win';
        if ($winnerId !== null && $winnerId !== 1) $outcome = 'loss';

        $lp = $meta['last_play'] ?? [];

        $p1Val = $lp[1]['cardValue'] ?? null;
        $p2Val = $lp[2]['cardValue'] ?? null;

        $p1Img = $lp[1]['img'] ?? null;
        $p2Img = $lp[2]['img'] ?? null;

        if (! $p1Img || ! $p2Img) {
            return [];
        }

        return [[
            'type'         => 'battle_resolve',
            'player'       => ['img' => $p1Img, 'strength' => $p1Val],
            'enemy'        => ['img' => $p2Img, 'strength' => $p2Val],
            'outcome'      => $outcome,
            'planetMove'   => $outcome === 'win' ? 'down' : ($outcome === 'loss' ? 'up' : 'none'),
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

    private function cardValueFromId(string $cardId): int
    {
        if (ctype_digit($cardId)) {
            return (int) $cardId;
        }

        throw new \RuntimeException("Card ID '{$cardId}' is not numeric.");
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
        $meta     = $raw['meta'] ?? ['last_play' => []];

        $state = is_array($stateArr) ? GameState::fromArray($stateArr) : null;

        return [$state, $meta];
    }

    private function save(string $sessionId, GameState $state, array $meta): void
    {
        $meta['saved_at'] = now()->timestamp;

        cache()->put("game:{$sessionId}", [
            'state' => $state->toArray(),
            'meta'  => $meta,
        ], now()->addDays(30));
    }
}
