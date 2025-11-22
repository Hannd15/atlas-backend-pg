<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AtlasUserService extends AtlasAuthService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(string $token): array
    {
        return $this->sendRequest(fn () => $this->client($token)->get($this->serviceUrl().'/api/auth/users'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(string $token, int $userId): array
    {
        return $this->sendRequest(fn () => $this->client($token)->get($this->serviceUrl()."/api/auth/users/{$userId}"));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dropdown(string $token): array
    {
        return $this->sendRequest(fn () => $this->client($token)->get($this->serviceUrl().'/api/auth/users/dropdown'));
    }

    /**
     * @param  array<int, int|string|null>  $userIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchUsersByIds(string $token, array $userIds): array
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $idsList = $ids->values()->all();

        try {
            $response = $this->sendRequest(fn () => $this->client($token)->post(
                $this->serviceUrl().'/api/auth/users/batch',
                ['ids' => $idsList]
            ));

            $users = [];

            foreach ($response as $user) {
                $userId = isset($user['id']) ? (int) $user['id'] : null;

                if ($userId === null) {
                    continue;
                }

                $users[$userId] = $user;
            }

            return $users;
        } catch (HttpResponseException $exception) {
            if ($exception->getResponse()?->getStatusCode() === 404) {
                return $this->fetchUsersIndividually($token, $idsList);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, int|string|null>  $userIds
     * @return array<int, string>
     */
    public function namesByIds(string $token, array $userIds): array
    {
        $users = $this->fetchUsersByIds($token, $userIds);

        $names = [];

        foreach ($users as $id => $user) {
            if (! isset($user['name'])) {
                continue;
            }

            $names[(int) $id] = (string) $user['name'];
        }

        return $names;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function fetchUsersIndividually(string $token, array $ids): array
    {
        $users = [];

        foreach ($ids as $id) {
            try {
                $user = $this->getUser($token, $id);

                if (! empty($user)) {
                    $users[$id] = $user;
                }
            } catch (HttpResponseException $exception) {
                $status = $exception->getResponse()?->getStatusCode();

                if ($status === 404) {
                    continue;
                }

                throw $exception;
            }
        }

        return $users;
    }

    protected function client(string $token): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->ensureToken($token))
            ->timeout($this->timeout());
    }

    protected function ensureToken(string $token): string
    {
        $token = trim($token);

        if ($token === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        return $token;
    }

    /**
     * @param  callable():Response  $callback
     * @return array<int|string, mixed>
     */
    protected function sendRequest(callable $callback): array
    {
        try {
            $response = $callback();
        } catch (HttpResponseException $exception) {
            throw $exception;
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

        if ($response->successful()) {
            return $this->decodeResponse($response);
        }

        if (in_array($response->status(), [400, 401, 403, 404, 422], true)) {
            $data = $this->decodeResponse($response);

            if (empty($data)) {
                $data = [
                    'message' => 'Authentication service error.',
                ];
            }

            throw new HttpResponseException(response()->json($data, $response->status()));
        }

        Log::error('Atlas authentication service returned unexpected status.', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new HttpResponseException(response()->json([
            'message' => 'Authentication service unavailable.',
        ], 503));
    }
}
