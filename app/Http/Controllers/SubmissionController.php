<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Submissions", description="API endpoints for project deliverable submissions")
 *
 * @OA\Schema(
 *     schema="SubmissionResource",
 *     type="object",
 *     description="Minimal submission representation. Related files and evaluations available via dedicated endpoints.",
 *
 *     @OA\Property(property="id", type="integer", example=45),
 *     @OA\Property(property="deliverable_id", type="integer", example=12),
 *     @OA\Property(property="project_id", type="integer", example=30),
 *     @OA\Property(property="submission_date", type="string", format="date-time", example="2025-04-19T10:00:00"),
 *     @OA\Property(property="deliverable_name", type="string", nullable=true, example="Entrega Parcial"),
 *     @OA\Property(property="project_title", type="string", nullable=true, example="Proyecto Integrador"),
 *     @OA\Property(property="phase_name", type="string", nullable=true, example="PG I"),
 *     @OA\Property(property="period_name", type="string", nullable=true, example="2025-1"),
 *     @OA\Property(property="file_count", type="integer", example=3),
 *     @OA\Property(property="evaluation_count", type="integer", example=2),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionCreatePayload",
 *     type="object",
 *     required={"deliverable_id","project_id","submission_date"},
 *
 *     @OA\Property(property="deliverable_id", type="integer", example=12),
 *     @OA\Property(property="project_id", type="integer", example=30),
 *     @OA\Property(property="submission_date", type="string", format="date-time", example="2025-04-19T10:00:00"),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"))
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionUpdatePayload",
 *     type="object",
 *
 *     @OA\Property(property="deliverable_id", type="integer"),
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="submission_date", type="string", format="date-time"),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer"))
 * )
 */
class SubmissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions",
     *     summary="List submissions for a deliverable",
     *     tags={"Submissions"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of submissions",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/SubmissionResource")
     *         )
     *     )
     * )
     */
    public function index($academicPeriod, $phase, $deliverable): JsonResponse
    {
        $submissions = Submission::where('deliverable_id', $deliverable)
            ->with([
                'deliverable.phase.period',
                'project',
                'files',
                'evaluations',
            ])->orderByDesc('updated_at')->get();

        return response()->json($submissions->map(fn (Submission $submission) => $this->transformSubmission($submission)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions",
     *     summary="Create a submission for a deliverable",
     *     tags={"Submissions"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionCreatePayload")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Submission created",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionResource")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $academicPeriod, $phase, $deliverable): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'submission_date' => 'required|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['deliverable_id'] = $deliverable;
        $fileIds = $this->normalizeIds($data['file_ids'] ?? null);
        unset($data['file_ids']);

        $submission = DB::transaction(function () use ($data, $fileIds) {
            $submission = Submission::create($data);

            if ($fileIds !== null) {
                $submission->files()->sync($fileIds);
            }

            return $submission;
        });
        $submission->load('deliverable.phase.period', 'project', 'files', 'evaluations');

        return response()->json($this->transformSubmission($submission), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}",
     *     summary="Show submission",
     *     tags={"Submissions"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Submission resource",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionResource")
     *     )
     * )
     */
    public function show($academicPeriod, $phase, $deliverable, Submission $submission): JsonResponse
    {
        $submission->load('deliverable.phase.period', 'project', 'files', 'evaluations');

        return response()->json($this->transformSubmission($submission));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}",
     *     summary="Update submission",
     *     tags={"Submissions"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/SubmissionUpdatePayload")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Updated submission",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionResource")
     *     )
     * )
     */
    public function update(Request $request, $academicPeriod, $phase, $deliverable, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'deliverable_id' => 'sometimes|required|exists:deliverables,id',
            'project_id' => 'sometimes|required|exists:projects,id',
            'submission_date' => 'sometimes|required|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        $attributes = array_intersect_key($validated, array_flip(['deliverable_id', 'project_id', 'submission_date']));
        $fileIds = array_key_exists('file_ids', $validated) ? $this->normalizeIds($validated['file_ids']) : null;

        DB::transaction(function () use ($submission, $attributes, $fileIds) {
            if (! empty($attributes)) {
                $submission->update($attributes);
            }

            if ($fileIds !== null) {
                $submission->files()->sync($fileIds);
            }
        });

        $submission->load('deliverable.phase.period', 'project', 'files', 'evaluations');

        return response()->json($this->transformSubmission($submission));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}",
     *     summary="Delete submission",
     *     tags={"Submissions"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deletion confirmation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Submission deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy($academicPeriod, $phase, $deliverable, Submission $submission): JsonResponse
    {
        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully']);
    }

    protected function transformSubmission(Submission $submission): array
    {
        $submission->loadMissing('deliverable.phase.period', 'project', 'files', 'evaluations');

        return [
            'id' => $submission->id,
            'deliverable_id' => $submission->deliverable_id,
            'project_id' => $submission->project_id,
            'submission_date' => optional($submission->submission_date)->toDateTimeString(),
            'deliverable_name' => $submission->deliverable?->name,
            'project_title' => $submission->project?->title,
            'phase_name' => $submission->deliverable?->phase?->name,
            'period_name' => $submission->deliverable?->phase?->period?->name,
            'file_count' => $submission->files->count(),
            'evaluation_count' => $submission->evaluations->count(),
            'created_at' => optional($submission->created_at)->toDateTimeString(),
            'updated_at' => optional($submission->updated_at)->toDateTimeString(),
        ];
    }
    // Detailed evaluation representation removed; use SubmissionEvaluationController endpoints.

    /**
     * @OA\Get(
     *     path="/api/pg/submissions/dropdown",
     *     summary="Get submissions for dropdown",
     *     tags={"Submissions"},
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
     *                 @OA\Property(property="value", type="integer", example=45),
     *                 @OA\Property(property="label", type="string", example="Proyecto Integrador - Entrega Parcial")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $submissions = Submission::with('project', 'deliverable')->orderBy('submission_date', 'desc')->get()->map(function (Submission $submission) {
            $label = $submission->project?->title;
            if ($submission->deliverable?->name) {
                $label .= ' - '.$submission->deliverable->name;
            }

            return [
                'value' => $submission->id,
                'label' => $label,
            ];
        });

        return response()->json($submissions);
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
