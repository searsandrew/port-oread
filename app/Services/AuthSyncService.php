<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuthSyncService
{
    public function __construct(
        protected TiberApiService $api,
        protected LocalStoreService $local
    ) {}

    public function login(string $email, string $password): bool
    {
        try {
            $data = $this->api->login($email, $password);

            if (!isset($data['token'])) {
                return false;
            }

            $this->local->set('auth_token', $data['token']);

            // If the API doesn't return a user payload, fetch it.
            $user = $data['user'] ?? null;
            if (!$user) {
                try {
                    $user = $this->api->getUserDetails($data['token']);
                } catch (\Throwable $e) {
                    $user = ['email' => $email];
                }
            }

            $this->local->set('user_data', $user);

            // Cache catalog for offline
            $this->syncPlanets(force: true);

            // Cache owned planets too (optional; safe even if empty)
            $this->syncOwnedPlanets();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Online login failed: '.$e->getMessage());

            // Offline auth = "do we already have a valid local session?"
            return $this->offlineSessionLogin($email);
        }
    }

    public function register(array $data): bool
    {
        $response = $this->api->register($data);

        if (!isset($response['token'])) {
            return false;
        }

        $this->local->set('auth_token', $response['token']);

        $user = $response['user'] ?? null;
        if (!$user) {
            try {
                $user = $this->api->getUserDetails($response['token']);
            } catch (\Throwable $e) {
                $user = ['email' => $data['email'] ?? null];
            }
        }

        $this->local->set('user_data', $user);

        $this->syncPlanets(force: true);
        $this->syncOwnedPlanets();

        return true;
    }

    /**
     * Offline mode should NOT be a second password system.
     * If the user previously authenticated and we still have token + user_data,
     * let them in (offline browsing / local play).
     */
    protected function offlineSessionLogin(string $email): bool
    {
        $token = $this->local->get('auth_token');
        $userData = $this->local->get('user_data');

        if (!$token || !$userData) {
            return false;
        }

        return ($userData['email'] ?? null) === $email;
    }

    /**
     * Back-compat: syncPlanets() now means "sync catalog planets"
     */
    public function syncPlanets(bool $force = false): bool
    {
        return $this->syncCatalogPlanets($force);
    }

    public function syncCatalogPlanets(bool $force = false): bool
    {
        $existing = $this->local->get('catalog_planets', null);

        if (!$force && is_array($existing) && count($existing) > 0) {
            return true;
        }

        $token = $this->local->get('auth_token');
        if (!$token) {
            return false;
        }

        try {
            $planets = $this->api->getCatalogPlanets($token);

            // Store under the new key…
            $this->local->set('catalog_planets', $planets);

            // …and also under the old key so nothing else breaks today.
            $this->local->set('planets', $planets);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Catalog planet sync failed: '.$e->getMessage());
            return false;
        }
    }

    public function syncOwnedPlanets(): bool
    {
        $token = $this->local->get('auth_token');
        if (!$token) {
            return false;
        }

        try {
            $owned = $this->api->getPlanets($token);
            $this->local->set('owned_planets', $owned);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Owned planet sync failed: '.$e->getMessage());
            return false;
        }
    }

    public function isAuthenticated(): bool
    {
        // Don’t treat user_data alone as auth
        return $this->local->get('auth_token') !== null;
    }

    public function getUser(): ?array
    {
        return $this->local->get('user_data');
    }

    /** Catalog planets */
    public function getPlanets(): array
    {
        // Prefer new key; fallback to old key
        return $this->local->get('catalog_planets', $this->local->get('planets', []));
    }

    /** Owned planets */
    public function getOwnedPlanets(): array
    {
        return $this->local->get('owned_planets', []);
    }

    public function logout(): void
    {
        $token = $this->local->get('auth_token');

        if ($token) {
            try {
                $this->api->logout($token);
            } catch (\Throwable $e) {
                // ignore (offline / server down)
            }
        }

        $this->local->clear();
    }
}
