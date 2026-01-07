<?php

namespace App\Game;

use App\Game\Contracts\GameDriver;

class GameService
{
    public function __construct(
        private readonly GameDriver $driver,
    ) {}

    public function snapshot(string $sessionId): array
    {
        return $this->driver->snapshot($sessionId);
    }

    public function startNewGame(string $sessionId, array $options = []): array
    {
        return $this->driver->startNewGame($sessionId, $options);
    }

    public function playCard(string $sessionId, string $playerId, string $cardId): array
    {
        return $this->driver->playCard($sessionId, $playerId, $cardId);
    }
}
