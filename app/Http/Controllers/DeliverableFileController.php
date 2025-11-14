<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Deliverable Files",
 *     description="API endpoints for uploading and listing files associated with deliverables"
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableFileResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=90),
 *     @OA\Property(property="deliverable_id", type="integer", example=27),
 *     @OA\Property(property="name", type="string", example="propuesta.pdf"),
 *     @OA\Property(property="extension", type="string", example="pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/propuesta.pdf"),
 *     @OA\Property(property="disk", type="string", example="public"),
 *     @OA\Property(property="path", type="string", example="pg/uploads/2025/01/15/propuesta.pdf"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class DeliverableFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/deliverable-files",
     *     summary="Get all deliverable files with related information",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of all deliverable files with deliverable, phase, and period names",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=90),
     *                 @OA\Property(property="file_name", type="string", example="propuesta.pdf"),
     *                 @OA\Property(property="deliverable_name", type="string", example="Entrega 1"),
     *                 @OA\Property(property="phase_name", type="string", example="Proyecto de grado I"),
     *                 @OA\Property(property="period_name", type="string", example="2025-1")
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(): \Illuminate\Http\JsonResponse
    {
        $files = \App\Models\DeliverableFile::with([
            'file',
            'deliverable.phase.period',
        ])->get();

        $result = $files->map(fn ($deliverableFile) => [
            'id' => $deliverableFile->file_id,
            'file_name' => $deliverableFile->file->name,
            'deliverable_name' => $deliverableFile->deliverable->name,
            'phase_name' => $deliverableFile->deliverable->phase->name,
            'period_name' => $deliverableFile->deliverable->phase->period->name,
        ]);

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverables/{deliverable_id}/files",
     *     summary="Get all files associated with a deliverable",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of files associated with the deliverable",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/DeliverableFileResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Deliverable not found"
     *     )
     * )
     */
    public function index(int $deliverableId): \Illuminate\Http\JsonResponse
    {
        $deliverable = \App\Models\Deliverable::findOrFail($deliverableId);
        $files = $deliverable->files()->orderByDesc('updated_at')->get();

        return response()->json($files->map(fn (File $file) => $this->transformFileForDeliverable($file, $deliverableId)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverables/{deliverable_id}/files",
     *     summary="Upload and associate a file with a deliverable",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
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
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="File uploaded and associated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableFileResource")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request, int $deliverableId): \Illuminate\Http\JsonResponse
    {
        $deliverable = \App\Models\Deliverable::findOrFail($deliverableId);

        $validated = $request->validate([
            'file' => 'required|file',
            'name' => 'sometimes|nullable|string|max:255',
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

        $name = $validated['name'] ?? $uploadedFile->getClientOriginalName();

        $file = File::create([
            'name' => $name,
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'url' => $url,
            'disk' => $disk,
            'path' => $path,
        ]);

        $deliverable->files()->attach($file->id);

        return response()->json($this->transformFileForDeliverable($file, $deliverableId), 201);
    }

    /**
     * Transform a file for deliverable response.
     */
    private function transformFileForDeliverable(File $file, int $deliverableId): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
            'disk' => $file->disk,
            'path' => $file->path,
            'deliverable_id' => $deliverableId,
            'created_at' => $file->created_at,
            'updated_at' => $file->updated_at,
        ];
    }
}
