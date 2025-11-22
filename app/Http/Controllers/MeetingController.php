<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Services\AtlasUserService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
 *     @OA\Property(property="start_time", type="string", format="time", nullable=true, example="10:00"),
 *     @OA\Property(property="end_time", type="string", format="time", nullable=true, example="11:00"),
 *     @OA\Property(property="timezone", type="string", nullable=true, example="America/New_York"),
 *     @OA\Property(property="observations", type="string", nullable=true, example="Revisión semanal"),
 *     @OA\Property(property="google_meet_url", type="string", nullable=true, example="https://meet.google.com/abc-defg-hij")
 * )
 */
class MeetingController extends Controller
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    public function __construct(
        protected GoogleCalendarService $googleCalendarService,
        protected AtlasUserService $atlasUserService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id');

        if (! $projectId) {
            throw ValidationException::withMessages([
                'project_id' => 'The project_id query parameter is required.',
            ]);
        }

        $meetings = Meeting::query()
            ->where('project_id', $projectId)
            ->with('project')
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($meetings->map(fn (Meeting $meeting) => [
            'project_name' => $meeting->project?->title,
            'meeting_date' => optional($meeting->meeting_date)->toDateString(),
            'observations' => $meeting->observations,
        ])->values()->all());
    }

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
     *             @OA\Property(property="meeting_date", type="string", format="date", example="2025-02-14"),
     *             @OA\Property(property="start_time", type="string", format="time", example="10:00", description="Start time in HH:mm format"),
     *             @OA\Property(property="end_time", type="string", format="time", example="11:00", description="End time in HH:mm format (must be after start_time)"),
     *             @OA\Property(property="timezone", type="string", example="America/New_York", description="Timezone for the meeting (defaults to app timezone)")
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
            'start_time' => $payload['start_time'] ?? null,
            'end_time' => $payload['end_time'] ?? null,
            'timezone' => self::DEFAULT_TIMEZONE,
            'observations' => null,
            'created_by' => $creatorId,
        ]);

        $meeting->attendees()->sync($attendeeIds);

        // Try to create Google Meet
        $this->createGoogleMeetForMeeting($request, $meeting, $attendeeIds);

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

        $oldDate = $meeting->meeting_date;
        $oldStartTime = $meeting->start_time;
        $oldEndTime = $meeting->end_time;

        $payload = $request->validated();
        $payload['timezone'] = self::DEFAULT_TIMEZONE;

        $meeting->update($payload);

        $attendeeIds = $this->attendeeIdsForProject($project);
        $meeting->attendees()->sync($attendeeIds);

        // Check if meeting date/time changed, and recreate Google Meet
        $dateOrTimeChanged = $meeting->meeting_date != $oldDate
            || $meeting->start_time != $oldStartTime
            || $meeting->end_time != $oldEndTime;

        $timeFieldsProvided = $request->filled('start_time') && $request->filled('end_time');

        if ($dateOrTimeChanged || $timeFieldsProvided) {
            if ($meeting->google_calendar_event_id) {
                $this->updateGoogleCalendarEvent($request, $meeting, $attendeeIds);
            } else {
                $this->createGoogleMeetForMeeting($request, $meeting, $attendeeIds);
            }
        }

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
    public function destroy(Request $request, Project $project, Meeting $meeting): JsonResponse
    {
        abort_if($meeting->project_id !== $project->id, 404);

        $this->deleteGoogleEventForMeeting($request, $meeting);

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
            'start_time' => $meeting->start_time ? Carbon::parse($meeting->start_time)->format('H:i') : null,
            'end_time' => $meeting->end_time ? Carbon::parse($meeting->end_time)->format('H:i') : null,
            'timezone' => $meeting->timezone ?? self::DEFAULT_TIMEZONE,
            'observations' => $meeting->observations,
            'url' => $meeting->url,
            'google_meet_url' => $meeting->google_meet_url,
        ];
    }

    protected function deleteGoogleEventForMeeting(Request $request, Meeting $meeting): void
    {
        if (! $meeting->google_calendar_event_id) {
            return;
        }

        $bearerToken = $request->header('Authorization');

        if (! $bearerToken) {
            Log::warning('No authorization token available for Google Calendar deletion', [
                'meeting_id' => $meeting->id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
            ]);

            return;
        }

        try {
            $result = $this->googleCalendarService->deleteEvent($bearerToken, $meeting->google_calendar_event_id);

            if ($result['success']) {
                Log::info('Google Calendar event deleted successfully', [
                    'meeting_id' => $meeting->id,
                    'google_calendar_event_id' => $meeting->google_calendar_event_id,
                ]);

                return;
            }

            Log::warning('Failed to delete Google Calendar event', [
                'meeting_id' => $meeting->id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
                'error' => $result['error'] ?? 'Unknown error',
                'status' => $result['status'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception deleting Google Calendar event', [
                'meeting_id' => $meeting->id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create or update Google Meet for a meeting.
     */
    protected function createGoogleMeetForMeeting(Request $request, Meeting $meeting, array $attendeeIds): void
    {
        // Skip if no time information provided
        if (! $meeting->start_time || ! $meeting->end_time) {
            Log::info('Skipping Google Meet creation - no time information', ['meeting_id' => $meeting->id]);

            return;
        }

        try {
            $bearerToken = $request->header('Authorization');

            if (! $bearerToken) {
                Log::warning('No authorization token available for Google Meet creation', ['meeting_id' => $meeting->id]);

                return;
            }

            $attendees = $this->resolveAttendees($bearerToken, $attendeeIds);

            $meetingData = $this->buildMeetingEventPayload($meeting, $attendees);

            $result = $this->googleCalendarService->createMeetWithRetry($bearerToken, $meetingData);

            if ($result['success'] && isset($result['data'])) {
                $meeting->update([
                    'google_calendar_event_id' => $result['data']['id'] ?? null,
                    'google_meet_url' => $result['data']['hangoutLink'] ?? null,
                ]);

                Log::info('Google Meet created successfully', [
                    'meeting_id' => $meeting->id,
                    'meet_url' => $meeting->google_meet_url,
                ]);
            } else {
                Log::warning('Failed to create Google Meet', [
                    'meeting_id' => $meeting->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'status' => $result['status'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception creating Google Meet', [
                'meeting_id' => $meeting->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function updateGoogleCalendarEvent(Request $request, Meeting $meeting, array $attendeeIds): void
    {
        if (! $meeting->google_calendar_event_id || ! $meeting->start_time || ! $meeting->end_time) {
            return;
        }

        $bearerToken = $request->header('Authorization');

        if (! $bearerToken) {
            Log::warning('No authorization token available for Google Calendar update', [
                'meeting_id' => $meeting->id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
            ]);

            return;
        }

        try {
            $attendees = $this->resolveAttendees($bearerToken, $attendeeIds);

            $eventData = $this->buildMeetingEventPayload($meeting, $attendees);

            $result = $this->googleCalendarService->updateEvent(
                $bearerToken,
                $meeting->google_calendar_event_id,
                $eventData
            );

            if ($result['success'] && isset($result['data'])) {
                $meeting->update([
                    'google_meet_url' => $result['data']['hangoutLink'] ?? $meeting->google_meet_url,
                ]);

                Log::info('Google Calendar event updated successfully', [
                    'meeting_id' => $meeting->id,
                    'google_calendar_event_id' => $meeting->google_calendar_event_id,
                ]);
            } elseif (! $result['success']) {
                Log::warning('Failed to update Google Calendar event', [
                    'meeting_id' => $meeting->id,
                    'google_calendar_event_id' => $meeting->google_calendar_event_id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'status' => $result['status'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception updating Google Calendar event', [
                'meeting_id' => $meeting->id,
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array{email: string|null}>  $attendees
     */
    protected function buildMeetingEventPayload(Meeting $meeting, array $attendees): array
    {
        $timezone = self::DEFAULT_TIMEZONE;
        $dateStr = Carbon::parse($meeting->meeting_date)->format('Y-m-d');
        $startDateTime = Carbon::parse("{$dateStr} {$meeting->start_time}", $timezone);
        $endDateTime = Carbon::parse("{$dateStr} {$meeting->end_time}", $timezone);

        return [
            'summary' => "Reunión Proyecto de Grado: {$meeting->project?->title}",
            'description' => $meeting->observations ?? 'Reunión de proyecto de grado programada desde Atlas.',
            'start' => [
                'dateTime' => $startDateTime->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $endDateTime->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'attendees' => $attendees,
        ];
    }

    /**
     * @return array<int, array{email: string|null}>
     */
    protected function resolveAttendees(string $bearerToken, array $attendeeIds): array
    {
        $token = trim(str_replace('Bearer ', '', $bearerToken));
        $users = $this->atlasUserService->fetchUsersByIds($token, $attendeeIds);

        return collect($users)->map(fn ($user) => [
            'email' => $user['email'] ?? null,
        ])->filter(fn ($attendee) => ! empty($attendee['email']))->values()->all();
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
