<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proposal\StoreProposalRequest;
use App\Http\Requests\Proposal\UpdateProposalRequest;
use App\Models\File;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Services\AtlasAuthService;
use App\Services\FileStorageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Proposals",
 *     description="Endpoints for viewing and managing research proposals"
 * )
 *
 * @OA\Schema(
 *     schema="ProposalPayload",
 *     type="object",
 *     required={"title","thematic_line_id","proposer_id"},
 *
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="thematic_line_id", type="integer", example=4),
 *     @OA\Property(property="proposer_id", type="integer", example=12),
 *     @OA\Property(property="preferred_director_id", type="integer", nullable=true, example=32),
 *     @OA\Property(property="proposal_status_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer", example=77)),
 *     @OA\Property(
 *         property="files",
 *         type="array",
 *
 *         @OA\Items(type="string", format="binary")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProposalResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=15),
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(
 *         property="proposal_type",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=2),
 *         @OA\Property(property="code", type="string", example="made_by_teacher"),
 *         @OA\Property(property="name", type="string", example="Docente")
 *     ),
 *     @OA\Property(
 *         property="proposal_status",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=4),
 *         @OA\Property(property="code", type="string", example="pending"),
 *         @OA\Property(property="name", type="string", example="Pendiente")
 *     ),
 *     @OA\Property(property="proposer", type="object", nullable=true, @OA\Property(property="id", type="integer", example=9), @OA\Property(property="name", type="string", example="Laura Mejía")),
 *     @OA\Property(property="preferred_director", type="object", nullable=true, @OA\Property(property="id", type="integer", example=23), @OA\Property(property="name", type="string", example="Ing. Carlos")),
 *     @OA\Property(property="thematic_line", type="object", nullable=true, @OA\Property(property="id", type="integer", example=3), @OA\Property(property="name", type="string", example="IoT")),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="file_names", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProposalController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService,
        protected AtlasAuthService $atlasAuthService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/proposals",
     *     summary="List proposals created by teachers",
     *     tags={"Proposals"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proposals list",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProposalResource"))
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $proposals = Proposal::with($this->defaultRelations())
            ->whereHas('type', fn ($query) => $query->where('code', 'made_by_teacher'))
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($proposals->map(fn (Proposal $proposal) => $this->transformForIndex($proposal)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/proposals",
     *     summary="Create a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/ProposalPayload"))
     *     ),
     *
     *     @OA\Response(response=201, description="Proposal created", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $proposalTypeId = $this->determineProposalTypeId($request);
        $statusId = $validated['proposal_status_id'] ?? $this->defaultStatusId();

        $proposal = Proposal::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'proposal_type_id' => $proposalTypeId,
            'proposal_status_id' => $statusId,
            'proposer_id' => $validated['proposer_id'],
            'preferred_director_id' => $validated['preferred_director_id'] ?? null,
            'thematic_line_id' => $validated['thematic_line_id'],
        ]);

        $this->syncExistingFiles($proposal, $request->fileIds() ?? []);

        $storedFiles = $this->storeUploadedFiles($request);
        if ($storedFiles->isNotEmpty()) {
            $proposal->files()->syncWithoutDetaching($storedFiles->pluck('id')->all());
        }

        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Show a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal detail", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function show(Proposal $proposal): JsonResponse
    {
        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Update a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/ProposalPayload"))),
     *
     *     @OA\Response(response=200, description="Proposal updated", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validated();

        $proposal->update(Arr::only($validated, [
            'title',
            'description',
            'proposal_status_id',
            'proposer_id',
            'preferred_director_id',
            'thematic_line_id',
        ]));

        $existingFileIds = $proposal->files()->pluck('files.id')->all();

        $fileIds = $request->fileIds();
        if ($fileIds !== null) {
            $proposal->files()->sync($fileIds);
            $this->deleteRemovedFiles($proposal, $existingFileIds, $fileIds);
        }

        $storedFiles = $this->storeUploadedFiles($request);
        if ($storedFiles->isNotEmpty()) {
            $proposal->files()->syncWithoutDetaching($storedFiles->pluck('id')->all());
        }

        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Delete a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal deleted"),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function destroy(Proposal $proposal): JsonResponse
    {
        $fileIds = $proposal->files()->pluck('files.id')->all();

        $proposal->files()->detach();

        foreach ($fileIds as $fileId) {
            $this->deleteFileIfOrphaned($proposal, (int) $fileId);
        }

        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted successfully']);
    }

    protected function transformForIndex(Proposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'type' => $proposal->type ? [
                'id' => $proposal->type->id,
                'code' => $proposal->type->code,
                'name' => $proposal->type->name,
            ] : null,
            'status' => $proposal->status ? [
                'id' => $proposal->status->id,
                'code' => $proposal->status->code,
                'name' => $proposal->status->name,
            ] : null,
            'proposer' => $proposal->proposer ? [
                'id' => $proposal->proposer->id,
                'name' => $proposal->proposer->name,
            ] : null,
            'preferred_director' => $proposal->preferredDirector ? [
                'id' => $proposal->preferredDirector->id,
                'name' => $proposal->preferredDirector->name,
            ] : null,
            'thematic_line' => $proposal->thematicLine ? [
                'id' => $proposal->thematicLine->id,
                'name' => $proposal->thematicLine->name,
            ] : null,
            'created_at' => optional($proposal->created_at)->toDateTimeString(),
            'updated_at' => optional($proposal->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(Proposal $proposal): array
    {
        $files = $proposal->files->values();

        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'proposal_type' => $proposal->type ? [
                'id' => $proposal->type->id,
                'code' => $proposal->type->code,
                'name' => $proposal->type->name,
            ] : null,
            'proposal_status' => $proposal->status ? [
                'id' => $proposal->status->id,
                'code' => $proposal->status->code,
                'name' => $proposal->status->name,
            ] : null,
            'proposer' => $proposal->proposer ? [
                'id' => $proposal->proposer->id,
                'name' => $proposal->proposer->name,
            ] : null,
            'preferred_director' => $proposal->preferredDirector ? [
                'id' => $proposal->preferredDirector->id,
                'name' => $proposal->preferredDirector->name,
            ] : null,
            'thematic_line' => $proposal->thematicLine ? [
                'id' => $proposal->thematicLine->id,
                'name' => $proposal->thematicLine->name,
            ] : null,
            'file_ids' => $files->pluck('id')->values()->all(),
            'file_names' => $files->pluck('name')->values()->all(),
            'created_at' => optional($proposal->created_at)->toDateTimeString(),
            'updated_at' => optional($proposal->updated_at)->toDateTimeString(),
        ];
    }

    protected function defaultRelations(): array
    {
        return [
            'type',
            'status',
            'thematicLine',
            'proposer',
            'preferredDirector',
            'files',
        ];
    }

    protected function determineProposalTypeId(Request $request): int
    {
        $userData = $request->attributes->get('atlasUser');

        if (! is_array($userData)) {
            $token = (string) $request->bearerToken();
            $userData = $this->atlasAuthService->verifyToken($token)['user'] ?? [];
            $request->attributes->set('atlasUser', $userData);
        }

        $roles = $this->extractRoleNames($userData);

        $typeCode = $this->inferTypeCodeFromRoles($roles);
        $typeId = $this->typeId($typeCode);

        if ($typeId === null) {
            $typeId = $this->typeId('made_by_student');
        }

        if ($typeId === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Proposal type configuration is missing.',
            ], 503));
        }

        return $typeId;
    }

    protected function extractRoleNames(array $userData): array
    {
        $roles = [];
        $rawRoles = $userData['roles'] ?? [];

        if (is_string($rawRoles)) {
            $roles[] = $rawRoles;
        } elseif (is_array($rawRoles)) {
            foreach ($rawRoles as $role) {
                if (is_string($role)) {
                    $roles[] = $role;

                    continue;
                }

                if (is_array($role)) {
                    if (isset($role['name'])) {
                        $roles[] = $role['name'];

                        continue;
                    }

                    if (isset($role['label'])) {
                        $roles[] = $role['label'];
                    }
                }
            }
        }

        return $roles;
    }

    protected function inferTypeCodeFromRoles(array $roles): string
    {
        $normalized = collect($roles)->map(fn ($role) => Str::lower($role));

        if ($normalized->contains('director')) {
            return 'made_by_teacher';
        }

        if ($normalized->contains('estudiante')) {
            return 'made_by_student';
        }

        return 'made_by_student';
    }

    protected function typeId(string $code): ?int
    {
        static $cache = [];

        if (! array_key_exists($code, $cache)) {
            $cache[$code] = ProposalType::where('code', $code)->value('id');
        }

        return $cache[$code];
    }

    protected function defaultStatusId(): ?int
    {
        static $defaultStatusId = null;

        if ($defaultStatusId === null) {
            $defaultStatusId = ProposalStatus::where('code', 'pending')->value('id');
        }

        return $defaultStatusId;
    }

    protected function storeUploadedFiles(StoreProposalRequest|UpdateProposalRequest $request): Collection
    {
        $files = $request->uploadedFiles();

        if (empty($files)) {
            return collect();
        }

        return $this->fileStorageService->storeUploadedFiles($files);
    }

    protected function syncExistingFiles(Proposal $proposal, array $fileIds): void
    {
        if (empty($fileIds)) {
            return;
        }

        $proposal->files()->syncWithoutDetaching($fileIds);
    }

    protected function deleteRemovedFiles(Proposal $proposal, array $previousIds, array $currentIds): void
    {
        $removed = array_diff($previousIds, $currentIds);

        foreach ($removed as $fileId) {
            $this->deleteFileIfOrphaned($proposal, (int) $fileId);
        }
    }

    protected function deleteFileIfOrphaned(Proposal $proposal, int $fileId): void
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
}
