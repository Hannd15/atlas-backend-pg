<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Meetings",
 *     description="API endpoints for scheduling and managing project meetings"
 * )
 *
 * @OA\Schema(
 *     schema="MeetingDetailResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=42),
 *     @OA\Property(property="project_id", type="integer", example=12),
 *     @OA\Property(property="project_name", type="string", example="Proyecto Principal"),
 *     @OA\Property(property="meeting_date", type="string", format="date", example="2025-02-14"),
 *     @OA\Property(property="observations", type="string", nullable=true, example="Revisión semanal"),
 *     @OA\Property(property="url", type="string", example="https://meetings.test/project-12/20250214")
 * )
 */
class MeetingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/projects/{project}/meetings",
     *     summary="List meetings for a project",
     *     tags={"Meetings"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Meetings for the project",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/MeetingDetailResource")
     *         )
     *     )
     * )
     */
    public function projectMeetings(Project $project): JsonResponse
    {
        $meetings = $project->meetings()
            ->with('project', 'creator', 'attendees')
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($meetings->map(fn (Meeting $meeting) => $this->transform($meeting))->values()->all());
    }

    /**
     * @OA\Post(
     *     path="/api/pg/projects/{project}/meetings",
     *     summary="Schedule a meeting for a project",
     *     tags={"Meetings"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"meeting_date"},
     *
     *             @OA\Property(property="meeting_date", type="string", format="date", example="2025-02-14")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Meeting created successfully", @OA\JsonContent(ref="#/components/schemas/MeetingDetailResource"))
     * )
     */
    public function store(StoreMeetingRequest $request, Project $project): JsonResponse
    {
        $creatorId = $this->resolveCreatorId($project);
        $attendeeIds = $this->attendeeIdsForProject($project);
        $payload = $request->validated();

        $meeting = Meeting::create([
            'project_id' => $project->id,
            'meeting_date' => $payload['meeting_date'],
            'observations' => null,
            'created_by' => $creatorId,
        ]);

        $meeting->attendees()->sync($attendeeIds);

        $meeting->load('project', 'creator', 'attendees');

        return response()->json($this->transform($meeting), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/projects/{project}/meetings/{meeting}",
     *     summary="Show meeting",
     *     tags={"Meetings"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="meeting", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Meeting detail", @OA\JsonContent(ref="#/components/schemas/MeetingDetailResource"))
     * )
     */
    public function show(Project $project, Meeting $meeting): JsonResponse
    {
        abort_if($meeting->project_id !== $project->id, 404);

        $meeting->load('project', 'creator', 'attendees');

        return response()->json($this->transform($meeting));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/projects/{project}/meetings/{meeting}",
     *     summary="Update meeting",
     *     tags={"Meetings"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="meeting", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="meeting_date", type="string", format="date"),
     *             @OA\Property(property="observations", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Meeting updated successfully", @OA\JsonContent(ref="#/components/schemas/MeetingDetailResource"))
     * )
     */
    public function update(UpdateMeetingRequest $request, Project $project, Meeting $meeting): JsonResponse
    {
        abort_if($meeting->project_id !== $project->id, 404);

        $meeting->update($request->validated());

        $meeting->attendees()->sync($this->attendeeIdsForProject($project));

        $meeting->load('project', 'creator', 'attendees');

        return response()->json($this->transform($meeting));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/projects/{project}/meetings/{meeting}",
     *     summary="Delete meeting",
     *     tags={"Meetings"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="meeting", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Meeting deleted successfully")
     * )
     */
    public function destroy(Project $project, Meeting $meeting): JsonResponse
    {
        abort_if($meeting->project_id !== $project->id, 404);

        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted successfully']);
    }

    protected function transform(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'project_id' => $meeting->project_id,
            'project_name' => $meeting->project?->title,
            'meeting_date' => optional($meeting->meeting_date)->toDateString(),
            'observations' => $meeting->observations,
            'url' => $meeting->url,
        ];
    }

    private function resolveCreatorId(Project $project): int
    {
        if ($userId = Auth::id()) {
            return $userId;
        }

        $project->loadMissing(['staff', 'groups.members']);

        $fromStaff = $project->staff->pluck('user_id')->filter()->first();
        if ($fromStaff) {
            return (int) $fromStaff;
        }

        $fromMembers = $project->groups
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
            ->filter()
            ->first();

        if ($fromMembers) {
            return (int) $fromMembers;
        }

        throw ValidationException::withMessages([
            'project_id' => 'Unable to determine a creator for this meeting. Please associate staff or group members first.',
        ]);
    }

    private function attendeeIdsForProject(Project $project): array
    {
        $project->loadMissing(['staff', 'groups.members']);

        $attendeeIds = $project->staff->pluck('user_id')
            ->merge($project->groups->flatMap(fn ($group) => $group->members->pluck('user_id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($attendeeIds)) {
            throw ValidationException::withMessages([
                'project_id' => 'The project must have staff or group members before scheduling meetings.',
            ]);
        }

        return $attendeeIds;
    }
}
