<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubric\StoreRubricRequest;
use App\Http\Requests\Rubric\UpdateRubricRequest;
use App\Models\Rubric;
use Illuminate\Http\JsonResponse;

class RubricController extends Controller
{
    public function index(): JsonResponse
    {
        $rubrics = Rubric::with('thematicLines', 'deliverables')->orderByDesc('updated_at')->get();

        return response()->json($rubrics->map(fn (Rubric $rubric) => $this->transformForIndex($rubric)));
    }

    public function store(StoreRubricRequest $request): JsonResponse
    {
        $rubric = Rubric::create($request->safe()->only(['name', 'description', 'min_value', 'max_value']));

        $this->syncRelations($rubric, $request->thematicLineIds(), $request->deliverableIds());

        return response()->json($this->transformForShow($rubric->load('thematicLines', 'deliverables')), 201);
    }

    public function show(Rubric $rubric): JsonResponse
    {
        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    public function update(UpdateRubricRequest $request, Rubric $rubric): JsonResponse
    {
        $rubric->update($request->safe()->only(['name', 'description', 'min_value', 'max_value']));

        $this->syncRelations($rubric, $request->thematicLineIds(), $request->deliverableIds());

        $rubric->load('thematicLines', 'deliverables');

        return response()->json($this->transformForShow($rubric));
    }

    public function destroy(Rubric $rubric): JsonResponse
    {
        $rubric->delete();

        return response()->json(['message' => 'Rubric deleted successfully']);
    }

    public function dropdown(): JsonResponse
    {
        $rubrics = Rubric::orderBy('name')->get()->map(fn (Rubric $rubric) => [
            'value' => $rubric->id,
            'label' => $rubric->name,
        ]);

        return response()->json($rubrics);
    }

    protected function syncRelations(Rubric $rubric, ?array $thematicLineIds, ?array $deliverableIds): void
    {
        if ($thematicLineIds !== null) {
            $rubric->thematicLines()->sync($thematicLineIds);
        }

        if ($deliverableIds !== null) {
            $rubric->deliverables()->sync($deliverableIds);
        }
    }

    protected function transformForIndex(Rubric $rubric): array
    {
        return [
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(Rubric $rubric): array
    {
        return [
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'min_value' => $rubric->min_value,
            'max_value' => $rubric->max_value,
            'thematic_line_ids' => $rubric->thematicLines->pluck('id')->values()->all(),
            'thematic_line_names' => $rubric->thematicLines->pluck('name')->implode(', '),
            'deliverable_ids' => $rubric->deliverables->pluck('id')->values()->all(),
            'deliverable_names' => $rubric->deliverables->pluck('name')->implode(', '),
            'created_at' => optional($rubric->created_at)->toDateTimeString(),
            'updated_at' => optional($rubric->updated_at)->toDateTimeString(),
        ];
    }
}
