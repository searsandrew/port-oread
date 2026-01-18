<?php

namespace App\Services;

use App\Models\Planet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AuthSyncService
{
    public function __construct(
        protected TiberApiService $api,
        protected LocalStoreService $local
    ) {}

    // ----------------------------
    // Connect / Auth token storage
    // ----------------------------

    public function connectRegister(User $profile, array $payload): bool
    {
        $result = $this->api->register($payload);

        if (! isset($result['token'])) {
            return false;
        }

        $this->local->setForProfile($profile->id, 'auth_token', $result['token']);
        $this->local->setForProfile($profile->id, 'user_data', $result['user'] ?? null);

        if (isset($result['user']['id'])) {
            $profile->forceFill([
                'tiber_user_id' => $result['user']['id'],
                'connected_at' => now(),
            ])->save();
        }

        return true;
    }

    public function connectLogin(User $profile, string $email, string $password): bool
    {
        $result = $this->api->login($email, $password);

        if (! isset($result['token'])) {
            return false;
        }

        $this->local->setForProfile($profile->id, 'auth_token', $result['token']);
        $this->local->setForProfile($profile->id, 'user_data', $result['user'] ?? null);

        if (isset($result['user']['id'])) {
            $profile->forceFill([
                'tiber_user_id' => $result['user']['id'],
                'connected_at' => now(),
            ])->save();
        }

        return true;
    }

    public function tokenFor(User $profile): ?string
    {
        $this->local->migrateLegacyAuthToProfile($profile->id);

        return $this->local->getForProfile($profile->id, 'auth_token');
    }

    public function userDataFor(User $profile): mixed
    {
        $this->local->migrateLegacyAuthToProfile($profile->id);

        return $this->local->getForProfile($profile->id, 'user_data');
    }

    public function disconnect(User $profile): void
    {
        $this->local->disconnectProfile($profile->id);

        $profile->forceFill([
            'tiber_user_id' => null,
            'connected_at' => null,
        ])->save();
    }

    // ----------------------------
    // Catalog planets (offline-first)
    // ----------------------------

    /**
     * Fetch public catalog planets and upsert into local SQLite planets table.
     */
    public function syncPlanets(bool $force = false): bool
    {
        try {
            // If we already have local planets and not forcing, we can skip a fetch
            // unless you want to compare meta.last_updated_at later.
            if (! $force && Planet::query()->exists()) {
                return false;
            }

            $payload = $this->api->getCatalogPlanets();
            $rows = $payload['data'] ?? [];

            if (! is_array($rows) || $rows === []) {
                return false;
            }

            $now = now();
            $upserts = [];

            foreach ($rows as $p) {
                if (! is_array($p) || empty($p['id'])) {
                    continue;
                }

                $upserts[] = [
                    'id' => (string) $p['id'],
                    'name' => (string) ($p['name'] ?? 'Unknown'),
                    'flavor' => (string) ($p['flavor'] ?? ''),
                    'type' => $p['type'] ?? null,
                    'class' => (string) ($p['class'] ?? 'standard'),
                    'victory_point_value' => (int) ($p['victory_point_value'] ?? 0),
                    'filename' => (string) ($p['filename'] ?? $p['id']),
                    'is_standard' => (bool) ($p['is_standard'] ?? false),
                    'is_purchasable' => (bool) ($p['is_purchasable'] ?? false),
                    'is_custom' => (bool) ($p['is_custom'] ?? false),
                    'is_promotional' => (bool) ($p['is_promotional'] ?? false),
                    'created_at' => isset($p['created_at']) ? Carbon::parse($p['created_at']) : $now,
                    'updated_at' => isset($p['updated_at']) ? Carbon::parse($p['updated_at']) : $now,
                ];
            }

            if ($upserts === []) {
                return false;
            }

            Planet::upsert(
                $upserts,
                ['id'],
                [
                    'name',
                    'flavor',
                    'type',
                    'class',
                    'victory_point_value',
                    'filename',
                    'is_standard',
                    'is_purchasable',
                    'is_custom',
                    'is_promotional',
                    'updated_at',
                ]
            );

            if (! empty($payload['meta']['last_updated_at'])) {
                $this->local->set('planets_last_updated_at', $payload['meta']['last_updated_at']);
            }

            $this->local->set('planets_last_synced_at', now()->toISOString());

            return true;
        } catch (\Throwable $e) {
            Log::warning('Catalog planet sync failed: '.$e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    /**
     * Offline-first: returns catalog planets from local DB.
     */
    public function getPlanets(): array
    {
        return Planet::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Planet $p) => [
                'id' => (string) $p->id,
                'name' => (string) $p->name,
                'flavor' => (string) $p->flavor,
                'type' => $p->type ? (string) $p->type : null,
                'class' => $p->class ? (string) $p->class : null,
                'victory_point_value' => (int) $p->victory_point_value,
                'filename' => (string) $p->filename,
                'is_standard' => (bool) $p->is_standard,
                'is_purchasable' => (bool) $p->is_purchasable,
                'is_custom' => (bool) $p->is_custom,
                'is_promotional' => (bool) $p->is_promotional,
            ])
            ->all();
    }

    // ----------------------------
    // Owned planets (per profile)
    // ----------------------------

    /**
     * Sync the connected user's owned planets from Tiber into local pivot.
     * Also stores owned IDs in profile secure storage as a fallback.
     */
    public function syncOwnedPlanets(User $profile): bool
    {
        $token = $this->tokenFor($profile);

        if (! $token) {
            return false;
        }

        try {
            $payload = $this->api->getPlanets($token);

            // Accept either {data:[...]} or [...]
            $rows = $payload['data'] ?? $payload;

            if (! is_array($rows)) {
                return false;
            }

            $ids = collect($rows)
                ->filter(fn ($p) => is_array($p) && ! empty($p['id']))
                ->map(fn ($p) => (string) $p['id'])
                ->values()
                ->all();

            // Ensure local catalog exists for these IDs (in case syncPlanets hasn't run yet)
            if (! Planet::query()->exists()) {
                $this->syncPlanets(force: true);
            }

            // Pivot sync (planet_user)
            $profile->planets()->sync($ids);

            // Store as a quick offline fallback / debug source
            $this->local->setForProfile($profile->id, 'owned_planet_ids', $ids);
            $this->local->setForProfile($profile->id, 'owned_planets_last_synced_at', now()->toISOString());

            return true;
        } catch (\Throwable $e) {
            Log::warning('Owned planet sync failed: '.$e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    /**
     * Offline-first: read owned planets from local pivot.
     */
    public function getOwnedPlanets(User $profile): array
    {
        return $profile->planets()
            ->orderBy('name')
            ->get()
            ->map(fn (Planet $p) => [
                'id' => (string) $p->id,
                'name' => (string) $p->name,
                'flavor' => (string) $p->flavor,
                'type' => $p->type ? (string) $p->type : null,
                'class' => $p->class ? (string) $p->class : null,
                'victory_point_value' => (int) $p->victory_point_value,
                'filename' => (string) $p->filename,
            ])
            ->all();
    }
}
