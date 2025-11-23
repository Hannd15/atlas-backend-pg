<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Submission Evaluations",
 *     description="Endpoints to manage evaluations per submission"
 * )
 *
 *     @OA\Schema(
 *     schema="SubmissionEvaluationResource",
 *     type="object",
 *     description="Minimal evaluation representation without embedded relationship objects.",
 *
 *     @OA\Property(property="id", type="integer", example=90),
 *     @OA\Property(property="submission_id", type="integer", example=45),
 *     @OA\Property(property="user_id", type="integer", example=15),
 *     @OA\Property(property="evaluator_id", type="integer", example=2),
 *     @OA\Property(property="rubric_id", type="integer", example=5),
 *     @OA\Property(property="grade", type="number", format="float", example=4.5),
 *     @OA\Property(property="evaluation_date", type="string", format="date-time", example="2025-04-15T17:45:00"),
 *     @OA\Property(property="user_name", type="string", nullable=true, example="Laura Pérez"),
 *     @OA\Property(property="evaluator_name", type="string", nullable=true, example="Ing. Carlos"),
 *     @OA\Property(property="rubric_name", type="string", nullable=true, example="Calidad técnica"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionEvaluationCreatePayload",
 *     type="object",
 *     required={"user_id","rubric_id","grade","evaluator_id"},
 *
 *     @OA\Property(property="user_id", type="integer", example=15),
 *     @OA\Property(property="rubric_id", type="integer", example=5),
 *     @OA\Property(property="grade", type="number", format="float", example=4.2),
 *     @OA\Property(property="evaluation_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="evaluator_id", type="integer", example=2)
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionEvaluationUpdatePayload",
 *     type="object",
 *
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="rubric_id", type="integer"),
 *     @OA\Property(property="grade", type="number", format="float"),
 *     @OA\Property(property="evaluation_date", type="string", format="date-time"),
 *     @OA\Property(property="evaluator_id", type="integer")
 * )
 */
class SubmissionEvaluationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}/evaluations",
     *     summary="List evaluations for a submission",
     *     tags={"Submission Evaluations"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of evaluations",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/SubmissionEvaluationResource")
     *         )
     *     )
     * )
     */
    public function index($academicPeriod, $phase, $deliverable, Submission $submission): JsonResponse
    {
        $submission->load('evaluations.user', 'evaluations.evaluator', 'evaluations.rubric');

        return response()->json(
            $submission->evaluations
                ->map(fn (Evaluation $evaluation) => $this->transformEvaluation($evaluation))
                ->values()
                ->all()
        );
    }

    /**
     * @OA\Post(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}/evaluations",
     *     summary="Create evaluation",
     *     tags={"Submission Evaluations"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SubmissionEvaluationCreatePayload")),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Evaluation resource",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionEvaluationResource")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $academicPeriod, $phase, $deliverable, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'rubric_id' => 'required|exists:rubrics,id',
            'grade' => 'required|numeric',
            'evaluation_date' => 'nullable|date',
            'evaluator_id' => 'required|exists:users,id',
        ]);

        $validated['evaluation_date'] = $validated['evaluation_date'] ?? now();

        $evaluation = $submission->evaluations()->create($validated);
        $evaluation->load('user', 'evaluator', 'rubric');

        return response()->json($this->transformEvaluation($evaluation), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}/evaluations/{evaluation}",
     *     summary="Show evaluation",
     *     tags={"Submission Evaluations"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="evaluation", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation resource",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionEvaluationResource")
     *     )
     * )
     */
    public function show($academicPeriod, $phase, $deliverable, Submission $submission, Evaluation $evaluation): JsonResponse
    {
        $evaluation->load('user', 'evaluator', 'rubric');

        return response()->json($this->transformEvaluation($evaluation));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}/evaluations/{evaluation}",
     *     summary="Update evaluation",
     *     tags={"Submission Evaluations"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="evaluation", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/SubmissionEvaluationUpdatePayload")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Updated evaluation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionEvaluationResource")
     *     )
     * )
     */
    public function update(Request $request, $academicPeriod, $phase, $deliverable, Submission $submission, Evaluation $evaluation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'rubric_id' => 'sometimes|required|exists:rubrics,id',
            'grade' => 'sometimes|required|numeric',
            'evaluation_date' => 'sometimes|required|date',
            'evaluator_id' => 'sometimes|required|exists:users,id',
        ]);

        $evaluation->update($validated);
        $evaluation->load('user', 'evaluator', 'rubric');

        return response()->json($this->transformEvaluation($evaluation));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/submissions/{submission}/evaluations/{evaluation}",
     *     summary="Delete evaluation",
     *     tags={"Submission Evaluations"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="evaluation", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deletion confirmation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Evaluation deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy($academicPeriod, $phase, $deliverable, Submission $submission, Evaluation $evaluation): JsonResponse
    {
        $evaluation->delete();

        return response()->json(['message' => 'Evaluation deleted successfully']);
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
            'evaluation_date' => optional($evaluation->evaluation_date)->toDateTimeString(),
            'user_name' => $evaluation->user?->name,
            'evaluator_name' => $evaluation->evaluator?->name,
            'rubric_name' => $evaluation->rubric?->name,
            'created_at' => optional($evaluation->created_at)->toDateTimeString(),
            'updated_at' => optional($evaluation->updated_at)->toDateTimeString(),
        ];
    }
}
