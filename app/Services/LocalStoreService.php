<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Native\Mobile\Facades\SecureStorage;

class LocalStoreService
{
    private function cacheKey(string $key): string
    {
        return "local_store_{$key}";
    }

    private function profileKey(string $profileId, string $key): string
    {
        return "profile:{$profileId}:{$key}";
    }

    public function set(string $key, mixed $value): bool
    {
        try {
            $stringValue = is_string($value) ? $value : json_encode($value);

            // Cache fallback (also useful for non-native / web dev)
            cache()->put($this->cacheKey($key), $value, now()->addDays(30));

            return SecureStorage::set($key, $stringValue);
        } catch (\Throwable $e) {
            Log::warning("SecureStorage failed for key {$key}, using cache fallback: ".$e->getMessage());

            return true;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = SecureStorage::get($key);

            if ($value === null) {
                return cache()->get($this->cacheKey($key), $default);
            }

            $decoded = json_decode($value, true);

            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        } catch (\Throwable $e) {
            return cache()->get($this->cacheKey($key), $default);
        }
    }

    public function delete(string $key): bool
    {
        cache()->forget($this->cacheKey($key));

        try {
            return SecureStorage::delete($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ----------------------------
    // Profile-scoped helpers
    // ----------------------------

    public function setForProfile(string $profileId, string $key, mixed $value): bool
    {
        return $this->set($this->profileKey($profileId, $key), $value);
    }

    public function getForProfile(string $profileId, string $key, mixed $default = null): mixed
    {
        return $this->get($this->profileKey($profileId, $key), $default);
    }

    public function deleteForProfile(string $profileId, string $key): bool
    {
        return $this->delete($this->profileKey($profileId, $key));
    }

    public function migrateLegacyAuthToProfile(string $profileId): void
    {
        $profileToken = $this->getForProfile($profileId, 'auth_token');
        $legacyToken = $this->get('auth_token');

        if (! $profileToken && $legacyToken) {
            $this->setForProfile($profileId, 'auth_token', $legacyToken);
            $this->delete('auth_token');
        }

        $profileUser = $this->getForProfile($profileId, 'user_data');
        $legacyUser = $this->get('user_data');

        if (! $profileUser && $legacyUser) {
            $this->setForProfile($profileId, 'user_data', $legacyUser);
            $this->delete('user_data');
        }
    }

    public function disconnectProfile(string $profileId): void
    {
        $this->deleteForProfile($profileId, 'auth_token');
        $this->deleteForProfile($profileId, 'user_data');
        $this->deleteForProfile($profileId, 'owned_planet_ids');
        $this->deleteForProfile($profileId, 'owned_planets_last_synced_at');
    }

    public function clear(): void
    {
        // legacy clear (safe)
        $this->delete('auth_token');
        $this->delete('user_data');
        $this->delete('preferences');
        $this->delete('planets_last_updated_at');
        $this->delete('planets_last_synced_at');
    }
}
