<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThematicLine\StoreThematicLineRequest;
use App\Http\Requests\ThematicLine\UpdateThematicLineRequest;
use App\Models\ThematicLine;
use Illuminate\Http\JsonResponse;

class ThematicLineController extends Controller
{
    public function index(): JsonResponse
    {
        $thematicLines = ThematicLine::with('rubrics')->orderByDesc('updated_at')->get();

        return response()->json($thematicLines->map(fn (ThematicLine $thematicLine) => $this->transformForIndex($thematicLine)));
    }

    public function store(StoreThematicLineRequest $request): JsonResponse
    {
        $thematicLine = ThematicLine::create($request->safe()->only(['name', 'description']));

        if (($rubricIds = $request->rubricIds()) !== null) {
            $thematicLine->rubrics()->sync($rubricIds);
        }

        return response()->json($this->transformForShow($thematicLine->load('rubrics')), 201);
    }

    public function show(ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->load('rubrics');

        return response()->json($this->transformForShow($thematicLine));
    }

    public function update(UpdateThematicLineRequest $request, ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->update($request->safe()->only(['name', 'description']));

        if (($rubricIds = $request->rubricIds()) !== null) {
            $thematicLine->rubrics()->sync($rubricIds);
        }

        $thematicLine->load('rubrics');

        return response()->json($this->transformForShow($thematicLine));
    }

    public function destroy(ThematicLine $thematicLine): JsonResponse
    {
        $thematicLine->delete();

        return response()->json(['message' => 'Thematic line deleted successfully']);
    }

    public function dropdown(): JsonResponse
    {
        $thematicLines = ThematicLine::orderBy('name')->get()->map(fn (ThematicLine $thematicLine) => [
            'value' => $thematicLine->id,
            'label' => $thematicLine->name,
        ]);

        return response()->json($thematicLines);
    }

    protected function transformForIndex(ThematicLine $thematicLine): array
    {
        return [
            'id' => $thematicLine->id,
            'name' => $thematicLine->name,
            'description' => $thematicLine->description,
            'rubric_names' => $thematicLine->rubrics->pluck('name')->implode(', '),
            'rubric_ids' => $thematicLine->rubrics->pluck('id')->values()->all(),
            'created_at' => optional($thematicLine->created_at)->toDateTimeString(),
            'updated_at' => optional($thematicLine->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(ThematicLine $thematicLine): array
    {
        return [
            'id' => $thematicLine->id,
            'name' => $thematicLine->name,
            'description' => $thematicLine->description,
            'rubric_ids' => $thematicLine->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $thematicLine->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($thematicLine->created_at)->toDateTimeString(),
            'updated_at' => optional($thematicLine->updated_at)->toDateTimeString(),
        ];
    }
}
