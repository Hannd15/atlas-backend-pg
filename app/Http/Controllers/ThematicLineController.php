<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThematicLine\StoreThematicLineRequest;
use App\Http\Requests\ThematicLine\UpdateThematicLineRequest;
use App\Models\ThematicLine;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Thematic Lines",
 *     description="Categorize proposals and rubrics by thematic line"
 * )
 *
 * @OA\Schema(
 *     schema="ThematicLinePayload",
 *     type="object",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="IoT"),
 *     @OA\Property(property="description", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ThematicLineResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=4),
 *     @OA\Property(property="name", type="string", example="IoT"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ThematicLineController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/thematic-lines",
     *     summary="List thematic lines",
     *     tags={"Thematic Lines"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of thematic lines",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ThematicLineResource"))
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $thematicLines = ThematicLine::with('rubrics')->orderByDesc('updated_at')->get();

        return response()->json($thematicLines->map(fn (ThematicLine $thematicLine) => $this->transformForIndex($thematicLine)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/thematic-lines",
     *     summary="Create a thematic line",
     *     tags={"Thematic Lines"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ThematicLinePayload")),
     *
     *     @OA\Response(response=201, description="Thematic line created", @OA\JsonContent(ref="#/components/schemas/ThematicLineResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreThematicLineRequest $request): JsonResponse
    {
        $thematicLine = ThematicLine::create($request->safe()->only(['name', 'description']));

        $this->syncRubrics($thematicLine, $request->rubricIds());

        return response()->json($this->transformForShow($thematicLine->load('rubrics')), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/thematic-lines/{thematic_line}",
     *     summary="Show a thematic line",
     *     tags={"Thematic Lines"},
     *
     *     @OA\Parameter(name="thematic_line", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thematic line detail", @OA\JsonContent(ref="#/components/schemas/ThematicLineResource")),
     *     @OA\Response(response=404, description="Thematic line not found")
     * )
     */
    public function show(ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->load('rubrics');

        return response()->json($this->transformForShow($thematicLine));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/thematic-lines/{thematic_line}",
     *     summary="Update a thematic line",
     *     tags={"Thematic Lines"},
     *
     *     @OA\Parameter(name="thematic_line", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/ThematicLinePayload")),
     *
     *     @OA\Response(response=200, description="Thematic line updated", @OA\JsonContent(ref="#/components/schemas/ThematicLineResource")),
     *     @OA\Response(response=404, description="Thematic line not found")
     * )
     */
    public function update(UpdateThematicLineRequest $request, ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->update($request->safe()->only(['name', 'description']));

        $this->syncRubrics($thematicLine, $request->rubricIds());

        return response()->json($this->transformForShow($thematicLine->load('rubrics')));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/thematic-lines/{thematic_line}",
     *     summary="Delete a thematic line",
     *     tags={"Thematic Lines"},
     *
     *     @OA\Parameter(name="thematic_line", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thematic line deleted"),
     *     @OA\Response(response=404, description="Thematic line not found")
     * )
     */
    public function destroy(ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->delete();

        return response()->json(['message' => 'Thematic line deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/thematic-lines/dropdown",
     *     summary="Thematic lines dropdown",
     *     tags={"Thematic Lines"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pairs ready for selects",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=4),
     *                 @OA\Property(property="label", type="string", example="IoT")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $thematicLines = ThematicLine::orderBy('id')->get()->map(fn (ThematicLine $thematicLine) => [
            'value' => $thematicLine->id,
            'label' => $thematicLine->name,
        ])->values();

        return response()->json($thematicLines);
    }

    protected function transformForIndex(ThematicLine $thematicLine): array
    {
        return [
            'id' => $thematicLine->id,
            'name' => $thematicLine->name,
            'description' => $thematicLine->description,
            'rubric_ids' => $thematicLine->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $thematicLine->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($thematicLine->created_at)->toDateTimeString(),
            'updated_at' => optional($thematicLine->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(ThematicLine $thematicLine): array
    {
        return [
            'id' => $thematicLine->id,
            'name' => $thematicLine->name,
            'description' => $thematicLine->description,
            'rubric_ids' => $thematicLine->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $thematicLine->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($thematicLine->created_at)->toDateTimeString(),
            'updated_at' => optional($thematicLine->updated_at)->toDateTimeString(),
        ];
    }

    protected function syncRubrics(ThematicLine $thematicLine, ?array $rubricIds): void
    {
        if ($rubricIds !== null) {
            $thematicLine->rubrics()->sync($rubricIds);
        }
    }
}
