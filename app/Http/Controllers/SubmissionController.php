<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Submissions",
 *     description="API endpoints for project deliverable submissions"
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=45),
 *     @OA\Property(property="deliverable_id", type="integer", example=12),
 *     @OA\Property(property="project_id", type="integer", example=30),
 *     @OA\Property(property="submission_date", type="string", format="date-time", example="2025-04-19T10:00:00"),
 *     @OA\Property(
 *         property="deliverable",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(
 *             property="phase",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(
 *                 property="period",
 *                 type="object",
 *                 nullable=true,
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Property(
 *         property="project",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(
 *             property="status",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="files",
 *         type="array",
 *
 *         @OA\Items(
 *             type="object",
 *
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="extension", type="string"),
 *             @OA\Property(property="url", type="string")
 *         )
 *     ),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(
 *         property="evaluations",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/SubmissionEvaluationResource")
 *     ),
 *
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
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(
 *         property="evaluations",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/SubmissionEvaluationCreatePayload")
 *     )
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
     *     path="/api/pg/submissions",
     *     summary="List submissions",
     *     tags={"Submissions"},
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
    public function index(): JsonResponse
    {
        $submissions = Submission::with([
            'deliverable.phase.period',
            'project.status',
            'files',
            'evaluations.user',
            'evaluations.evaluator',
            'evaluations.rubric',
        ])->orderByDesc('updated_at')->get();

        return response()->json(
            $submissions->map(fn (Submission $submission) => $this->transformSubmission($submission))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'deliverable_id' => 'required|exists:deliverables,id',
            'project_id' => 'required|exists:projects,id',
            'submission_date' => 'required|date',
            'file_ids' => 'sometimes|array',
            'file_ids.*' => 'integer|exists:files,id',
            'evaluations' => 'sometimes|array',
            'evaluations.*.user_id' => 'required|exists:users,id',
            'evaluations.*.rubric_id' => 'required|exists:rubrics,id',
            'evaluations.*.grade' => 'required|numeric',
            'evaluations.*.comments' => 'nullable|string',
            'evaluations.*.evaluation_date' => 'nullable|date',
            'evaluations.*.evaluator_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $fileIds = $this->normalizeIds($data['file_ids'] ?? null);
        $evaluationsPayload = $data['evaluations'] ?? null;

        unset($data['file_ids'], $data['evaluations']);

        $submission = DB::transaction(function () use ($data, $fileIds, $evaluationsPayload) {
            $submission = Submission::create($data);

            if ($fileIds !== null) {
                $submission->files()->sync($fileIds);
            }

            if ($evaluationsPayload !== null) {
                $submission->evaluations()->createMany(
                    collect($evaluationsPayload)
                        ->map(function (array $evaluation) {
                            $evaluation['evaluation_date'] = $evaluation['evaluation_date'] ?? now();

                            return $evaluation;
                        })
                        ->all()
                );
            }

            return $submission;
        });

        $submission->load('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        return response()->json($this->transformSubmission($submission), 201);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/submissions",
     *     summary="Create a submission",
     *     tags={"Submissions"},
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
    public function show(Submission $submission): JsonResponse
    {
        $submission->load('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        return response()->json($this->transformSubmission($submission));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/submissions/{submission}",
     *     summary="Show submission",
     *     tags={"Submissions"},
     *
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
    public function update(Request $request, Submission $submission): JsonResponse
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

        $submission->load('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        return response()->json($this->transformSubmission($submission));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/submissions/{submission}",
     *     summary="Update submission",
     *     tags={"Submissions"},
     *
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
    public function destroy(Submission $submission): JsonResponse
    {
        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/submissions/{submission}",
     *     summary="Delete submission",
     *     tags={"Submissions"},
     *
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
    protected function transformSubmission(Submission $submission): array
    {
        $submission->loadMissing('deliverable.phase.period', 'project.status', 'files', 'evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        return [
            'id' => $submission->id,
            'deliverable' => $submission->deliverable ? [
                'id' => $submission->deliverable->id,
                'name' => $submission->deliverable->name,
                'description' => $submission->deliverable->description,
                'phase' => $submission->deliverable->phase ? [
                    'id' => $submission->deliverable->phase->id,
                    'name' => $submission->deliverable->phase->name,
                    'period' => $submission->deliverable->phase->period ? [
                        'id' => $submission->deliverable->phase->period->id,
                        'name' => $submission->deliverable->phase->period->name,
                    ] : null,
                ] : null,
            ] : null,
            'project' => $submission->project ? [
                'id' => $submission->project->id,
                'title' => $submission->project->title,
                'status' => $submission->project->status ? [
                    'id' => $submission->project->status->id,
                    'name' => $submission->project->status->name,
                ] : null,
            ] : null,
            'deliverable_id' => $submission->deliverable_id,
            'project_id' => $submission->project_id,
            'submission_date' => optional($submission->submission_date)->toDateTimeString(),
            'files' => $submission->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'extension' => $file->extension,
                'url' => $file->url,
            ])->values()->all(),
            'file_ids' => $submission->files->pluck('id')->values()->all(),
            'evaluations' => $submission->evaluations
                ->map(fn (Evaluation $evaluation) => $this->transformEvaluation($evaluation))
                ->values()
                ->all(),
            'created_at' => optional($submission->created_at)->toDateTimeString(),
            'updated_at' => optional($submission->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformEvaluation(Evaluation $evaluation): array
    {
        $evaluation->loadMissing('user', 'evaluator', 'rubric');

        return [
            'id' => $evaluation->id,
            'submission_id' => $evaluation->submission_id,
            'user_id' => $evaluation->user_id,
            'evaluator_id' => $evaluation->evaluator_id,
            'rubric_id' => $evaluation->rubric_id,
            'grade' => $evaluation->grade,
            'comments' => $evaluation->comments,
            'evaluation_date' => optional($evaluation->evaluation_date)->toDateTimeString(),
            'user' => $evaluation->user ? [
                'id' => $evaluation->user->id,
                'name' => $evaluation->user->name,
                'email' => $evaluation->user->email,
            ] : null,
            'evaluator' => $evaluation->evaluator ? [
                'id' => $evaluation->evaluator->id,
                'name' => $evaluation->evaluator->name,
                'email' => $evaluation->evaluator->email,
            ] : null,
            'rubric' => $evaluation->rubric ? [
                'id' => $evaluation->rubric->id,
                'name' => $evaluation->rubric->name,
                'min_value' => $evaluation->rubric->min_value,
                'max_value' => $evaluation->rubric->max_value,
            ] : null,
            'created_at' => optional($evaluation->created_at)->toDateTimeString(),
            'updated_at' => optional($evaluation->updated_at)->toDateTimeString(),
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
