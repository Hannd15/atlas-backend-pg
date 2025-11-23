<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Submission Files",
 *     description="API endpoints for uploading and listing files associated with submissions"
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionFileResource",
 *     title="Submission File Resource",
 *     description="Detailed submission file information",
 *
 *     @OA\Property(property="id", type="integer", example=123),
 *     @OA\Property(property="name", type="string", example="avance.pdf"),
 *     @OA\Property(property="extension", type="string", example="pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/avance.pdf"),
 *     @OA\Property(property="disk", type="string", example="public"),
 *     @OA\Property(property="path", type="string", example="pg/uploads/2025/04/15/avance.pdf"),
 *     @OA\Property(property="submission_id", type="integer", example=42),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SubmissionFileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/submission-files",
     *     summary="Get all submission files",
     *     tags={"Submission Files"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of submission files with contextual metadata",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",

     *
     *                 @OA\Property(property="file_id", type="integer"),
     *                 @OA\Property(property="submission_id", type="integer"),
     *                 @OA\Property(property="name", type="string", nullable=true),
     *                 @OA\Property(property="extension", type="string", nullable=true),
     *                 @OA\Property(property="url", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(): JsonResponse
    {
        $submissionFiles = SubmissionFile::with([
            'submission.deliverable.phase.period',
            'submission.project',
            'file',
        ])->get();

        $result = $submissionFiles->map(function (SubmissionFile $submissionFile) {
            $file = $submissionFile->file;

            return [
                'file_id' => $submissionFile->file_id,
                'submission_id' => $submissionFile->submission_id,
                'name' => $file?->name,
                'extension' => $file?->extension,
                'url' => $file?->url,
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/projects/{project}/submissions/{submission}/files",
     *     summary="Get files associated with a submission",
     *     tags={"Submission Files"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of files",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="extension", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index($academicPeriod, $phase, $deliverable, $project, int $submissionId): JsonResponse
    {
        $submission = $this->findSubmissionOrFail($submissionId, (int) $deliverable, (int) $project);
        $files = $submission->files()->orderByDesc('updated_at')->get();

        return response()->json(
            $files->map(fn (File $file) => [
                'id' => $file->id,
                'name' => $file->name,
                'extension' => $file->extension,
            ])
        );
    }

    /**
     * @OA\Post(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/projects/{project}/submissions/{submission}/files",
     *     summary="Upload and associate a file with a submission",
     *     tags={"Submission Files"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
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
     *         description="File stored and associated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/SubmissionFileResource")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $academicPeriod, $phase, $deliverable, $project, int $submissionId): JsonResponse
    {
        $submission = $this->findSubmissionOrFail($submissionId, (int) $deliverable, (int) $project);

        $validated = $request->validate([
            'file' => 'required|file',
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

        $name = $uploadedFile->getClientOriginalName();

        $file = File::create([
            'name' => $name,
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'url' => $url,
            'disk' => $disk,
            'path' => $path,
        ]);

        $submission->files()->attach($file->id);

        return response()->json($this->transformFileForSubmission($file, $submissionId), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/projects/{project}/submissions/{submission}/files/{file}",
     *     summary="Detach a file from a submission",
     *     tags={"Submission Files"},
     *
     *     @OA\Parameter(name="academic_period", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="phase", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="deliverable", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="submission", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="file", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="File detached from submission"),
     *     @OA\Response(response=404, description="Submission or file not found")
     * )
     */
    public function destroy($academicPeriod, $phase, $deliverable, $project, int $submissionId, File $file): JsonResponse
    {
        $submission = $this->findSubmissionOrFail($submissionId, (int) $deliverable, (int) $project);

        if (! $submission->files()->where('files.id', $file->id)->exists()) {
            abort(404, 'File not associated with this submission');
        }

        $submission->files()->detach($file->id);

        return response()->json(['message' => 'File detached successfully']);
    }

    protected function transformFileForSubmission(File $file, int $submissionId): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
            'disk' => $file->disk,
            'path' => $file->path,
            'submission_id' => $submissionId,
            'created_at' => optional($file->created_at)->toDateTimeString(),
            'updated_at' => optional($file->updated_at)->toDateTimeString(),
        ];
    }

    protected function findSubmissionOrFail(int $submissionId, int $deliverableId, int $projectId): Submission
    {
        return Submission::where('id', $submissionId)
            ->where('deliverable_id', $deliverableId)
            ->where('project_id', $projectId)
            ->firstOrFail();
    }
}
