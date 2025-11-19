<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Phase;
use Illuminate\Http\JsonResponse;
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
 *     schema="DeliverableSummaryResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00")
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
 *     @OA\Property(property="rubric_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="rubric_names", type="string", example="Rubric OC-13, Rubric AA-94"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableCreatePayload",
 *     type="object",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
 *     @OA\Property(
 *         property="rubric_ids",
 *         type="array",
 *         nullable=true,
 *
 *         @OA\Items(type="integer"),
 *         description="Rubric identifiers to sync with the deliverable"
 *     )
 * )
 */
class DeliverableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables",
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
     *             @OA\Items(ref="#/components/schemas/DeliverableSummaryResource")
     *         )
     *     )
     * )
     */
    public function index(AcademicPeriod $academicPeriod, Phase $phase): JsonResponse
    {
        $deliverables = $phase->deliverables()->orderBy('due_date')->orderBy('id')->get();

        return response()->json(
            $deliverables
                ->map(fn (Deliverable $deliverable) => $this->transformDeliverableSummary($deliverable))
                ->values()
        );
    }

    /**
     * @OA\Post(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables",
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
    public function store(Request $request, AcademicPeriod $academicPeriod, Phase $phase): JsonResponse
    {
        $validator = Validator::make($request->all(), [
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

        $deliverable = DB::transaction(function () use ($phase, $data, $fileIds, $rubricIds) {
            $deliverable = $phase->deliverables()->create($data);

            if ($fileIds !== null) {
                $deliverable->files()->sync($fileIds);
            }

            if ($rubricIds !== null) {
                $deliverable->rubrics()->sync($rubricIds);
            }

            return $deliverable;
        });

        return response()->json($this->transformDeliverableDetail($deliverable), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}",
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
    public function show(AcademicPeriod $academicPeriod, Phase $phase, Deliverable $deliverable): JsonResponse
    {
        return response()->json($this->transformDeliverableDetail($deliverable));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}",
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
     *                 type="object",
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
    public function update(Request $request, AcademicPeriod $academicPeriod, Phase $phase, Deliverable $deliverable): JsonResponse
    {
        $validated = $request->validate([
            'phase_id' => 'prohibited',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
            'rubric_ids' => 'sometimes|array',
            'rubric_ids.*' => 'integer|exists:rubrics,id',
        ]);

        $attributes = array_intersect_key($validated, array_flip(['name', 'description', 'due_date']));
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

        return response()->json($this->transformDeliverableDetail($deliverable));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}",
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
    public function destroy(AcademicPeriod $academicPeriod, Phase $phase, Deliverable $deliverable): JsonResponse
    {
        $deliverable->delete();

        return response()->json(['message' => 'Deliverable deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/rubrics",
     *     summary="Get rubrics assigned to a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Rubrics assigned to the deliverable",
     *
     *         @OA\JsonContent(type="array", @OA\Items(type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")))
     *     ),
     *
     *     @OA\Response(response=404, description="Deliverable not found")
     * )
     */
    public function getRubrics(AcademicPeriod $academicPeriod, Phase $phase, Deliverable $deliverable): JsonResponse
    {
        $deliverable->loadMissing('rubrics');

        $rubrics = $deliverable->rubrics->map(fn ($rubric) => [
            'id' => $rubric->id,
            'name' => $rubric->name,
        ]);

        return response()->json($rubrics);
    }

    protected function transformDeliverableSummary(Deliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
        ];
    }

    protected function transformDeliverableDetail(Deliverable $deliverable): array
    {
        $deliverable->loadMissing('rubrics');

        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
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
