<?php

namespace App\Http\Controllers;

use App\Models\ThematicLine;
use Illuminate\Http\Request;

class ThematicLineController extends Controller
{
    public function update(Request $request, ThematicLine $thematicLine)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trl_expected' => 'nullable|integer',
            'abet_criteria' => 'nullable|string',
            'min_score' => 'nullable|integer',
            'proposal_ids' => 'nullable|array',
            'proposal_ids.*' => 'exists:proposals,id',
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        $thematicLine->update($request->only('name', 'description', 'trl_expected', 'abet_criteria', 'min_score'));

        if ($request->has('proposal_ids')) {
            $thematicLine->proposals()->sync($request->input('proposal_ids'));
        }

        if ($request->has('rubric_ids')) {
            $thematicLine->rubrics()->sync($request->input('rubric_ids'));
        }

        return response()->json($thematicLine);
    }

    public function destroy(ThematicLine $thematicLine)
    {
        $thematicLine->delete();

        return response()->json(['message' => 'Thematic line deleted successfully']);
    }

    public function dropdown()
    {
        $thematicLines = ThematicLine::all()->map(function ($thematicLine) {
            return [
                'value' => $thematicLine->id,
                'label' => $thematicLine->name,
            ];
        });

        return response()->json($thematicLines);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trl_expected' => 'nullable|integer',
            'abet_criteria' => 'nullable|string',
            'min_score' => 'nullable|integer',
            'proposal_ids' => 'nullable|array',
            'proposal_ids.*' => 'exists:proposals,id',
            'rubric_ids' => 'nullable|array',
            'rubric_ids.*' => 'exists:rubrics,id',
        ]);

        $thematicLine = ThematicLine::create($request->only('name', 'description', 'trl_expected', 'abet_criteria', 'min_score'));

        if ($request->has('proposal_ids')) {
            $thematicLine->proposals()->sync($request->input('proposal_ids'));
        }

        if ($request->has('rubric_ids')) {
            $thematicLine->rubrics()->sync($request->input('rubric_ids'));
        }

        return response()->json($thematicLine, 201);
    }

    public function show(ThematicLine $thematicLine)
    {
        $thematicLine->load('proposals', 'rubrics');

        $thematicLine->proposal_ids = $thematicLine->proposals->pluck('id');
        $thematicLine->rubric_ids = $thematicLine->rubrics->pluck('id');

        return response()->json($thematicLine);
    }

    public function index()
    {
        $thematicLines = ThematicLine::with(['proposals', 'rubrics'])->orderBy('updated_at', 'desc')->get();

        $thematicLines->each(function ($thematicLine) {
            $thematicLine->proposal_names = $thematicLine->proposals->pluck('title')->implode(', ');
            $thematicLine->rubric_names = $thematicLine->rubrics->pluck('name')->implode(', ');
        });

        return response()->json($thematicLines);
    }
}
