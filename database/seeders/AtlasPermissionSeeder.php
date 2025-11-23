<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AtlasPermissionSeeder extends Seeder
{
    protected array $permissions = [
        ['name' => 'asignable a un grupo de proyectos de grado'],
        ['name' => 'asignable a staff de proyectos de grado'],
        ['name' => 'ver propuestas'],
        ['name' => 'crear proyectos'],
        ['name' => 'editar proyectos'],
        ['name' => 'eliminar proyectos'],
        ['name' => 'ver personal de proyectos'],
        ['name' => 'crear propuestas'],
        ['name' => 'editar propuestas'],
        ['name' => 'eliminar propuestas'],
        ['name' => 'ver estados de proyectos'],
        ['name' => 'cambiar estado de proyectos'],
    ];

    public function run(): void
    {
        $baseUrl = env('ATLAS_AUTH_URL', '');
        $token = env('MODULE_PG_TOKEN', '');

        if (trim((string) $baseUrl) === '' || trim((string) $token) === '') {
            Log::warning('AtlasPermissionSeeder skipped because atlas_auth.url or atlas_auth.token is not configured.');

            return;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/').'/api/auth/permissions/batch', [
                'permissions' => $this->permissions,
            ]);

        if (! $response->successful()) {
            Log::error('Failed to seed atlas permissions.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        Log::info('Seeded atlas permissions.', ['count' => count($this->permissions)]);
    }
}
