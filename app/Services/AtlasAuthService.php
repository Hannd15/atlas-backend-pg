<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AtlasAuthService
{
    public function verifyToken(string $token, array $roles = [], array $permissions = []): array
    {
        if ($token === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $serviceUrl = $this->serviceUrl();
        $payload = $this->buildPayload($roles, $permissions);

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout($this->timeout())
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

        return $data;
    }

    protected function serviceUrl(): string
    {
        $serviceUrl = (string) config('services.atlas_auth.url', '');

        if ($serviceUrl === '') {
            Log::error('Atlas authentication service URL is not configured.');

            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        return rtrim($serviceUrl, '/');
    }

    protected function timeout(): int
    {
        return (int) config('services.atlas_auth.timeout', 10);
    }

    /**
     * @return array{roles?: array<int, string>, permissions?: array<int, string>}
     */
    protected function buildPayload(array $roles, array $permissions): array
    {
        $payload = [];

        if (! empty($roles)) {
            $payload['roles'] = array_values(array_filter(
                array_map(fn ($role) => is_string($role) ? trim($role) : $role, $roles),
                fn ($role) => ! empty($role)
            ));
        }

        if (! empty($permissions)) {
            $payload['permissions'] = array_values(array_filter(
                array_map(fn ($permission) => is_string($permission) ? trim($permission) : $permission, $permissions),
                fn ($permission) => ! empty($permission)
            ));
        }

        return $payload;
    }

    protected function decodeResponse(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
