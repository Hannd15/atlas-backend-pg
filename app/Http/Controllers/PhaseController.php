<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePhaseRequest;
use App\Models\AcademicPeriod;
use App\Models\Phase;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Phases",
 *     description="API endpoints for managing phases (phases are auto-created with academic periods and tied to them immutably)"
 * )
 *
 * @OA\Schema(
 *     schema="PhaseResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
 * )
 *
 * @OA\Schema(
 *     schema="PhaseSummaryResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
 * )
 */
class PhaseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases",
     *     summary="Get all phases",
     *     tags={"Phases"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of all phases with period information",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/PhaseSummaryResource")
     *         )
     *     )
     * )
     */
    public function index(AcademicPeriod $academicPeriod): JsonResponse
    {
        $phases = $academicPeriod->phases()->orderBy('name')->get();

        return response()->json(
            $phases
                ->map(fn (Phase $phase) => [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'start_date' => $phase->start_date,
                    'end_date' => $phase->end_date,
                ])
                ->values()
        );
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}",
     *     summary="Get a specific phase",
     *     tags={"Phases"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Phase ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Phase details with period relationship",
     *
     *         @OA\JsonContent(ref="#/components/schemas/PhaseResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Phase not found"
     *     )
     * )
     */
    public function show(AcademicPeriod $academicPeriod, Phase $phase): JsonResponse
    {
        return response()->json($this->transformPhase($phase));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}",
     *     summary="Update a phase",
     *     tags={"Phases"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Phase ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Phase update payload. When updating dates, they must be within the academic period and cannot overlap with other phases.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Proyecto de grado I"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Phase updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/PhaseResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Phase not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdatePhaseRequest $request, AcademicPeriod $academicPeriod, Phase $phase): JsonResponse
    {
        $validated = $request->validated();

        $phase->update($validated);

        return response()->json($this->transformPhase($phase));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/dropdown",
     *     summary="Get phases for dropdown (scoped to academic period)",
     *     tags={"Phases"},
     *
     *     @OA\Parameter(
     *         name="academic_period",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
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
     *                 @OA\Property(property="value", type="integer", example=1),
     *                 @OA\Property(property="label", type="string", example="Proyecto de grado I")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(AcademicPeriod $academicPeriod): JsonResponse
    {
        $phases = $academicPeriod->phases()->orderBy('name')->get()->map(fn (Phase $phase) => [
            'value' => $phase->id,
            'label' => $phase->name,
        ]);

        return response()->json($phases);
    }

    private function transformPhase(Phase $phase): array
    {
        return [
            'id' => $phase->id,
            'name' => $phase->name,
            'start_date' => optional($phase->start_date)->toDateString(),
            'end_date' => optional($phase->end_date)->toDateString(),
            'created_at' => optional($phase->created_at)->toDateTimeString(),
            'updated_at' => optional($phase->updated_at)->toDateTimeString(),
        ];
    }
}
