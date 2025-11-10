<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\Phase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class PhaseDeliverableService
{
    public function __construct(
        protected FileStorageService $fileStorageService,
    ) {}

    /**
     * Replace all deliverables of the given phase using the provided payload structure.
     *
     * @param  array<int, array<string, mixed>>  $deliverablesPayload
     * @param  array<int, array<string, UploadedFile|array|null>|UploadedFile|null>  $uploadedFiles
     * @return Collection<int, Deliverable>
     */
    public function replaceDeliverables(Phase $phase, array $deliverablesPayload, array $uploadedFiles = []): Collection
    {
        $phase->deliverables()->delete();

        $created = collect();

        foreach ($deliverablesPayload as $index => $payload) {
            $deliverable = $this->createDeliverableFromPayload(
                $phase,
                $payload,
                $uploadedFiles[$index]['files'] ?? ($uploadedFiles[$index] ?? []),
                $index + 1
            );

            $created->push($deliverable);
        }

        return $created;
    }

    /**
     * Clone deliverables, including file relationships, from the source phase.
     */
    public function cloneFromPhase(Phase $target, Phase $source): Collection
    {
        $cloned = collect();

        foreach ($source->deliverables as $deliverable) {
            $newDeliverable = $target->deliverables()->create([
                'name' => $deliverable->name,
                'description' => $deliverable->description,
                'due_date' => $deliverable->due_date,
            ]);

            $newDeliverable->files()->sync($deliverable->files->pluck('id')->all());

            $cloned->push($newDeliverable);
        }

        return $cloned;
    }

    /**
     * Create a deliverable for the given phase using payload + uploaded files.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile|array|null>  $uploadedFiles
     */
    protected function createDeliverableFromPayload(Phase $phase, array $payload, array $uploadedFiles = [], int $fallbackPosition = 1): Deliverable
    {
        $fileIds = collect($payload['file_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $uploaded = $this->fileStorageService->storeUploadedFiles($uploadedFiles);

        if ($uploaded->isNotEmpty()) {
            $fileIds = $fileIds->merge($uploaded->pluck('id'));
        }

        $deliverable = $phase->deliverables()->create([
            'name' => $payload['name'] ?? 'Entrega '.$fallbackPosition,
            'description' => $payload['description'] ?? null,
            'due_date' => $payload['due_date'] ?? null,
        ]);

        if ($fileIds->isNotEmpty()) {
            $deliverable->files()->sync($fileIds->unique()->all());
        }

        return $deliverable->load('files');
    }
}
