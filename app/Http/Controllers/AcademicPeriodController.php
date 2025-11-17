<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicPeriod\StoreAcademicPeriodRequest;
use App\Http\Requests\AcademicPeriod\UpdateAcademicPeriodRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Academic Periods",
 *     description="API endpoints for managing academic periods"
 * )
 *
 * @OA\Schema(
 *     schema="AcademicPeriodSummary",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="2025-1"),
 *     @OA\Property(property="state_name", type="string", example="Activo", nullable=true),
 *     @OA\Property(property="state_description", type="string", example="Periodo en curso", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AcademicPeriodResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="2025-1"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
 *     @OA\Property(property="state_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="state_name", type="string", nullable=true, example="Activo"),
 *     @OA\Property(property="state_description", type="string", nullable=true, example="Periodo en curso"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
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
     *         description="List of academic periods with their state name",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/AcademicPeriodSummary")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $academicPeriods = AcademicPeriod::with('state')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($academicPeriods->map(fn (AcademicPeriod $period) => $this->transformPeriodSummary($period))->values());
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
     *         description="Academic period created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AcademicPeriodResource")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(StoreAcademicPeriodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $academicPeriod = DB::transaction(function () use ($validated) {
            $activeState = AcademicPeriodState::ensureActive();
            $finishedState = AcademicPeriodState::ensureFinished();

            AcademicPeriod::query()->update(['state_id' => $finishedState->id]);

            $period = AcademicPeriod::create([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'state_id' => $activeState->id,
            ]);

            $this->createDefaultPhases($period);

            return $period;
        });

        $academicPeriod->load('state');

        return response()->json($this->transformPeriod($academicPeriod), 201);
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
     *         description="Academic period details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AcademicPeriodResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Academic period not found"
     *     )
     * )
     */
    public function show(AcademicPeriod $academicPeriod): JsonResponse
    {
        $academicPeriod->load('state');

        return response()->json($this->transformPeriod($academicPeriod));
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
     *             @OA\Property(property="name", type="string", example="2025-1"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Academic period updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AcademicPeriodResource")
     *     ),
     *
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
    public function update(UpdateAcademicPeriodRequest $request, AcademicPeriod $academicPeriod): JsonResponse
    {
        $validated = $request->validated();

        foreach (['name', 'start_date', 'end_date'] as $attribute) {
            if (array_key_exists($attribute, $validated)) {
                $academicPeriod->{$attribute} = $validated[$attribute];
            }
        }

        $academicPeriod->save();

        $academicPeriod->load('state');

        return response()->json($this->transformPeriod($academicPeriod));
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
    public function destroy(AcademicPeriod $academicPeriod): JsonResponse
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
     *         description="Pairs ready for selects",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=3),
     *                 @OA\Property(property="label", type="string", example="2025-1")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $periods = AcademicPeriod::orderBy('start_date', 'desc')->get()->map(fn (AcademicPeriod $period) => [
            'value' => $period->id,
            'label' => $period->name,
        ])->values();

        return response()->json($periods);
    }

    protected function transformPeriodSummary(AcademicPeriod $period): array
    {
        $period->loadMissing('state');

        return [
            'id' => $period->id,
            'name' => $period->name,
            'state_name' => $period->state?->name,
            'state_description' => $period->state?->description,
        ];
    }

    protected function transformPeriod(AcademicPeriod $period): array
    {
        $period->loadMissing('state');

        return [
            'id' => $period->id,
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'state_id' => $period->state?->id,
            'state_name' => $period->state?->name,
            'state_description' => $period->state?->description,
            'created_at' => optional($period->created_at)->toDateTimeString(),
            'updated_at' => optional($period->updated_at)->toDateTimeString(),
        ];
    }

    protected function createDefaultPhases(AcademicPeriod $academicPeriod): void
    {
        if ($academicPeriod->phases()->exists()) {
            return;
        }

        $start = $academicPeriod->start_date;
        $end = $academicPeriod->end_date;

        // Calculate midpoint
        $midpoint = $start->copy()->addDays((int) floor($start->diffInDays($end) / 2));

        $phases = [
            [
                'name' => 'Proyecto de grado I',
                'start_date' => $start,
                'end_date' => $midpoint,
            ],
            [
                'name' => 'Proyecto de grado II',
                'start_date' => $midpoint->copy()->addDay(),
                'end_date' => $end,
            ],
        ];

        foreach ($phases as $phase) {
            $academicPeriod->phases()->create($phase);
        }
    }
}
