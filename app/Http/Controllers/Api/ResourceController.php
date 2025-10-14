<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $config = $this->resolveResource($request);
        $modelClass = $config['model'];

        $query = $modelClass::query();

        if ($with = $request->query('with')) {
            $relations = array_map('trim', explode(',', $with));
            $query->with($relations);
        }

        if (! empty($config['default_order'])) {
            foreach ($config['default_order'] as $column => $direction) {
                $query->orderBy($column, $direction);
            }
        }

        $perPage = (int) $request->query('per_page', $config['per_page'] ?? 15);
        $results = $query->paginate($perPage);

        return response()->json($results);
    }

    public function store(Request $request): JsonResponse
    {
        $config = $this->resolveResource($request);
        $modelClass = $config['model'];
        $model = new $modelClass();
        $data = $this->extractData($request, $model, $config, 'store');

        try {
            $created = $modelClass::create($data);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Resource already exists or violates a database constraint.',
                ], 409);
            }

            throw $e;
        }

        return response()->json($created, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $config = $this->resolveResource($request);
        $model = $this->resolveModel($config, $id, $request);

        return response()->json($model);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $config = $this->resolveResource($request);
        $model = $this->resolveModel($config, $id, $request);
        $data = $this->extractData($request, $model, $config, 'update');

        $model->fill($data);
        $model->save();

        return response()->json($model);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $config = $this->resolveResource($request);
        $model = $this->resolveModel($config, $id);
        $model->delete();

        return response()->json(null, 204);
    }

    protected function extractData(Request $request, Model $model, array $config, string $context): array
    {
        $rules = Arr::get($config, "rules.$context", []);
        $validated = $rules ? $request->validate($rules) : $request->all();
        $fillable = $model->getFillable();

        if ($context === 'update' && $immutable = Arr::get($config, 'immutable', [])) {
            $fillable = array_values(array_diff($fillable, $immutable));
        }

        return $fillable ? Arr::only($validated, $fillable) : $validated;
    }

    protected function resolveModel(array $config, string $identifier, ?Request $request = null): Model
    {
        $modelClass = $config['model'];
        $query = $modelClass::query();

        if ($request && $with = $request->query('with')) {
            $relations = array_map('trim', explode(',', $with));
            $query->with($relations);
        }

        if (! empty($config['composite_key'])) {
            $separator = $config['composite_separator'] ?? '|';
            $parts = explode($separator, $identifier);
            $columns = $config['composite_key'];

            if (count($parts) !== count($columns)) {
                throw new NotFoundHttpException('Invalid resource identifier.');
            }

            foreach ($columns as $index => $column) {
                $query->where($column, $parts[$index]);
            }

            $model = $query->first();
            if (! $model) {
                throw new NotFoundHttpException('Resource not found.');
            }

            return $model;
        }

        $keyName = $config['primary_key'] ?? (new $modelClass())->getKeyName();

        return $query->where($keyName, $identifier)->firstOrFail();
    }

    protected function resolveResource(Request $request): array
    {
        $route = $request->route();
        $resourceKey = null;

        if ($route) {
            $resourceKey = $route->defaults['resource'] ?? null;

            if (! $resourceKey) {
                $name = (string) $route->getName();
                if ($name !== '') {
                    $segments = explode('.', $name);
                    $resourceKey = $segments[0] ?? null;

                    if ($resourceKey === 'api' && isset($segments[1])) {
                        $resourceKey = $segments[1];
                    }
                }
            }
        }

        if (! $resourceKey) {
            throw new NotFoundHttpException('Resource not resolved.');
        }

        $config = config("api-resources.$resourceKey");

        if (! $config) {
            throw new NotFoundHttpException('Resource not configured.');
        }

        return array_merge($config, ['slug' => $resourceKey]);
    }
}
