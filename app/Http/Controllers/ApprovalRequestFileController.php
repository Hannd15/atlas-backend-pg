<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ApprovalRequestFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/approval-requests/{approval_request}/files",
     *     summary="List files attached to an approval request",
     *     tags={"Approval Requests"},
     *
     *     @OA\Parameter(name="approval_request", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Files related to the approval request",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=90),
     *                 @OA\Property(property="name", type="string", example="observaciones.pdf"),
     *                 @OA\Property(property="extension", type="string", example="pdf")
     *             )
     *         )
     *     )
     * )
     */
    public function index(ApprovalRequest $approvalRequest): JsonResponse
    {
        $files = $approvalRequest->files()->orderByDesc('approval_request_files.created_at')->get();

        return response()->json($files->map(fn (File $file) => $this->transformFile($file))->values());
    }

    /**
     * @OA\Post(
     *     path="/api/pg/approval-requests/{approval_request}/files",
     *     summary="Upload and attach a file to an approval request",
     *     tags={"Approval Requests"},
     *
     *     @OA\Parameter(name="approval_request", in="path", required=true, @OA\Schema(type="integer")),
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
     *                 @OA\Property(property="name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Attached file",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="id", type="integer", example=90),
     *             @OA\Property(property="name", type="string", example="observaciones.pdf"),
     *             @OA\Property(property="extension", type="string", example="pdf")
     *         )
     *     )
     * )
     */
    public function store(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $validated['file'];
        $configuredDisk = config('filesystems.default', 'public');

        if (! config()->has('filesystems.disks.'.$configuredDisk)) {
            $configuredDisk = 'public';
        }

        $disk = method_exists(Storage::disk($configuredDisk), 'url') ? $configuredDisk : 'public';
        $directory = Carbon::now()->format('pg/approval-requests/Y/m/d');
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

        $approvalRequest->files()->attach($file->id);

        return response()->json($this->transformFile($file), 201);
    }

    protected function transformFile(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
        ];
    }
}
