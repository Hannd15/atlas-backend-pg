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
 *     schema="FileResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=90),
 *     @OA\Property(property="name", type="string", example="propuesta.pdf"),
 *     @OA\Property(property="extension", type="string", example="pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/propuesta.pdf")
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
 *     @OA\Property(property="deliverable_ids", type="array", @OA\Items(type="integer", example=40)),
 *     @OA\Property(property="submission_ids", type="array", @OA\Items(type="integer", example=12)),
 *     @OA\Property(property="repository_project_ids", type="array", @OA\Items(type="integer", example=3)),
 *     @OA\Property(property="proposal_ids", type="array", @OA\Items(type="integer", example=8)),
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
     *         description="List of files",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/FileResource")
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $files = File::orderByDesc('updated_at')->get();

        return response()->json($files->map(fn (File $file) => [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
        ]));
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
            'proposals',
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
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="extension", type="string"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
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
            'file' => ['sometimes', 'nullable', 'file'],
        ]);

        // Update basic file properties
        $file->update(Arr::only($validated, ['name', 'extension']));

        // Handle file upload if provided
        if ($request->hasFile('file') && $request->file('file') instanceof \Illuminate\Http\UploadedFile) {
            $uploadedFile = $request->file('file');

            $configuredDisk = config('filesystems.default', 'public');
            if (! config()->has('filesystems.disks.'.$configuredDisk)) {
                $configuredDisk = 'public';
            }

            $disk = method_exists(\Illuminate\Support\Facades\Storage::disk($configuredDisk), 'url') ? $configuredDisk : 'public';
            $directory = \Illuminate\Support\Carbon::now()->format('pg/uploads/Y/m/d');
            $path = $uploadedFile->store($directory, $disk);

            if ($file->path && $file->disk) {
                \Illuminate\Support\Facades\Storage::disk($file->disk)->delete($file->path);
            }

            /** @var \Illuminate\Filesystem\FilesystemAdapter $adapter */
            $adapter = \Illuminate\Support\Facades\Storage::disk($disk);
            $url = method_exists($adapter, 'url') ? $adapter->url($path) : $adapter->path($path);

            $file->update([
                'disk' => $disk,
                'path' => $path,
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'url' => $url,
            ]);
        }

        $file->load([
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'proposals',
        ]);

        return response()->json($this->transformFile($file));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/files/{id}/download",
     *     summary="Download a file",
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
     *         description="File content streamed for download"
     *     ),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function download(File $file): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! $file->path || ! $file->disk) {
            abort(404, 'File not stored properly.');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk($file->disk);
        $path = $disk->path($file->path);

        return response()->download($path, $file->name);
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
     *         response=204,
     *         description="File deleted successfully"
     *     )
     * )
     */
    public function destroy(File $file): \Illuminate\Http\Response
    {
        // Delete the physical file if it exists
        if ($file->path && $file->disk) {
            \Illuminate\Support\Facades\Storage::disk($file->disk)->delete($file->path);
        }

        $file->delete();

        return response()->noContent();
    }

    /**
     * @OA\Get(
     *     path="/api/pg/files/dropdown",
     *     summary="Get files for dropdown",
     *     tags={"Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of files formatted for dropdowns",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="value", type="integer", example=90),
     *                 @OA\Property(property="label", type="string", example="propuesta.pdf")
     *             )
     *         )
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
            'deliverable_ids' => $file->deliverables->pluck('id')->values(),
            'submission_ids' => $file->submissions->pluck('id')->values(),
            'repository_project_ids' => $file->repositoryProjects->pluck('id')->values(),
            'proposal_ids' => $file->proposals->pluck('id')->values(),
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

        if (array_key_exists('proposal_ids', $payload)) {
            $file->proposals()->sync(collect($payload['proposal_ids'])->map(fn ($id) => (int) $id)->all());
        }
    }
}
