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

        if (trim((string) $token) === '') {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $parsed = $this->parseParameters($parameters);

            $data = $this->atlasAuthService->verifyToken($token, $parsed['roles'], $parsed['permissions']);

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
            throw $e;
        } catch (\Throwable $e) {
            // Catch any unexpected errors from the HTTP client or other failures.
            Log::error('Unexpected error while verifying Atlas token.', ['exception' => $e]);

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
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
