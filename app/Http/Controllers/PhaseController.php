<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePhaseRequest;
use App\Models\Phase;

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
 *     @OA\Property(property="period_id", type="integer", example=2),
 *     @OA\Property(property="period_names", type="string", example="2025-1"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
 * )
 */
class PhaseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/phases",
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
     *             @OA\Items(ref="#/components/schemas/PhaseResource")
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $phases = Phase::with('period')->orderBy('updated_at', 'desc')->get();

        $phases->each(function ($phase) {
            $phase->period_names = $phase->period ? $phase->period->name : '';
        });

        return response()->json($phases);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/phases/{id}",
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
    public function show(Phase $phase): \Illuminate\Http\JsonResponse
    {
        $phase->load('period');

        return response()->json($phase);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/phases/{id}",
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
    public function update(UpdatePhaseRequest $request, Phase $phase): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $phase->update($validated);
        $phase->load('period');

        return response()->json($phase);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/phases/dropdown",
     *     summary="Get phases for dropdown",
     *     tags={"Phases"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of phases formatted for dropdowns",
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
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $phases = Phase::all()->map(function ($phase) {
            return [
                'value' => $phase->id,
                'label' => $phase->name,
            ];
        });

        return response()->json($phases);
    }
}
