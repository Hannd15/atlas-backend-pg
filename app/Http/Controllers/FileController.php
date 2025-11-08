<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Files",
 *     description="API endpoints for managing files"
 * )
 */
class FileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/files",
     *     summary="Get all files",
     *     tags={"Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of files with deliverable, submission, repository project, and repository proposal names"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $files = File::with([
            'deliverables',
            'submissions',
            'repositoryProjects',
            'repositoryProposals',
        ])->orderBy('updated_at', 'desc')->get();

        $files->each(function ($file) {
            $file->deliverable_names = $file->deliverables->pluck('name')->implode(', ');
            $file->submission_names = $file->submissions->map(function ($submission) {
                return "Submission #{$submission->id}";
            })->implode(', ');
            $file->repository_project_names = $file->repositoryProjects->pluck('title')->implode(', ');
            $file->repository_proposal_names = $file->repositoryProposals->pluck('title')->implode(', ');
        });

        return response()->json($files);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/files",
     *     summary="Create a new file",
     *     tags={"Files"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name","extension","url"},
     *
     *             @OA\Property(property="name", type="string", example="document.pdf"),
     *             @OA\Property(property="extension", type="string", example="pdf"),
     *             @OA\Property(property="url", type="string", example="https://example.com/files/document.pdf"),
     *             @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_proposal_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="File created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="extension", type="string"),
     *             @OA\Property(property="url", type="string"),
     *             @OA\Property(property="academic_period_name", type="string", nullable=true),
     *             @OA\Property(property="deliverable_name", type="string", nullable=true),
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
            'extension' => 'required|string|max:10',
            'url' => 'required|url',
            'deliverable_ids' => 'nullable|array',
            'deliverable_ids.*' => 'exists:deliverables,id',
            'submission_ids' => 'nullable|array',
            'submission_ids.*' => 'exists:submissions,id',
            'repository_project_ids' => 'nullable|array',
            'repository_project_ids.*' => 'exists:repository_projects,id',
            'repository_proposal_ids' => 'nullable|array',
            'repository_proposal_ids.*' => 'exists:repository_proposals,id',
        ]);

        $file = File::create($request->only('name', 'extension', 'url'));

        if ($request->has('deliverable_ids')) {
            $file->deliverables()->sync($request->input('deliverable_ids'));
        }

        if ($request->has('submission_ids')) {
            $file->submissions()->sync($request->input('submission_ids'));
        }

        if ($request->has('repository_project_ids')) {
            $file->repositoryProjects()->sync($request->input('repository_project_ids'));
        }

        if ($request->has('repository_proposal_ids')) {
            $file->repositoryProposals()->sync($request->input('repository_proposal_ids'));
        }

        // Load relationships and add academic_period_name and deliverable_name
        $file = $this->enrichFileWithRelationshipData($file);

        return response()->json($file, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/files/{id}",
     *     summary="Get a specific file",
     *     tags={"Files"},
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
     *         description="File details with relation IDs, academic period name, and deliverable name",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="extension", type="string"),
     *             @OA\Property(property="url", type="string"),
     *             @OA\Property(property="academic_period_name", type="string", nullable=true),
     *             @OA\Property(property="deliverable_name", type="string", nullable=true),
     *             @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_proposal_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function show(File $file): \Illuminate\Http\JsonResponse
    {
        $file->load([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'repositoryProposals',
        ]);

        // Add relationship IDs as arrays
        $file->deliverable_ids = $file->deliverables->pluck('id');
        $file->submission_ids = $file->submissions->pluck('id');
        $file->repository_project_ids = $file->repositoryProjects->pluck('id');
        $file->repository_proposal_ids = $file->repositoryProposals->pluck('id');

        // Add academic_period_name and deliverable_name
        $file = $this->enrichFileWithRelationshipData($file);

        return response()->json($file);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/files/{id}",
     *     summary="Update a file",
     *     tags={"Files"},
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
     *             @OA\Property(property="extension", type="string"),
     *             @OA\Property(property="url", type="string"),
     *             @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="repository_proposal_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="File updated successfully"
     *     )
     * )
     */
    public function update(Request $request, File $file): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'extension' => 'sometimes|required|string|max:10',
            'url' => 'sometimes|required|url',
            'deliverable_ids' => 'nullable|array',
            'deliverable_ids.*' => 'exists:deliverables,id',
            'submission_ids' => 'nullable|array',
            'submission_ids.*' => 'exists:submissions,id',
            'repository_project_ids' => 'nullable|array',
            'repository_project_ids.*' => 'exists:repository_projects,id',
            'repository_proposal_ids' => 'nullable|array',
            'repository_proposal_ids.*' => 'exists:repository_proposals,id',
        ]);

        $file->update($request->only('name', 'extension', 'url'));

        if ($request->has('deliverable_ids')) {
            $file->deliverables()->sync($request->input('deliverable_ids'));
        }

        if ($request->has('submission_ids')) {
            $file->submissions()->sync($request->input('submission_ids'));
        }

        if ($request->has('repository_project_ids')) {
            $file->repositoryProjects()->sync($request->input('repository_project_ids'));
        }

        if ($request->has('repository_proposal_ids')) {
            $file->repositoryProposals()->sync($request->input('repository_proposal_ids'));
        }

        // Reload and enrich with relationship data
        $file = $this->enrichFileWithRelationshipData($file);

        return response()->json($file);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/files/{id}",
     *     summary="Delete a file",
     *     tags={"Files"},
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
     *         description="File deleted successfully"
     *     )
     * )
     */
    public function destroy(File $file): \Illuminate\Http\JsonResponse
    {
        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/files/dropdown",
     *     summary="Get files for dropdown",
     *     tags={"Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of files formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $files = File::all()->map(function ($file) {
            return [
                'value' => $file->id,
                'label' => $file->name,
            ];
        });

        return response()->json($files);
    }

    /**
     * Enrich file with academic_period_name and deliverable_name
     */
    protected function enrichFileWithRelationshipData(File $file): File
    {
        $file->load('deliverables.phase.period');

        // Get the first deliverable's academic period and deliverable name (if exists)
        $firstDeliverable = $file->deliverables->first();

        if ($firstDeliverable) {
            $file->deliverable_name = $firstDeliverable->name;
            $file->academic_period_name = $firstDeliverable->phase && $firstDeliverable->phase->period
                ? $firstDeliverable->phase->period->name
                : null;
        } else {
            $file->deliverable_name = null;
            $file->academic_period_name = null;
        }

        return $file;
    }
}
