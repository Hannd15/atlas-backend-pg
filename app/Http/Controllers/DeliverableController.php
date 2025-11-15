<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Deliverables",
 *     description="API endpoints for managing deliverables"
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
 *     @OA\Property(property="phase_id", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableCreatePayload",
 *     type="object",
 *     required={"phase_id","name"},
 *
 *     @OA\Property(property="phase_id", type="integer", example=5),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00")
 * )
 */
class DeliverableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/deliverables",
     *     summary="Get all deliverables",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables with essential metadata",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="name", type="string", example="Entrega 1"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
     *                 @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
     *                 @OA\Property(property="phase_name", type="string", nullable=true, example="Proyecto de grado I")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::with('phase.period', 'files', 'rubrics')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(
            $deliverables->map(fn (Deliverable $deliverable) => $this->transformDeliverable($deliverable))
        );
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverables",
     *     summary="Create a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(ref="#/components/schemas/DeliverableCreatePayload")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phase_id' => 'required|exists:phases,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
            'rubric_ids' => 'sometimes|array',
            'rubric_ids.*' => 'integer|exists:rubrics,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $fileIds = $this->normalizeIds($data['file_ids'] ?? null);
        $rubricIds = $this->normalizeIds($data['rubric_ids'] ?? null);

        unset($data['file_ids'], $data['rubric_ids']);

        $deliverable = DB::transaction(function () use ($data, $fileIds, $rubricIds) {
            $deliverable = Deliverable::create($data);

            if ($fileIds !== null) {
                $deliverable->files()->sync($fileIds);
            }

            if ($rubricIds !== null) {
                $deliverable->rubrics()->sync($rubricIds);
            }

            return $deliverable;
        });

        $deliverable->load('phase.period', 'files', 'rubrics');

        return response()->json($this->transformDeliverable($deliverable), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Get a specific deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable details with phase and file metadata",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
     *     )
     * )
     */
    public function show(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->load('phase.period', 'files');

        return response()->json($this->transformDeliverable($deliverable));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Update a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="phase_id", type="integer", nullable=true),
     *                 @OA\Property(property="name", type="string", nullable=true),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="rubric_ids", type="array", @OA\Items(type="integer"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
     *     )
     * )
     */
    public function update(Request $request, Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'phase_id' => 'sometimes|required|exists:phases,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
            'rubric_ids' => 'sometimes|array',
            'rubric_ids.*' => 'integer|exists:rubrics,id',
        ]);

        $attributes = array_intersect_key($validated, array_flip(['phase_id', 'name', 'description', 'due_date']));
        $fileIds = array_key_exists('file_ids', $validated) ? $this->normalizeIds($validated['file_ids']) : null;
        $rubricIds = array_key_exists('rubric_ids', $validated) ? $this->normalizeIds($validated['rubric_ids']) : null;

        DB::transaction(function () use ($deliverable, $attributes, $fileIds, $rubricIds) {
            if (! empty($attributes)) {
                $deliverable->update($attributes);
            }

            if ($fileIds !== null) {
                $deliverable->files()->sync($fileIds);
            }

            if ($rubricIds !== null) {
                $deliverable->rubrics()->sync($rubricIds);
            }
        });

        $deliverable->load('phase.period', 'files', 'rubrics');

        return response()->json($this->transformDeliverable($deliverable));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Delete a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable deleted successfully"
     *     )
     * )
     */
    public function destroy(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->delete();

        return response()->json(['message' => 'Deliverable deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/dropdown",
     *     summary="Get deliverables for dropdown",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::orderByDesc('updated_at')->get()->map(fn (Deliverable $deliverable) => [
            'value' => $deliverable->id,
            'label' => $deliverable->name,
        ]);

        return response()->json($deliverables);
    }

    protected function transformDeliverable(Deliverable $deliverable): array
    {
        $deliverable->loadMissing('phase.period', 'files', 'rubrics');

        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
            'phase' => $deliverable->phase ? [
                'id' => $deliverable->phase->id,
                'name' => $deliverable->phase->name,
                'period' => $deliverable->phase->period ? [
                    'id' => $deliverable->phase->period->id,
                    'name' => $deliverable->phase->period->name,
                ] : null,
            ] : null,
            'files' => $deliverable->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'extension' => $file->extension,
                'url' => $file->url,
            ])->values()->all(),
            'file_ids' => $deliverable->files->pluck('id')->values()->all(),
            'rubrics' => $deliverable->rubrics->map(fn ($rubric) => [
                'id' => $rubric->id,
                'name' => $rubric->name,
                'description' => $rubric->description,
                'min_value' => $rubric->min_value,
                'max_value' => $rubric->max_value,
            ])->values()->all(),
            'rubric_ids' => $deliverable->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $deliverable->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($deliverable->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverable->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * @param  array<int, int|string>|null  $ids
     * @return array<int, int>|null
     */
    protected function normalizeIds(?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }

        return collect($ids)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
