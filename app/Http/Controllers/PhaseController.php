<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Phases",
 *     description="API endpoints for managing phases (read-only, phases are auto-created with academic periods)"
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
     *         description="List of phases with period and deliverable names"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $phases = Phase::with('period', 'deliverables')->orderBy('updated_at', 'desc')->get();

        $phases->each(function ($phase) {
            $phase->period_names = $phase->period ? $phase->period->name : '';
            $phase->deliverable_names = $phase->deliverables->pluck('name')->implode(', ');
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
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Phase details with relation IDs"
     *     )
     * )
     */
    public function show(Phase $phase): \Illuminate\Http\JsonResponse
    {
        $phase->load('period', 'deliverables');

        $phase->period_id = $phase->period ? [$phase->period->id] : [];
        $phase->deliverable_ids = $phase->deliverables->pluck('id');

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
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="period_id", type="integer"),
     *             @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Phase updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Phase $phase): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'period_id' => 'nullable|exists:academic_periods,id',
            'deliverable_ids' => 'nullable|array',
            'deliverable_ids.*' => 'exists:deliverables,id',
        ]);

        $phase->update($request->only('name', 'start_date', 'end_date', 'period_id'));

        if ($request->has('deliverable_ids')) {
            $phase->deliverables()->sync($request->input('deliverable_ids'));
        }

        return response()->json($phase);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/phases/{id}",
     *     summary="Delete a phase",
     *     tags={"Phases"},
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
     *         description="Phase deleted successfully"
     *     )
     * )
     */
    public function destroy(Phase $phase): \Illuminate\Http\JsonResponse
    {
        $phase->delete();

        return response()->json(['message' => 'Phase deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/phases/dropdown",
     *     summary="Get phases for dropdown",
     *     tags={"Phases"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of phases formatted for dropdowns"
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
