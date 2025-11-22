<?php

namespace Tests\Fakes;

use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class FakeAtlasUserService extends AtlasUserService
{
    protected array $userRoles = [];

    protected function assertToken(string $token): void
    {
        if (trim($token) === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Missing bearer token.',
            ], 401));
        }
    }

    public function listUsers(string $token): array
    {
        $this->assertToken($token);

        return User::query()
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (User $user) => $this->formatRemoteUser($user))
            ->values()
            ->all();
    }

    public function setUserRoles(array $rolesByUser): void
    {
        $this->userRoles = collect($rolesByUser)
            ->map(fn ($roles) => $this->normalizeRolesPayload($roles))
            ->toArray();
    }

    public function getUser(string $token, int $userId): array
    {
        $this->assertToken($token);

        $user = User::find($userId);

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'message' => 'User not found.',
            ], 404));
        }

        return $this->formatRemoteUser($user);
    }

    public function updateUser(string $token, int $userId, array $payload): array
    {
        $this->assertToken($token);

        $user = User::find($userId);

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'message' => 'User not found.',
            ], 404));
        }

        $remote = $this->formatRemoteUser($user);

        foreach ($payload as $key => $value) {
            $remote[$key] = $value;
        }

        return $remote;
    }

    public function dropdown(string $token): array
    {
        $this->assertToken($token);

        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->values()
            ->all();
    }

    public function usersByPermission(string $token, string $permission): array
    {
        $this->assertToken($token);

        $normalizedPermission = Str::lower(trim((string) $permission));

        if ($normalizedPermission === '') {
            return [];
        }

        $matches = [];

        foreach (User::query()->orderBy('name')->get() as $user) {
            $roles = $this->userRoles[$user->id] ?? [];
            $normalizedRoles = array_filter(array_map(fn ($role) => Str::lower((string) $role), $roles));

            if (! in_array($normalizedPermission, $normalizedRoles, true)) {
                continue;
            }

            $matches[] = $this->formatRemoteUser($user);
        }

        return $matches;
    }

    public function fetchUsersByIds(string $token, array $userIds): array
    {
        $this->assertToken($token);

        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->map(fn (User $user) => $this->formatRemoteUser($user))
            ->toArray();
    }

    public function namesByIds(string $token, array $userIds): array
    {
        $users = $this->fetchUsersByIds($token, $userIds);

        $names = [];

        foreach ($users as $id => $user) {
            $names[(int) $id] = (string) ($user['name'] ?? "User #{$id}");
        }

        return $names;
    }

    protected function formatRemoteUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name ?? "User #{$user->id}",
            'email' => $user->email,
            'avatar' => $user->avatar,
            'roles' => $this->userRoles[$user->id] ?? [],
            'roles_list' => implode(', ', $this->userRoles[$user->id] ?? []),
        ];
    }

    protected function normalizeRolesPayload(mixed $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        if (! is_array($roles)) {
            return [];
        }

        return array_values($roles);
    }
}
