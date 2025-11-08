<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function update(Request $request, Project $project)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string|max:255',
            'proposal_id' => 'nullable|exists:proposals,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:project_groups,id',
        ]);

        $project->update($request->only('title', 'status', 'proposal_id'));

        if ($request->has('group_ids')) {
            $project->groups()->sync($request->input('group_ids'));
        }

        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    public function dropdown()
    {
        $projects = Project::all()->map(function ($project) {
            return [
                'value' => $project->id,
                'label' => $project->title,
            ];
        });

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'proposal_id' => 'nullable|exists:proposals,id',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:project_groups,id',
        ]);

        $project = Project::create($request->only('title', 'status', 'proposal_id'));

        if ($request->has('group_ids')) {
            $project->groups()->sync($request->input('group_ids'));
        }

        return response()->json($project, 201);
    }

    public function show(Project $project)
    {
        $project->load('proposal', 'groups', 'deliverables', 'submissions');

        $project->proposal_id = $project->proposal ? [$project->proposal->id] : [];
        $project->group_ids = $project->groups->pluck('id');
        $project->deliverable_ids = $project->deliverables->pluck('id');
        $project->submission_ids = $project->submissions->pluck('id');

        return response()->json($project);
    }

    public function index()
    {
        $projects = Project::with('proposal')->orderBy('updated_at', 'desc')->get();

        $projects->each(function ($project) {
            $project->proposal_names = $project->proposal ? $project->proposal->title : '';
        });

        return response()->json($projects);
    }
}
