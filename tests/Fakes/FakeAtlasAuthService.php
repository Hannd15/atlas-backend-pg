<?php

namespace Tests\Fakes;

use App\Services\AtlasAuthService;
use Illuminate\Http\Exceptions\HttpResponseException;

class FakeAtlasAuthService extends AtlasAuthService
{
    protected array $userPayload = [
        'id' => 1,
        'roles' => [],
        'name' => 'Testing User',
        'email' => 'testing@example.com',
    ];

    protected ?array $nextError = null;

    public function setUserPayload(array $payload): void
    {
        $this->userPayload = array_merge($this->userPayload, $payload);
    }

    public function failNext(int $status, array $body = []): void
    {
        $this->nextError = [
            'status' => $status,
            'body' => $body,
        ];
    }

    public function verifyToken(string $token, array $roles = [], array $permissions = []): array
    {
        if (trim($token) === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        if ($this->nextError !== null) {
            $error = $this->nextError;
            $this->nextError = null;

            throw new HttpResponseException(response()->json(
                $error['body'] ?? [],
                (int) ($error['status'] ?? 500)
            ));
        }

        return [
            'authorized' => true,
            'user' => $this->userPayload,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
