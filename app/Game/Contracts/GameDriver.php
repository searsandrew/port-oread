<?php

namespace App\Game\Contracts;

interface GameDriver
{
    public function snapshot(string $sessionId): array;

    public function startNewGame(string $sessionId, array $options = []): array;

    /**
     * Executes a player action and returns:
     * - 'snapshot' => updated snapshot
     * - 'effects'  => UI effects/animations to run (battle resolve, discard, planet slide, tie planet push, etc)
     */
    public function playCard(string $sessionId, string $playerId, string $cardId): array;
}
