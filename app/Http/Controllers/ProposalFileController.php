<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Proposal;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Proposal Files",
 *     description="Endpoints for uploading and listing files associated with proposals"
 * )
 *
 * @OA\Schema(
 *     schema="ProposalFileResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=120),
 *     @OA\Property(property="name", type="string", example="resumen.pdf"),
 *     @OA\Property(property="extension", type="string", example="pdf"),
 *     @OA\Property(property="url", type="string", example="https://storage.test/pg/uploads/2025/resumen.pdf")
 * )
 */
class ProposalFileController extends Controller
{
    public function __construct(protected FileStorageService $fileStorageService) {}

    /**
     * @OA\Get(
     *     path="/api/pg/proposals/{proposal}/files",
     *     summary="List files for a proposal",
     *     tags={"Proposal Files"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Files for the proposal",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProposalFileResource"))
     *     )
     * )
     */
    public function index(Proposal $proposal): JsonResponse
    {
        $files = $proposal->files()->orderByDesc('proposal_files.created_at')->get();

        return response()->json($files->map(fn (File $file) => $this->transform($file))->values());
    }

    /**
     * @OA\Post(
     *     path="/api/pg/proposals/{proposal}/files",
     *     summary="Upload and attach a file to a proposal",
     *     tags={"Proposal Files"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(required={"file"}, @OA\Property(property="file", type="string", format="binary"))
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="File uploaded", @OA\JsonContent(ref="#/components/schemas/ProposalFileResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $files = $this->fileStorageService->storeUploadedFiles([$validated['file']]);
        $file = $files->first();

        if ($validated['name'] ?? null) {
            $file->name = $validated['name'];
            $file->save();
        }

        $proposal->files()->syncWithoutDetaching([$file->id]);

        return response()->json($this->transform($file), 201);
    }

    private function transform(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
        ];
    }
}
