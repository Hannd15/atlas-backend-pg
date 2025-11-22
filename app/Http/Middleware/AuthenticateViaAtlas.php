<?php

namespace App\Http\Middleware;

use App\Models\GroupMember;
use App\Models\ProjectGroup;
use App\Services\AtlasAuthService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            $this->ensureUserOwnsProjectGroup($data['user']);

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

    protected function ensureUserOwnsProjectGroup(array $user): void
    {
        $userId = $this->extractUserId($user);

        if ($userId === null) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            if (GroupMember::where('user_id', $userId)->exists()) {
                return;
            }

            $group = ProjectGroup::create(['project_id' => null]);

            try {
                $group->members()->create(['user_id' => $userId]);
            } catch (QueryException $exception) {
                if ($this->isUniqueMembershipViolation($exception)) {
                    $group->delete();

                    return;
                }

                throw $exception;
            }
        });
    }

    protected function extractUserId(array $user): ?int
    {
        $userId = $user['id'] ?? null;

        if ($userId === null || $userId === '') {
            return null;
        }

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return null;
        }

        return (int) $userId;
    }

    protected function isUniqueMembershipViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState === '23000') {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'group_members_user_id_unique');
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
