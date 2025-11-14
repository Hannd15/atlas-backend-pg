<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\File;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Deliverables",
 *     description="API endpoints for managing deliverables"
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
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
 *             @OA\Property(property="id", type="integer", example=2),
 *             @OA\Property(property="name", type="string", example="2025-1")
 *         )
 *     ),
 *     @OA\Property(
 *         property="files",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/DeliverableFileResource")
 *     ),
 *
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer", example=12)),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T18:30:00")
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableCreatePayload",
 *     type="object",
 *     required={"phase_id","name"},
 *
 *     @OA\Property(property="phase_id", type="integer", example=5),
 *     @OA\Property(property="name", type="string", example="Entrega 1"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Documento PDF con la propuesta"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2025-03-15T23:59:00"),
 *     @OA\Property(property="file_ids", type="array", nullable=true, @OA\Items(type="integer", example=42)),
 *     @OA\Property(property="files", type="array", nullable=true, @OA\Items(type="string", format="binary")),
 *     @OA\Property(property="rubric_ids", type="array", nullable=true, @OA\Items(type="integer", example=7))
 * )
 */
class DeliverableController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables",
     *     summary="Get all deliverables",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables with phase and file relationships",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/DeliverableResource")
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::with(['phase.period', 'files', 'rubrics'])->orderByDesc('updated_at')->get();

        return response()->json($deliverables->map(fn (Deliverable $deliverable) => $this->transformDeliverable($deliverable)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverables",
     *     summary="Create a deliverable",
     *     tags={"Deliverables"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(ref="#/components/schemas/DeliverableCreatePayload")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
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
        $validator = Validator::make($request->all(), [
            'phase_id' => 'required|exists:phases,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
            'files' => 'nullable|array',
            'files.*' => 'file',
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deliverable = $this->persistDeliverable($validator->validated(), Arr::wrap($request->file('files', [])));

        return response()->json($this->transformDeliverable($deliverable), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Get a specific deliverable",
     *     tags={"Deliverables"},
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
     *         description="Deliverable details with phase and file metadata",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
     *     )
     * )
     */
    public function show(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->load('phase.period', 'files', 'rubrics');

        return response()->json($this->transformDeliverable($deliverable));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Update a deliverable",
     *     tags={"Deliverables"},
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
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="phase_id", type="integer", nullable=true),
     *                 @OA\Property(property="name", type="string", nullable=true),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="due_date", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="file_ids", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableResource")
     *     )
     * )
     */
    public function update(Request $request, Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'phase_id' => 'sometimes|required|exists:phases,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
            'files' => 'nullable|array',
            'files.*' => 'file',
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        $deliverable->update($request->only('phase_id', 'name', 'description', 'due_date'));

        $shouldSyncFiles = $request->has('file_ids') || $request->hasFile('files');

        if ($shouldSyncFiles) {
            $deliverable->load('files');

            $fileIds = collect($request->input('file_ids', []))
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($request->hasFile('files')) {
                $storedFiles = $this->fileStorageService->storeUploadedFiles(Arr::wrap($request->file('files', [])));
                $fileIds = $fileIds->merge($storedFiles->pluck('id'));
            }

            $finalFileIds = $fileIds->unique()->values();
            $filesToRemove = $deliverable->files->filter(fn ($file) => ! $finalFileIds->contains($file->id));

            $deliverable->files()->sync($finalFileIds->all());

            foreach ($filesToRemove as $file) {
                $file->loadCount(['deliverables', 'submissions', 'repositoryProjects', 'proposals']);

                if ($this->shouldDeleteFile($file)) {
                    $file->delete();
                }
            }
        }

        if ($request->has('rubric_ids') && $request->input('rubric_ids') !== null) {
            $deliverable->rubrics()->sync($this->resolveRubricIds($request->input('rubric_ids'))->all());
        }

        $deliverable->load('phase.period', 'files', 'rubrics');

        return response()->json($this->transformDeliverable($deliverable));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/deliverables/{id}",
     *     summary="Delete a deliverable",
     *     tags={"Deliverables"},
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
     *         description="Deliverable deleted successfully"
     *     )
     * )
     */
    public function destroy(Deliverable $deliverable): \Illuminate\Http\JsonResponse
    {
        $deliverable->delete();

        return response()->json(['message' => 'Deliverable deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/dropdown",
     *     summary="Get deliverables for dropdown",
     *     tags={"Deliverables"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverables formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $deliverables = Deliverable::orderByDesc('updated_at')->get()->map(fn (Deliverable $deliverable) => [
            'value' => $deliverable->id,
            'label' => $deliverable->name,
        ]);

        return response()->json($deliverables);
    }

    protected function persistDeliverable(array $data, array $uploadedFiles = []): Deliverable
    {
        $attributes = Arr::only($data, ['phase_id', 'name', 'description', 'due_date']);

        $deliverable = Deliverable::create([
            'phase_id' => $attributes['phase_id'],
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'due_date' => $attributes['due_date'] ?? null,
        ]);

        $fileIds = $this->resolveFileIds($data['file_ids'] ?? [], $uploadedFiles);

        if ($fileIds->isNotEmpty()) {
            $deliverable->files()->sync($fileIds->unique()->all());
        }

        if (array_key_exists('rubric_ids', $data) && $data['rubric_ids'] !== null) {
            $deliverable->rubrics()->sync($this->resolveRubricIds($data['rubric_ids'])->all());
        }

        return $deliverable->load('phase.period', 'files', 'rubrics');
    }

    protected function resolveFileIds(array $fileIds, array $uploadedFiles = []): \Illuminate\Support\Collection
    {
        $existing = collect($fileIds)->filter()->map(fn ($id) => (int) $id);
        $stored = $this->fileStorageService->storeUploadedFiles($uploadedFiles);

        return $existing->merge($stored->pluck('id'));
    }

    protected function resolveRubricIds(array $rubricIds): \Illuminate\Support\Collection
    {
        return collect($rubricIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function shouldDeleteFile(File $file): bool
    {
        return ($file->deliverables_count ?? 0) === 0
            && ($file->submissions_count ?? 0) === 0
            && ($file->repository_projects_count ?? 0) === 0
            && ($file->proposals_count ?? 0) === 0;
    }

    protected function transformDeliverable(Deliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
            'phase' => $deliverable->phase ? [
                'id' => $deliverable->phase->id,
                'name' => $deliverable->phase->name,
                'period' => $deliverable->phase->period ? [
                    'id' => $deliverable->phase->period->id,
                    'name' => $deliverable->phase->period->name,
                ] : null,
            ] : null,
            'files' => $deliverable->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'extension' => $file->extension,
                'url' => $file->url,
            ])->values()->all(),
            'file_ids' => $deliverable->files->pluck('id')->values()->all(),
            'rubrics' => $deliverable->rubrics->map(fn ($rubric) => [
                'id' => $rubric->id,
                'name' => $rubric->name,
                'description' => $rubric->description,
                'min_value' => $rubric->min_value,
                'max_value' => $rubric->max_value,
            ])->values()->all(),
            'rubric_ids' => $deliverable->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $deliverable->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($deliverable->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverable->updated_at)->toDateTimeString(),
        ];
    }
}
