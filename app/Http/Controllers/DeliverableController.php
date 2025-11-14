<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use Illuminate\Http\Request;
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
 *     @OA\Property(property="rubric_ids", type="array", @OA\Items(type="integer", example=7)),
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
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
 *     @OA\Property(property="rubric_ids", type="array", nullable=true, @OA\Items(type="integer", example=7))
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
        $deliverables = Deliverable::with('phase')->orderByDesc('updated_at')->get();

        return response()->json($deliverables->map(fn (Deliverable $deliverable) => [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
            'phase_name' => $deliverable->phase?->name,
        ]));
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
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deliverable = Deliverable::create($validator->validated());

        if (array_key_exists('rubric_ids', $validator->validated()) && $validator->validated()['rubric_ids'] !== null) {
            $deliverable->rubrics()->sync($this->resolveRubricIds($validator->validated()['rubric_ids'])->all());
        }

        $deliverable->load('rubrics');

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
        $deliverable->load('phase.period', 'files', 'rubrics');

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
        $request->validate([
            'phase_id' => 'sometimes|required|exists:phases,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        $deliverable->update($request->only('phase_id', 'name', 'description', 'due_date'));

        if ($request->has('rubric_ids') && $request->input('rubric_ids') !== null) {
            $deliverable->rubrics()->sync($this->resolveRubricIds($request->input('rubric_ids'))->all());
        }

        $deliverable->load('rubrics');

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

    protected function resolveRubricIds(array $rubricIds): \Illuminate\Support\Collection
    {
        return collect($rubricIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function transformDeliverable(Deliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
            'phase_id' => $deliverable->phase_id,
            'rubric_ids' => $deliverable->rubrics->pluck('id')->values()->all(),
            'created_at' => optional($deliverable->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverable->updated_at)->toDateTimeString(),
        ];
    }
}
