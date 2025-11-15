<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubric\StoreRubricRequest;
use App\Http\Requests\Rubric\UpdateRubricRequest;
use App\Models\Rubric;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Rubrics",
 *     description="CRUD endpoints for rubrics and their associations"
 * )
 *
 * @OA\Schema(
 *     schema="RubricPayload",
 *     type="object",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="Rubrica Final"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="min_value", type="integer", nullable=true, example=0),
 *     @OA\Property(property="max_value", type="integer", nullable=true, example=100)
 * )
 *
 * @OA\Schema(
 *     schema="RubricResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=6),
 *     @OA\Property(property="name", type="string", example="Rubrica Final"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="min_value", type="integer", nullable=true),
 *     @OA\Property(property="max_value", type="integer", nullable=true),
 *     @OA\Property(property="thematic_line_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="thematic_line_names", type="string", example="Investigación, IoT"),
 *     @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="deliverable_names", type="string", example="Entrega 1, Entrega 2"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class RubricController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/rubrics",
     *     summary="List rubrics",
     *     tags={"Rubrics"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of rubrics with essential metadata",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=6),
     *                 @OA\Property(property="name", type="string", example="Rubrica Final"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="min_value", type="integer", nullable=true, example=0),
     *                 @OA\Property(property="max_value", type="integer", nullable=true, example=100),
     *                 @OA\Property(property="thematic_line_names", type="string", example="Investigación, IoT"),
     *                 @OA\Property(property="deliverable_names", type="string", example="Entrega 1, Entrega 2")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $rubrics = Rubric::with('thematicLines', 'deliverables')->orderByDesc('updated_at')->get();

        return response()->json($rubrics->map(fn (Rubric $rubric) => $this->transformForIndex($rubric)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/rubrics",
     *     summary="Create a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RubricPayload")),
     *
     *     @OA\Response(response=201, description="Rubric created", @OA\JsonContent(ref="#/components/schemas/RubricResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreRubricRequest $request): JsonResponse
    {
        $rubric = Rubric::create($request->safe()->only(['name', 'description', 'min_value', 'max_value']));

        $this->syncRelationships(
            $rubric,
            $request->thematicLineIds(),
            $request->deliverableIds()
        );

        return response()->json($this->transformForShow($rubric->load('thematicLines', 'deliverables')), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/rubrics/{rubric}",
     *     summary="Show a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Rubric detail", @OA\JsonContent(ref="#/components/schemas/RubricResource")),
     *     @OA\Response(response=404, description="Rubric not found")
     * )
     */
    public function show(Rubric $rubric): JsonResponse
    {
        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/rubrics/{rubric}",
     *     summary="Update a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/RubricPayload")),
     *
     *     @OA\Response(response=200, description="Rubric updated", @OA\JsonContent(ref="#/components/schemas/RubricResource")),
     *     @OA\Response(response=404, description="Rubric not found")
     * )
     */
    public function update(UpdateRubricRequest $request, Rubric $rubric): JsonResponse
    {
        $rubric->update($request->safe()->only(['name', 'description', 'min_value', 'max_value']));

        $this->syncRelationships(
            $rubric,
            $request->thematicLineIds(),
            $request->deliverableIds()
        );

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/rubrics/{rubric}",
     *     summary="Delete a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Rubric deleted"),
     *     @OA\Response(response=404, description="Rubric not found")
     * )
     */
    public function destroy(Rubric $rubric): JsonResponse
    {
        $rubric->delete();

        return response()->json(['message' => 'Rubric deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/rubrics/dropdown",
     *     summary="Rubrics dropdown",
     *     tags={"Rubrics"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pairs ready for select inputs",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=6),
     *                 @OA\Property(property="label", type="string", example="Rubrica Final")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $rubrics = Rubric::orderBy('name')->get()->map(fn (Rubric $rubric) => [
            'value' => $rubric->id,
            'label' => $rubric->name,
        ]);

        return response()->json($rubrics);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/rubrics/{rubric}/thematic-lines/{thematicLine}",
     *     summary="Attach a thematic line to a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="thematicLine", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Thematic line attached to rubric"),
     *     @OA\Response(response=404, description="Rubric or thematic line not found")
     * )
     */
    public function attachThematicLine(Rubric $rubric, int $thematicLineId): JsonResponse
    {
        $rubric->thematicLines()->attach($thematicLineId);

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/rubrics/{rubric}/thematic-lines/{thematicLine}",
     *     summary="Detach a thematic line from a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="thematicLine", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thematic line detached from rubric"),
     *     @OA\Response(response=404, description="Rubric or thematic line not found")
     * )
     */
    public function detachThematicLine(Rubric $rubric, int $thematicLineId): JsonResponse
    {
        $rubric->thematicLines()->detach($thematicLineId);

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/rubrics/{rubric}/deliverables/{deliverable}",
     *     summary="Attach a deliverable to a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Deliverable attached to rubric"),
     *     @OA\Response(response=404, description="Rubric or deliverable not found")
     * )
     */
    public function attachDeliverable(Rubric $rubric, int $deliverableId): JsonResponse
    {
        $rubric->deliverables()->attach($deliverableId);

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/rubrics/{rubric}/deliverables/{deliverable}",
     *     summary="Detach a deliverable from a rubric",
     *     tags={"Rubrics"},
     *
     *     @OA\Parameter(name="rubric", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Deliverable detached from rubric"),
     *     @OA\Response(response=404, description="Rubric or deliverable not found")
     * )
     */
    public function detachDeliverable(Rubric $rubric, int $deliverableId): JsonResponse
    {
        $rubric->deliverables()->detach($deliverableId);

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    protected function transformForIndex(Rubric $rubric): array
    {
        return [
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(Rubric $rubric): array
    {
        return [
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ];
    }

    protected function syncRelationships(Rubric $rubric, ?array $thematicLineIds, ?array $deliverableIds): void
    {
        if ($thematicLineIds !== null) {
            $rubric->thematicLines()->sync($thematicLineIds);
        }

        if ($deliverableIds !== null) {
            $rubric->deliverables()->sync($deliverableIds);
        }
    }
}
