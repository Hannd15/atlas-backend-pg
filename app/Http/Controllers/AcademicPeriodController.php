<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Academic Periods",
 *     description="API endpoints for managing academic periods"
 * )
 */
class AcademicPeriodController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods",
     *     summary="Get all academic periods",
     *     tags={"Academic Periods"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of academic periods with phase names",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="start_date", type="string", format="date"),
     *                 @OA\Property(property="end_date", type="string", format="date"),
     *                 @OA\Property(property="phase_names", type="string", description="Comma-separated phase names"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $academicPeriods = AcademicPeriod::with(['phases'])->orderBy('updated_at', 'desc')->get();

        $academicPeriods->each(function ($academicPeriod) {
            $academicPeriod->phase_names = $academicPeriod->phases->pluck('name')->implode(', ');
        });

        return response()->json($academicPeriods);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/academic-periods",
     *     summary="Create a new academic period",
     *     tags={"Academic Periods"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name","start_date","end_date"},
     *
     *             @OA\Property(property="name", type="string", example="2025-1"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Academic period created successfully with automatic phases",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
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
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $academicPeriod = DB::transaction(function () use ($request) {
            $academicPeriod = AcademicPeriod::create($request->only('name', 'start_date', 'end_date'));

            // Calculate date ranges for the two phases
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $totalDays = $startDate->diff($endDate)->days;
            $halfwayPoint = (int) ($totalDays / 2);

            $phase1EndDate = (clone $startDate)->modify("+{$halfwayPoint} days");
            $phase2StartDate = (clone $phase1EndDate)->modify('+1 day');

            // Create Proyecto de grado I
            $academicPeriod->phases()->create([
                'name' => 'Proyecto de grado I',
                'start_date' => $request->start_date,
                'end_date' => $phase1EndDate->format('Y-m-d'),
            ]);

            // Create Proyecto de grado II
            $academicPeriod->phases()->create([
                'name' => 'Proyecto de grado II',
                'start_date' => $phase2StartDate->format('Y-m-d'),
                'end_date' => $request->end_date,
            ]);

            return $academicPeriod;
        });

        return response()->json($academicPeriod, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{id}",
     *     summary="Get a specific academic period",
     *     tags={"Academic Periods"},
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
     *         description="Academic period details with phase IDs",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="phase_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Academic period not found"
     *     )
     * )
     */
    public function show(AcademicPeriod $academicPeriod): \Illuminate\Http\JsonResponse
    {
        $academicPeriod->load('phases');

        $academicPeriod->phase_ids = $academicPeriod->phases->pluck('id');

        return response()->json($academicPeriod);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/academic-periods/{id}",
     *     summary="Update an academic period",
     *     tags={"Academic Periods"},
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
     *             @OA\Property(property="end_date", type="string", format="date")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Academic period updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Academic period not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, AcademicPeriod $academicPeriod): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $academicPeriod->update($request->only('name', 'start_date', 'end_date'));

        return response()->json($academicPeriod);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/academic-periods/{id}",
     *     summary="Delete an academic period",
     *     tags={"Academic Periods"},
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
     *         description="Academic period deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Academic period deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Academic period not found"
     *     )
     * )
     */
    public function destroy(AcademicPeriod $academicPeriod): \Illuminate\Http\JsonResponse
    {
        $academicPeriod->delete();

        return response()->json(['message' => 'Academic period deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/dropdown",
     *     summary="Get academic periods for dropdown",
     *     tags={"Academic Periods"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of academic periods formatted for dropdowns",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer"),
     *                 @OA\Property(property="label", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $academicPeriods = AcademicPeriod::all()->map(function ($academicPeriod) {
            return [
                'value' => $academicPeriod->id,
                'label' => $academicPeriod->name,
            ];
        });

        return response()->json($academicPeriods);
    }
}
