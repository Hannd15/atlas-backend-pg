<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicPeriod\StoreAcademicPeriodRequest;
use App\Http\Requests\AcademicPeriod\UpdateAcademicPeriodRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\Phase;
use App\Services\PhaseDeliverableService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Academic Periods",
 *     description="API endpoints for managing academic periods"
 * )
 *
 * @OA\Schema(
 *     schema="AcademicPeriodPhaseResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=10),
 *     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
 *     @OA\Property(
 *         property="deliverables",
 *         type="array",
 *         description="Deliverables are sorted in creation order and include associated files",
 *
 *         @OA\Items(
 *             type="object",
 *
 *             @OA\Property(property="id", type="integer", example=25),
 *             @OA\Property(property="name", type="string", example="Entrega de anteproyecto"),
 *             @OA\Property(property="description", type="string", example="Documento PDF con la propuesta"),
 *             @OA\Property(property="due_date", type="string", format="date-time", example="2025-02-15T23:59:00"),
 *             @OA\Property(
 *                 property="files",
 *                 type="array",
 *
 *                 @OA\Items(
 *                     type="object",
 *
 *                     @OA\Property(property="id", type="integer", example=90),
 *                     @OA\Property(property="name", type="string", example="propuesta.pdf"),
 *                     @OA\Property(property="extension", type="string", example="pdf"),
 *                     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/propuesta.pdf")
 *                 )
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AcademicPeriodDeliverableInput",
 *     type="object",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-02-15T23:59:00"),
 *     @OA\Property(property="file_ids", type="array", nullable=true, @OA\Items(type="integer", example=42), description="Existing file IDs to associate"),
 *     @OA\Property(property="files", type="array", nullable=true, @OA\Items(type="string", format="binary"), description="Uploaded files to attach to the deliverable")
 * )
 */
class AcademicPeriodController extends Controller
{
    /**
     * The default phase names used when no custom name is provided.
     */
    protected const DEFAULT_PHASE_NAMES = [
        'Proyecto de grado I',
        'Proyecto de grado II',
    ];

    public function __construct(
        protected PhaseDeliverableService $phaseDeliverableService
    ) {}

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
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="name", type="string", example="2025-1"),
     *                 @OA\Property(property="state_name", type="string", example="Active", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $academicPeriods = AcademicPeriod::with('state')->orderByDesc('start_date')->orderByDesc('id')->get();

        return response()->json($academicPeriods->map(fn (AcademicPeriod $period) => $this->transformPeriodSummary($period)));
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
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(property="state_id", type="integer", nullable=true, example=1),
     *             @OA\Property(
     *                 property="phases",
     *                 type="object",
     *                 @OA\Property(
     *                     property="phase_one",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/AcademicPeriodDeliverableInput")
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="phase_two",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado II"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/AcademicPeriodDeliverableInput")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Academic period created successfully with automatic phases",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="2025-1"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(
     *                 property="state",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Draft"),
     *                 @OA\Property(property="description", type="string", example="Academic period is being prepared."),
     *             ),
     *             @OA\Property(
     *                 property="phases",
     *                 type="object",
     *                 @OA\Property(
     *                     property="phase_one",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=25),
     *                             @OA\Property(property="name", type="string", example="Entrega 1"),
     *                             @OA\Property(property="description", type="string", example="Documento PDF con la propuesta"),
     *                             @OA\Property(property="due_date", type="string", format="date-time", example="2025-02-15T23:59:00"),
     *                             @OA\Property(
     *                                 property="files",
     *                                 type="array",
     *
     *                                 @OA\Items(
     *                                     type="object",
     *
     *                                     @OA\Property(property="id", type="integer", example=90),
     *                                     @OA\Property(property="name", type="string", example="propuesta.pdf"),
     *                                     @OA\Property(property="extension", type="string", example="pdf"),
     *                                     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/propuesta.pdf")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="phase_two",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=11),
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado II"),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=36),
     *                             @OA\Property(property="name", type="string", example="Entrega final"),
     *                             @OA\Property(property="description", type="string", example="Repositorio con código fuente"),
     *                             @OA\Property(property="due_date", type="string", format="date-time", example="2025-06-20T23:59:00"),
     *                             @OA\Property(
     *                                 property="files",
     *                                 type="array",
     *
     *                                 @OA\Items(
     *                                     type="object",
     *
     *                                     @OA\Property(property="id", type="integer", example=94),
     *                                     @OA\Property(property="name", type="string", example="presentacion.pptx"),
     *                                     @OA\Property(property="extension", type="string", example="pptx"),
     *                                     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/presentacion.pptx")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
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
    public function store(StoreAcademicPeriodRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $previousPeriod = AcademicPeriod::with('phases.deliverables.files')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        $stateId = Arr::get($validated, 'state_id')
            ?? AcademicPeriodState::query()->where('name', 'Draft')->value('id');

        $academicPeriod = DB::transaction(function () use ($validated, $previousPeriod, $stateId) {
            $period = AcademicPeriod::create([
                'name' => Arr::get($validated, 'name'),
                'start_date' => Arr::get($validated, 'start_date'),
                'end_date' => Arr::get($validated, 'end_date'),
                'state_id' => $stateId,
            ]);

            [$phaseOne, $phaseTwo] = $this->createPhasesForPeriod($period, [
                'phase_one' => Arr::get($validated, 'phases.phase_one', []),
                'phase_two' => Arr::get($validated, 'phases.phase_two', []),
            ]);

            $previousPhases = $previousPeriod?->phases?->sortBy('id')->values() ?? collect();

            $this->syncPhaseDeliverablesFromRequest(
                $phaseOne,
                Arr::get($validated, 'phases.phase_one.deliverables'),
                request()->file('phases.phase_one.deliverables', []),
                $previousPhases->get(0)
            );

            $this->syncPhaseDeliverablesFromRequest(
                $phaseTwo,
                Arr::get($validated, 'phases.phase_two.deliverables'),
                request()->file('phases.phase_two.deliverables', []),
                $previousPhases->get(1)
            );

            return $period->load('state', 'phases.deliverables.files');
        });

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
     *         description="Academic period details with phases and deliverables",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="2025-1"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(
     *                 property="state",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Active"),
     *                 @OA\Property(property="description", type="string", example="Academic period is currently running."),
     *             ),
     *             @OA\Property(
     *                 property="phases",
     *                 type="object",
     *                 description="Always returns two phases aligned with the academic period dates",
     *                 @OA\Property(property="phase_one", ref="#/components/schemas/AcademicPeriodPhaseResource"),
     *                 @OA\Property(property="phase_two", ref="#/components/schemas/AcademicPeriodPhaseResource"),
     *             ),
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
        $academicPeriod->load('state', 'phases.deliverables.files');

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
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(property="state_id", type="integer", nullable=true, example=2),
     *             @OA\Property(
     *                 property="phases",
     *                 type="object",
     *                 description="Provide partial data to rename phases or replace deliverables. Omitted phases remain unchanged.",
     *                 @OA\Property(
     *                     property="phase_one",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado I"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *                         description="Complete replacement list for phase deliverables. Files can be sent via multipart uploads.",
     *
     *                         @OA\Items(ref="#/components/schemas/AcademicPeriodDeliverableInput")
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="phase_two",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Proyecto de grado II"),
     *                     @OA\Property(
     *                         property="deliverables",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/AcademicPeriodDeliverableInput")
     *                     )
     *                 )
     *             )
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
    public function update(UpdateAcademicPeriodRequest $request, AcademicPeriod $academicPeriod): \Illuminate\Http\JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $academicPeriod) {
            $validated = $request->validated();

            $academicPeriod->fill(Arr::only($validated, ['name', 'start_date', 'end_date', 'state_id']));
            $academicPeriod->save();

            [$phaseOne, $phaseTwo] = $this->getOrderedPhases($academicPeriod);

            foreach ([$phaseOne, $phaseTwo] as $phase) {
                $phase->update([
                    'start_date' => $academicPeriod->start_date,
                    'end_date' => $academicPeriod->end_date,
                ]);
            }

            $this->updatePhaseFromRequest(
                $phaseOne,
                Arr::get($validated, 'phases.phase_one'),
                request()->file('phases.phase_one.deliverables', []),
                0
            );

            $this->updatePhaseFromRequest(
                $phaseTwo,
                Arr::get($validated, 'phases.phase_two'),
                request()->file('phases.phase_two.deliverables', []),
                1
            );

            return $academicPeriod->load('state', 'phases.deliverables.files');
        });

        return response()->json($this->transformPeriod($updated));
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
        $academicPeriods = AcademicPeriod::orderByDesc('start_date')->get()->map(fn (AcademicPeriod $academicPeriod) => [
            'value' => $academicPeriod->id,
            'label' => $academicPeriod->name,
        ]);

        return response()->json($academicPeriods);
    }

    /**
     * Create both phases associated with the academic period and return them ordered.
     *
     * @param  array<string, array<string, mixed>>  $phasePayload
     * @return array<int, Phase>
     */
    protected function createPhasesForPeriod(AcademicPeriod $period, array $phasePayload): array
    {
        $phases = [];

        foreach ([0, 1] as $index) {
            $key = $index === 0 ? 'phase_one' : 'phase_two';
            $payload = $phasePayload[$key] ?? [];

            $phases[$index] = $period->phases()->create([
                'name' => $payload['name'] ?? self::DEFAULT_PHASE_NAMES[$index],
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
            ]);
        }

        return $phases;
    }

    /**
     * Ensure the period always exposes two phases ordered consistently.
     *
     * @return array<int, Phase>
     */
    protected function getOrderedPhases(AcademicPeriod $period): array
    {
        $period->loadMissing('phases.deliverables.files');

        $phases = $period->phases->sortBy('id')->values();

        while ($phases->count() < 2) {
            $index = $phases->count();
            $phases->push($period->phases()->create([
                'name' => self::DEFAULT_PHASE_NAMES[$index] ?? ('Fase '.($index + 1)),
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
            ]));
        }

        return [$phases->get(0), $phases->get(1)];
    }

    /**
     * Synchronise deliverables for a phase from the request or fallback to the previous period.
     */
    protected function syncPhaseDeliverablesFromRequest(Phase $phase, ?array $deliverablesPayload, array $deliverablesFiles, ?Phase $fallbackPhase = null): void
    {
        $normalizedFiles = is_array($deliverablesFiles) ? $deliverablesFiles : [];

        if (is_array($deliverablesPayload)) {
            $this->phaseDeliverableService->replaceDeliverables($phase, $deliverablesPayload, $normalizedFiles);

            return;
        }

        if ($fallbackPhase) {
            $fallbackPhase->loadMissing('deliverables.files');
            $this->phaseDeliverableService->cloneFromPhase($phase, $fallbackPhase);
        }
    }

    /**
     * Update phase metadata and deliverables when present in the request payload.
     */
    protected function updatePhaseFromRequest(Phase $phase, ?array $payload, array $deliverablesFiles, int $phaseIndex): void
    {
        if (! is_array($payload)) {
            return;
        }

        if (array_key_exists('name', $payload)) {
            $phase->update([
                'name' => $payload['name'] ?? self::DEFAULT_PHASE_NAMES[$phaseIndex] ?? $phase->name,
            ]);
        }

        if (array_key_exists('deliverables', $payload)) {
            $deliverablesPayload = $payload['deliverables'] ?? [];
            $this->phaseDeliverableService->replaceDeliverables($phase, $deliverablesPayload, is_array($deliverablesFiles) ? $deliverablesFiles : []);
        }
    }

    /**
     * Shape the academic period response with nested phases and deliverables.
     */
    protected function transformPeriodSummary(AcademicPeriod $period): array
    {
        $period->loadMissing('state');

        return [
            'id' => $period->id,
            'name' => $period->name,
            'state_name' => $period->state?->name,
        ];
    }

    protected function transformPeriod(AcademicPeriod $period): array
    {
        $period->loadMissing('state', 'phases.deliverables.files');

        [$phaseOne, $phaseTwo] = $this->getOrderedPhases($period);

        return [
            'id' => $period->id,
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'state' => $period->state ? [
                'id' => $period->state->id,
                'name' => $period->state->name,
                'description' => $period->state->description,
            ] : null,
            'phases' => [
                'phase_one' => $this->transformPhase($phaseOne),
                'phase_two' => $this->transformPhase($phaseTwo),
            ],
            'created_at' => optional($period->created_at)->toDateTimeString(),
            'updated_at' => optional($period->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformPhase(Phase $phase): array
    {
        return [
            'id' => $phase->id,
            'name' => $phase->name,
            'start_date' => optional($phase->start_date)->toDateString(),
            'end_date' => optional($phase->end_date)->toDateString(),
            'deliverables' => $phase->deliverables->map(function (\App\Models\Deliverable $deliverable) {
                return [
                    'id' => $deliverable->id,
                    'name' => $deliverable->name,
                    'description' => $deliverable->description,
                    'due_date' => optional($deliverable->due_date)->toDateTimeString(),
                    'files' => $deliverable->files->map(function (\App\Models\File $file) {
                        return [
                            'id' => $file->id,
                            'name' => $file->name,
                            'extension' => $file->extension,
                            'url' => $file->url,
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }
}
