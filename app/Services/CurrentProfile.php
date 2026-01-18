<?php

namespace App\Services;

use App\Models\User;

class CurrentProfile
{
    private const SESSION_KEY = 'current_profile_id';

    private const STORE_KEY = 'last_profile_id';

    public function __construct(
        protected LocalStoreService $store,
    ) {}

    /**
     * Returns the currently selected profile, auto-selecting
     * the last used profile if none is in session.
     */
    public function get(): ?User
    {
        // 1) Session wins for this request lifecycle
        $sessionId = session(self::SESSION_KEY);
        if ($sessionId) {
            return User::find($sessionId);
        }

        // 2) Try to auto-load last used profile from device storage
        $lastId = $this->store->get(self::STORE_KEY);
        if (! $lastId) {
            return null;
        }

        $profile = User::find($lastId);
        if (! $profile) {
            // Clean up stale pointer
            $this->store->delete(self::STORE_KEY);

            return null;
        }

        // Re-hydrate session so middleware & app behave consistently
        session([self::SESSION_KEY => $profile->id]);

        return $profile;
    }

    public function set(User $profile): void
    {
        session([self::SESSION_KEY => $profile->id]);
        $this->store->set(self::STORE_KEY, $profile->id);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->store->delete(self::STORE_KEY);
    }
}
