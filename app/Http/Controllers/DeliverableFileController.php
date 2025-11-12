<?php

namespace App\Http\Controllers;

use App\Models\DeliverableFile;
use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Deliverable Files",
 *     description="API endpoints for managing deliverable-file associations"
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableFileResource",
 *     type="object",
 *
 *     @OA\Property(property="deliverable_id", type="integer", example=27),
 *     @OA\Property(property="file_id", type="integer", example=90),
 *     @OA\Property(property="deliverable_name", type="string", example="Entrega 1"),
 *     @OA\Property(property="phase_name", type="string", example="Proyecto de grado I"),
 *     @OA\Property(property="academic_period_name", type="string", example="2025-1"),
 *     @OA\Property(property="file_name", type="string", example="propuesta.pdf"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class DeliverableFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/deliverable-files",
     *     summary="Get all deliverable-file associations",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of deliverable-file associations with related names",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/DeliverableFileResource")
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $deliverableFiles = DeliverableFile::with(['deliverable.phase.period', 'file'])->orderByDesc('updated_at')->get();

        return response()->json($deliverableFiles->map(fn (DeliverableFile $deliverableFile) => $this->transformDeliverableFile($deliverableFile)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/deliverable-files",
     *     summary="Create a new deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"deliverable_id","file_id"},
     *
     *             @OA\Property(property="deliverable_id", type="integer", example=1),
     *             @OA\Property(property="file_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable-file association created successfully",
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
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'deliverable_id' => 'required|exists:deliverables,id',
            'file_id' => 'required|exists:files,id',
        ]);

        $deliverableFile = DeliverableFile::create($request->only('deliverable_id', 'file_id'));

        $deliverableFile->load('deliverable.phase.period', 'file');

        return response()->json($this->transformDeliverableFile($deliverableFile), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/deliverable-files/{deliverable_id}/{file_id}",
     *     summary="Get a specific deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="file_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable-file association details with related names",
     *
     *         @OA\JsonContent(ref="#/components/schemas/DeliverableFileResource")
     *     )
     * )
     */
    public function show(int $deliverableId, int $fileId): \Illuminate\Http\JsonResponse
    {
        $deliverableFile = DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->firstOrFail();

        $deliverableFile->load('deliverable.phase.period', 'file');

        return response()->json($this->transformDeliverableFile($deliverableFile));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/deliverable-files/{deliverable_id}/{file_id}",
     *     summary="Update a deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="file_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable-file association updated successfully",
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
    public function update(Request $request, int $deliverableId, int $fileId): \Illuminate\Http\JsonResponse
    {
        $deliverableFile = DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'nullable', 'file'],
        ]);

        $file = $deliverableFile->file()->firstOrFail();

        if (array_key_exists('name', $validated) && $validated['name'] !== null) {
            $file->name = $validated['name'];
        }

        if (array_key_exists('file', $validated) && $validated['file'] instanceof UploadedFile) {
            $uploadedFile = $validated['file'];

            $configuredDisk = config('filesystems.default', 'public');
            if (! config()->has('filesystems.disks.'.$configuredDisk)) {
                $configuredDisk = 'public';
            }

            $disk = method_exists(Storage::disk($configuredDisk), 'url') ? $configuredDisk : 'public';
            $directory = Carbon::now()->format('pg/uploads/Y/m/d');
            $path = $uploadedFile->store($directory, $disk);

            if ($file->path && $file->disk) {
                Storage::disk($file->disk)->delete($file->path);
            }

            /** @var FilesystemAdapter $adapter */
            $adapter = Storage::disk($disk);
            $url = method_exists($adapter, 'url') ? $adapter->url($path) : $adapter->path($path);

            $file->disk = $disk;
            $file->path = $path;
            $file->extension = $uploadedFile->getClientOriginalExtension();
            $file->url = $url;

            if (! array_key_exists('name', $validated) || $validated['name'] === null) {
                $file->name = $uploadedFile->getClientOriginalName();
            }
        }

        if ($file->isDirty()) {
            $file->save();
        }

        $deliverableFile->load('deliverable.phase.period', 'file');

        return response()->json($this->transformDeliverableFile($deliverableFile));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/deliverable-files/{deliverable_id}/{file_id}",
     *     summary="Delete a deliverable-file association",
     *     tags={"Deliverable Files"},
     *
     *     @OA\Parameter(
     *         name="deliverable_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="file_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable-file association deleted successfully"
     *     )
     * )
     */
    public function destroy(int $deliverableId, int $fileId): \Illuminate\Http\JsonResponse
    {
        $deliverableFile = DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->firstOrFail();

        DeliverableFile::where('deliverable_id', $deliverableId)
            ->where('file_id', $fileId)
            ->delete();

        $this->deleteFileIfOrphaned($fileId);

        return response()->json(['message' => 'Deliverable-file association deleted successfully']);
    }

    protected function deleteFileIfOrphaned(int $fileId): void
    {
        $file = File::find($fileId);

        if (! $file) {
            return;
        }

        $attachedToDeliverables = $file->deliverables()->exists();
        $attachedToSubmissions = $file->submissions()->exists();
        $attachedToRepositoryProjects = $file->repositoryProjects()->exists();
        $attachedToProposals = $file->proposals()->exists();

        if (! $attachedToDeliverables && ! $attachedToSubmissions && ! $attachedToRepositoryProjects && ! $attachedToProposals) {
            $file->delete();
        }
    }

    protected function transformDeliverableFile(DeliverableFile $deliverableFile): array
    {
        $deliverable = $deliverableFile->deliverable;
        $phase = $deliverable?->phase;
        $period = $phase?->period;

        return [
            'deliverable_id' => $deliverableFile->deliverable_id,
            'file_id' => $deliverableFile->file_id,
            'deliverable_name' => $deliverable?->name,
            'phase_name' => $phase?->name,
            'academic_period_name' => $period?->name,
            'file_name' => $deliverableFile->file?->name,
            'created_at' => optional($deliverableFile->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverableFile->updated_at)->toDateTimeString(),
        ];
    }
}
