<?php

namespace App\Http\Controllers;

use App\Services\AtlasAuthService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

abstract class AtlasAuthenticatedController extends Controller
{
    public function __construct(protected AtlasAuthService $atlasAuthService) {}

    protected function resolveAuthenticatedUserId(Request $request): int
    {
        $user = $this->resolveAtlasUser($request);

        if (! isset($user['id'])) {
            throw new HttpResponseException(response()->json([
                'message' => 'Authenticated user payload is missing an id.',
            ], 503));
        }

        return (int) $user['id'];
    }

    protected function resolveAtlasUser(Request $request): array
    {
        $userData = $request->attributes->get('atlasUser');

        if (is_array($userData) && ! $this->shouldRefreshAtlasUser($userData)) {
            return $userData;
        }

        $token = trim((string) $request->bearerToken());

        if ($token === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Missing bearer token.',
            ], 401));
        }

        $payload = $this->atlasAuthService->verifyToken($token);
        $userData = $payload['user'] ?? null;

        if (! is_array($userData)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        $request->attributes->set('atlasUser', $userData);

        return $userData;
    }

    protected function shouldRefreshAtlasUser(array $userData): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        $normalized = array_change_key_case($userData, CASE_LOWER);

        if ($normalized === [] || (count($normalized) === 1 && isset($normalized['id']))) {
            return true;
        }

        return ! array_key_exists('roles', $normalized);
    }
}
