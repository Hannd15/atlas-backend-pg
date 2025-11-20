<?php

namespace App\Http\Middleware;

use App\Services\AtlasAuthService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Authenticate requests by delegating to the Atlas Auth service.
 *
 * Usage example:
 * Route::middleware('auth.atlas:roles=admin|manager,permissions=view-projects|edit-projects')->group(...);
 */
class AuthenticateViaAtlas
{
    public function __construct(
        protected AtlasAuthService $atlasAuthService
    ) {}

    public function handle(Request $request, Closure $next, string ...$parameters)
    {
        $token = $request->bearerToken();

        /* if (! $token) {

            if (app()->environment(['local', 'testing'])) {
                Log::debug('No token provided, but allowing request in local/testing environment');

                return $next($request);
            }

            throw new HttpResponseException(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        } */

        try {
            $parsed = $this->parseParameters($parameters);

            $data = $this->atlasAuthService->verifyToken('4|b6xuGrQAn2mazDMrrQuTNtkhYGHrspcl2iE34Yfmc1090de5', $parsed['roles'], $parsed['permissions']);

            if (! array_key_exists('user', $data)) {
                Log::warning('Atlas authentication success response missing user payload.', [
                    'response' => $data,
                ]);

                throw new HttpResponseException(response()->json([
                    'message' => 'Authentication service unavailable.',
                ], 503));
            }

            $request->attributes->set('atlasUser', $data['user']);
        } catch (HttpResponseException $e) {
            if (app()->environment(['local', 'testing'])) {
                Log::warning('Atlas auth failed in local/testing environment, allowing request anyway', [
                    'status' => $e->getResponse()->getStatusCode(),
                ]);

                return $next($request);
            }

            throw $e;
        }

        return $next($request);
    }

    protected function parseParameters(array $parameters): array
    {
        $config = [
            'roles' => [],
            'permissions' => [],
        ];

        foreach ($parameters as $parameter) {
            if (! str_contains($parameter, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $parameter, 2);

            $key = strtolower(trim($rawKey));
            $values = collect(preg_split('/[|;]/', $rawValue))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();

            if (in_array($key, ['roles', 'permissions'], true)) {
                $config[$key] = $values;
            }
        }

        return $config;
    }
}
