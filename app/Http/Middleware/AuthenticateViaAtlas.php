<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Authenticate requests by delegating to the Atlas Auth service.
 *
 * Usage example:
 * Route::middleware('auth.atlas:roles=admin|manager,permissions=view-projects|edit-projects')->group(...);
 */
class AuthenticateViaAtlas
{
    public function handle(Request $request, Closure $next, string ...$parameters)
    {
        $token = $request->bearerToken();

        if (! $token) {
            throw new HttpResponseException(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $serviceUrl = rtrim((string) config('services.atlas_auth.url'), '/');

        if ($serviceUrl === '') {
            Log::error('Atlas authentication service URL is not configured.');

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        $payload = $this->buildPayload($parameters);

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout((int) config('services.atlas_auth.timeout', 10))
                ->post($serviceUrl.'/api/auth/token/verify', $payload);
        } catch (ConnectionException $exception) {
            Log::error('Atlas authentication service unreachable.', [
                'exception' => $exception,
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        } catch (\Throwable $exception) {
            Log::error('Atlas authentication service call failed.', [
                'exception' => $exception,
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new HttpResponseException(response()->json(
                $this->decodeResponse($response),
                $response->status()
            ));
        }

        if (! $response->successful()) {
            Log::error('Atlas authentication service returned unexpected status.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        $data = $this->decodeResponse($response);

        if (! ($data['authorized'] ?? false)) {
            throw new HttpResponseException(response()->json($data, 403));
        }

        if (! array_key_exists('user', $data)) {
            Log::warning('Atlas authentication success response missing user payload.', [
                'response' => $data,
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        $request->attributes->set('atlasUser', $data['user']);

        return $next($request);
    }

    protected function buildPayload(array $parameters): array
    {
        $payload = [];
        $parsed = $this->parseParameters($parameters);

        if (! empty($parsed['roles'])) {
            $payload['roles'] = $parsed['roles'];
        }

        if (! empty($parsed['permissions'])) {
            $payload['permissions'] = $parsed['permissions'];
        }

        return $payload;
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

        return array_filter($config);
    }

    protected function decodeResponse(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
