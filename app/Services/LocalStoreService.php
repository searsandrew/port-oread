<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Native\Mobile\Facades\SecureStorage;

class LocalStoreService
{
    public function set(string $key, mixed $value): bool
    {
        try {
            $stringValue = is_string($value) ? $value : json_encode($value);

            // Always sync to cache as fallback for non-native environments
            cache()->put("local_store_{$key}", $value, now()->addDays(30));

            return SecureStorage::set($key, $stringValue);
        } catch (\Exception $e) {
            Log::warning("SecureStorage failed for key {$key}, using cache fallback: ".$e->getMessage());

            return true; // We synced to cache, so consider it "success" for the flow
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = SecureStorage::get($key);

            if ($value === null) {
                return cache()->get("local_store_{$key}", $default);
            }

            $decoded = json_decode($value, true);

            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        } catch (\Exception $e) {
            return cache()->get("local_store_{$key}", $default);
        }
    }

    public function delete(string $key): bool
    {
        cache()->forget("local_store_{$key}");

        try {
            return SecureStorage::delete($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clear(): void
    {
        $this->delete('auth_token');
        $this->delete('user_data');
        $this->delete('owned_planets');
    }
}
