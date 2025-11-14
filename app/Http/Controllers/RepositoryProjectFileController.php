<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\RepositoryProject;
use App\Models\RepositoryProjectFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Repository Project Files",
 *     description="Manage files attached to repository projects"
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectFileResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=90),
 *     @OA\Property(property="name", type="string", example="memoria.pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/memoria.pdf"),
 *     @OA\Property(property="repository_project_id", type="integer", example=12),
 *     @OA\Property(property="repository_project_name", type="string", example="Repositorio IoT"),
 *     @OA\Property(property="repository_project_publish_date", type="string", format="date", example="2025-06-01")
 * )
 */
class RepositoryProjectFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/repository-project-files",
     *     summary="Get all repository project files",
     *     tags={"Repository Project Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of all files attached to repository projects",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RepositoryProjectFileResource"))
     *     )
     * )
     */
    public function getAll(): JsonResponse
    {
        $records = RepositoryProjectFile::with(['file', 'repositoryProject'])->orderByDesc('updated_at')->get();

        return response()->json($records->map(fn (RepositoryProjectFile $repositoryProjectFile) => $this->resourceFromPivot($repositoryProjectFile)));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/repository-projects/{repositoryProject}/files",
     *     summary="Get files for a repository project",
     *     tags={"Repository Project Files"},
     *
     *     @OA\Parameter(
     *         name="repositoryProject",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Files linked to the repository project",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RepositoryProjectFileResource"))
     *     ),
     *
     *     @OA\Response(response=404, description="Repository project not found")
     * )
     */
    public function index(RepositoryProject $repositoryProject): JsonResponse
    {
        $files = $repositoryProject->files()->orderByDesc('updated_at')->get();

        return response()->json($files->map(fn (File $file) => $this->resourceFromModels($file, $repositoryProject)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/repository-projects/{repositoryProject}/files",
     *     summary="Upload and relate a file to a repository project",
     *     tags={"Repository Project Files"},
     *
     *     @OA\Parameter(
     *         name="repositoryProject",
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
     *                 required={"file"},
     *
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="name", type="string", example="memoria-final.pdf")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="File uploaded and linked to repository project",
     *
     *         @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFileResource")
     *     ),
     *
     *     @OA\Response(response=404, description="Repository project not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, RepositoryProject $repositoryProject): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $validated['file'];
        $configuredDisk = config('filesystems.default', 'public');
        if (! config()->has('filesystems.disks.'.$configuredDisk)) {
            $configuredDisk = 'public';
        }

        $disk = method_exists(Storage::disk($configuredDisk), 'url') ? $configuredDisk : 'public';
        $directory = Carbon::now()->format('pg/uploads/Y/m/d');
        $path = $uploadedFile->store($directory, $disk);

        /** @var FilesystemAdapter $adapter */
        $adapter = Storage::disk($disk);
        $url = method_exists($adapter, 'url') ? $adapter->url($path) : $adapter->path($path);

        $file = File::create([
            'name' => $validated['name'] ?? $uploadedFile->getClientOriginalName(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'url' => $url,
            'disk' => $disk,
            'path' => $path,
        ]);

        $repositoryProject->files()->attach($file->id);

        return response()->json($this->resourceFromModels($file, $repositoryProject), 201);
    }

    private function resourceFromPivot(RepositoryProjectFile $repositoryProjectFile): array
    {
        $repositoryProject = $repositoryProjectFile->repositoryProject;
        $file = $repositoryProjectFile->file;

        return $this->resourceFromModels($file, $repositoryProject);
    }

    private function resourceFromModels(?File $file, ?RepositoryProject $repositoryProject): array
    {
        return [
            'id' => $file?->id,
            'name' => $file?->name,
            'url' => $file?->url,
            'repository_project_id' => $repositoryProject?->id,
            'repository_project_name' => $repositoryProject?->title,
            'repository_project_publish_date' => optional($repositoryProject?->publish_date)->toDateString(),
        ];
    }
}
