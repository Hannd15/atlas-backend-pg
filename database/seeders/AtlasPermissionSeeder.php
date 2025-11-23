<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AtlasPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $baseUrl = env('ATLAS_AUTH_URL', '');
        $token = env('MODULE_PG_TOKEN', '');

        if (trim((string) $baseUrl) === '' || trim((string) $token) === '') {
            Log::warning('AtlasPermissionSeeder skipped because atlas_auth.url or atlas_auth.token is not configured.');

            return;
        }

        $permissions = collect(config('atlas.permissions', []))
            ->map(fn ($name) => is_string($name) ? trim($name) : $name)
            ->filter(fn ($name) => ! empty($name))
            ->unique()
            ->values()
            ->map(fn ($name) => ['name' => $name])
            ->all();

        if (empty($permissions)) {
            Log::warning('AtlasPermissionSeeder skipped because no permissions were configured.');

            return;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/').'/api/auth/permissions/batch', [
                'permissions' => $permissions,
            ]);

        if (! $response->successful()) {
            Log::error('Failed to seed atlas permissions.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        Log::info('Seeded atlas permissions.', ['count' => count($permissions)]);
    }
}
