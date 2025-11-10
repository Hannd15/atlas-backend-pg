<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * @OA\Tag(
 *     name="Files",
 *     description="API endpoints for managing files"
 * )
 *
 * @OA\Schema(
 *     schema="FileDeliverableSummary",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=40),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(
 *         property="phase",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Proyecto de grado I"),
 *         @OA\Property(
 *             property="period",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="2025-1")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="StoredFileResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=90),
 *     @OA\Property(property="name", type="string", example="propuesta.pdf"),
 *     @OA\Property(property="extension", type="string", example="pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/propuesta.pdf"),
 *     @OA\Property(property="disk", type="string", example="public"),
 *     @OA\Property(property="path", type="string", example="pg/uploads/2025/01/15/propuesta.pdf"),
 *     @OA\Property(
 *         property="deliverables",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/FileDeliverableSummary")
 *     ),
 *
 *     @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer", example=40)),
 *     @OA\Property(property="submissions", type="array", @OA\Items(type="object", @OA\Property(property="id", type="integer", example=12))),
 *     @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer", example=12)),
 *     @OA\Property(property="repository_projects", type="array", @OA\Items(type="object", @OA\Property(property="id", type="integer", example=3), @OA\Property(property="title", type="string", example="Sistema de seguimiento"))),
 *     @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer", example=3)),
 *     @OA\Property(property="repository_proposals", type="array", @OA\Items(type="object", @OA\Property(property="id", type="integer", example=8), @OA\Property(property="title", type="string", example="Plataforma de aprendizaje"))),
 *     @OA\Property(property="repository_proposal_ids", type="array", @OA\Items(type="integer", example=8)),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class FileController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/files",
     *     summary="Get all files",
     *     tags={"Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of stored files with related deliverables and repository entities",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/StoredFileResource")
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $files = File::with([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'repositoryProposals',
        ])->orderByDesc('updated_at')->get();

        return response()->json($files->map(fn (File $file) => $this->transformFile($file)));
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
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"files"},
     *
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     minItems=1,
     *
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *
     *                 @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer", example=27)),
     *                 @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer", example=12)),
     *                 @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer", example=4)),
     *                 @OA\Property(property="repository_proposal_ids", type="array", @OA\Items(type="integer", example=8))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="File created successfully",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/StoredFileResource")
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
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
            'deliverable_ids' => ['nullable', 'array'],
            'deliverable_ids.*' => ['exists:deliverables,id'],
            'submission_ids' => ['nullable', 'array'],
            'submission_ids.*' => ['exists:submissions,id'],
            'repository_project_ids' => ['nullable', 'array'],
            'repository_project_ids.*' => ['exists:repository_projects,id'],
            'repository_proposal_ids' => ['nullable', 'array'],
            'repository_proposal_ids.*' => ['exists:repository_proposals,id'],
        ]);

        $storedFiles = $this->fileStorageService->storeUploadedFiles($request->file('files'));

        $deliverableIds = collect($validated['deliverable_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $submissionIds = collect($validated['submission_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $repositoryProjectIds = collect($validated['repository_project_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $repositoryProposalIds = collect($validated['repository_proposal_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        $storedFiles->each(function (File $file) use ($deliverableIds, $submissionIds, $repositoryProjectIds, $repositoryProposalIds): void {
            if (! empty($deliverableIds)) {
                $file->deliverables()->syncWithoutDetaching($deliverableIds);
            }

            if (! empty($submissionIds)) {
                $file->submissions()->syncWithoutDetaching($submissionIds);
            }

            if (! empty($repositoryProjectIds)) {
                $file->repositoryProjects()->syncWithoutDetaching($repositoryProjectIds);
            }

            if (! empty($repositoryProposalIds)) {
                $file->repositoryProposals()->syncWithoutDetaching($repositoryProposalIds);
            }
        });

        return response()->json($storedFiles->map(fn (File $file) => $this->transformFile($file)), 201);
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
     *         description="File details with related deliverables and repository entities",
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoredFileResource")
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

        return response()->json($this->transformFile($file));
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
     *         description="File updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoredFileResource")
     *     )
     * )
     */
    public function update(Request $request, File $file): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'extension' => ['sometimes', 'string', 'max:10'],
            'deliverable_ids' => ['nullable', 'array'],
            'deliverable_ids.*' => ['exists:deliverables,id'],
            'submission_ids' => ['nullable', 'array'],
            'submission_ids.*' => ['exists:submissions,id'],
            'repository_project_ids' => ['nullable', 'array'],
            'repository_project_ids.*' => ['exists:repository_projects,id'],
            'repository_proposal_ids' => ['nullable', 'array'],
            'repository_proposal_ids.*' => ['exists:repository_proposals,id'],
        ]);

        $file->update(Arr::only($validated, ['name', 'extension']));

        $this->syncRelationships($file, $validated);

        $file->load([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'repositoryProposals',
        ]);

        return response()->json($this->transformFile($file));
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
        $files = File::orderByDesc('updated_at')->get()->map(fn (File $file) => [
            'value' => $file->id,
            'label' => $file->name,
        ]);

        return response()->json($files);
    }

    protected function transformFile(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
            'disk' => $file->disk,
            'path' => $file->path,
            'deliverables' => $file->deliverables->map(function ($deliverable) {
                return [
                    'id' => $deliverable->id,
                    'name' => $deliverable->name,
                    'phase' => $deliverable->phase ? [
                        'id' => $deliverable->phase->id,
                        'name' => $deliverable->phase->name,
                        'period' => $deliverable->phase->period ? [
                            'id' => $deliverable->phase->period->id,
                            'name' => $deliverable->phase->period->name,
                        ] : null,
                    ] : null,
                ];
            })->values(),
            'deliverable_ids' => $file->deliverables->pluck('id')->values(),
            'submissions' => $file->submissions->map(fn ($submission) => [
                'id' => $submission->id,
            ])->values(),
            'submission_ids' => $file->submissions->pluck('id')->values(),
            'repository_projects' => $file->repositoryProjects->map(fn ($repositoryProject) => [
                'id' => $repositoryProject->id,
                'title' => $repositoryProject->title,
            ])->values(),
            'repository_project_ids' => $file->repositoryProjects->pluck('id')->values(),
            'repository_proposals' => $file->repositoryProposals->map(fn ($repositoryProposal) => [
                'id' => $repositoryProposal->id,
                'title' => $repositoryProposal->title,
            ])->values(),
            'repository_proposal_ids' => $file->repositoryProposals->pluck('id')->values(),
            'created_at' => optional($file->created_at)->toDateTimeString(),
            'updated_at' => optional($file->updated_at)->toDateTimeString(),
        ];
    }

    protected function syncRelationships(File $file, array $payload): void
    {
        if (array_key_exists('deliverable_ids', $payload)) {
            $file->deliverables()->sync(collect($payload['deliverable_ids'])->map(fn ($id) => (int) $id)->all());
        }

        if (array_key_exists('submission_ids', $payload)) {
            $file->submissions()->sync(collect($payload['submission_ids'])->map(fn ($id) => (int) $id)->all());
        }

        if (array_key_exists('repository_project_ids', $payload)) {
            $file->repositoryProjects()->sync(collect($payload['repository_project_ids'])->map(fn ($id) => (int) $id)->all());
        }

        if (array_key_exists('repository_proposal_ids', $payload)) {
            $file->repositoryProposals()->sync(collect($payload['repository_proposal_ids'])->map(fn ($id) => (int) $id)->all());
        }
    }
}
