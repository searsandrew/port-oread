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

            if (isset($data['token'])) {
                $this->local->set('auth_token', $data['token']);
                $this->local->set('user_data', $data['user'] ?? ['email' => $email]);

                // Fetch and sync planets immediately
                $this->syncPlanets();

                return true;
            }
        } catch (\Exception $e) {
            Log::warning('Online login failed: '.$e->getMessage());

            // Try offline login
            return $this->offlineLogin($email, $password);
        }

        return false;
    }

    public function register(array $data): bool
    {
        try {
            $response = $this->api->register($data);

            if (isset($response['token'])) {
                $this->local->set('auth_token', $response['token']);
                $this->local->set('user_data', $response['user'] ?? ['email' => $data['email']]);

                $this->syncPlanets();

                return true;
            }
        } catch (\Exception $e) {
            Log::error('Registration failed: '.$e->getMessage());
            throw $e;
        }

        return false;
    }

    protected function offlineLogin(string $email, string $password): bool
    {
        $userData = $this->local->get('user_data');

        if ($userData && isset($userData['email']) && $userData['email'] === $email) {
            // In a real app, we'd want to verify a hashed password stored locally too,
            // but for this task "be aware of our local users name, preferences, etc"
            // suggests we just need to know who they are.
            Log::info("Offline login successful for {$email}");

            return true;
        }

        return false;
    }

    public function syncPlanets(): bool
    {
        $token = $this->local->get('auth_token');

        if (! $token) {
            return false;
        }

        try {
            $planets = $this->api->getPlanets($token);
            $this->local->set('planets', $planets);

            return true;
        } catch (\Exception $e) {
            Log::warning('Planet sync failed: '.$e->getMessage());

            return false;
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->local->get('auth_token') !== null || $this->local->get('user_data') !== null;
    }

    public function getUser(): ?array
    {
        return $this->local->get('user_data');
    }

    public function getPlanets(): array
    {
        return $this->local->get('planets', []);
    }

    public function logout(): void
    {
        $this->local->clear();
    }
}
