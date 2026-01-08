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

            return SecureStorage::set($key, $stringValue);
        } catch (\Exception $e) {
            Log::error("Failed to set local storage key {$key}: ".$e->getMessage());

            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = SecureStorage::get($key);

            if ($value === null) {
                return $default;
            }

            $decoded = json_decode($value, true);

            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        } catch (\Exception $e) {
            Log::error("Failed to get local storage key {$key}: ".$e->getMessage());

            return $default;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return SecureStorage::delete($key);
        } catch (\Exception $e) {
            Log::error("Failed to delete local storage key {$key}: ".$e->getMessage());

            return false;
        }
    }

    public function clear(): void
    {
        // Native SecureStorage doesn't have a clear all, so we'd have to know all keys.
        $this->delete('auth_token');
        $this->delete('user_data');
        $this->delete('planets');
        $this->delete('preferences');
    }
}
