<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeetingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_meeting_with_auto_attendees(): void
    {
        [$project, $memberUser, $staffUser] = $this->createProjectStructure('Proyecto Principal');

        $meetingDate = Carbon::now()->addDays(3);

        $this->actingAs($staffUser);

        $response = $this->postJson("/api/pg/projects/{$project->id}/meetings", [
            'meeting_date' => $meetingDate->toDateString(),
        ]);

        $meeting = Meeting::with('attendees')
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();

        $expectedUrl = sprintf('https://meetings.test/project-%s/%s', $project->id, $meetingDate->format('Ymd'));

        $response->assertCreated()
            ->assertExactJson([
                'id' => $meeting->id,
                'project_id' => $project->id,
                'project_name' => 'Proyecto Principal',
                'meeting_date' => $meetingDate->toDateString(),
                'observations' => null,
                'url' => $expectedUrl,
            ]);

        $this->assertEqualsCanonicalizing([
            $staffUser->id,
            $memberUser->id,
        ], $meeting->attendees->pluck('id')->all());

        $this->assertSame($staffUser->id, $meeting->created_by);
    }

    public function test_update_only_allows_date_and_observations(): void
    {
        [$project, $memberUser, $staffUser] = $this->createProjectStructure('Proyecto de seguimiento');

        $this->actingAs($staffUser);

        $initialDate = Carbon::now()->addDay();

        $this->postJson("/api/pg/projects/{$project->id}/meetings", [
            'meeting_date' => $initialDate->toDateString(),
        ])->assertCreated();

        $meeting = Meeting::with('attendees')
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();

        $newMember = User::factory()->create();
        GroupMember::create([
            'group_id' => $project->groups->first()->id,
            'user_id' => $newMember->id,
        ]);

        $updatedDate = Carbon::now()->addDays(5);
        $otherUser = User::factory()->create();

        $response = $this->putJson("/api/pg/projects/{$project->id}/meetings/{$meeting->id}", [
            'meeting_date' => $updatedDate->toDateString(),
            'observations' => 'Agenda revisada',
            'created_by' => $otherUser->id,
            'project_id' => $project->id + 1,
        ]);

        $expectedUrl = sprintf('https://meetings.test/project-%s/%s', $project->id, $updatedDate->format('Ymd'));

        $response->assertOk()->assertExactJson([
            'id' => $meeting->id,
            'project_id' => $project->id,
            'project_name' => 'Proyecto de seguimiento',
            'meeting_date' => $updatedDate->toDateString(),
            'observations' => 'Agenda revisada',
            'url' => $expectedUrl,
        ]);

        $meeting->refresh()->load('attendees');

        $this->assertSame($staffUser->id, $meeting->created_by);
        $this->assertContains($newMember->id, $meeting->attendees->pluck('id')->all());
        $this->assertContains($memberUser->id, $meeting->attendees->pluck('id')->all());
    }

    public function test_index_returns_minimal_payload(): void
    {
        [$project, $memberUser, $staffUser] = $this->createProjectStructure('Proyecto reducido');

        $this->actingAs($staffUser);

        $meetingDate = Carbon::parse('2025-01-15');

        $this->postJson("/api/pg/projects/{$project->id}/meetings", [
            'meeting_date' => $meetingDate->toDateString(),
        ])->assertCreated();

        $meeting = Meeting::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();
        $meeting->update(['observations' => 'Notas generales']);

        $this->getJson("/api/pg/meetings?project_id={$project->id}")
            ->assertOk()
            ->assertExactJson([
                [
                    'project_name' => 'Proyecto reducido',
                    'meeting_date' => '2025-01-15',
                    'observations' => 'Notas generales',
                ],
            ]);
    }

    public function test_project_meetings_endpoint_returns_full_details(): void
    {
        [$project, $memberUser, $staffUser] = $this->createProjectStructure('Proyecto completo');

        $this->actingAs($staffUser);

        $meetingDate = Carbon::parse('2025-03-01');

        $this->postJson("/api/pg/projects/{$project->id}/meetings", [
            'meeting_date' => $meetingDate->toDateString(),
        ])->assertCreated();

        $meeting = Meeting::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();
        $meeting->update(['observations' => 'Plan de trabajo']);
        $meeting->refresh();

        $expectedUrl = sprintf('https://meetings.test/project-%s/%s', $project->id, $meetingDate->format('Ymd'));

        $this->getJson("/api/pg/projects/{$project->id}/meetings")
            ->assertOk()
            ->assertExactJson([
                [
                    'id' => $meeting->id,
                    'project_id' => $project->id,
                    'project_name' => 'Proyecto completo',
                    'meeting_date' => $meetingDate->toDateString(),
                    'observations' => 'Plan de trabajo',
                    'url' => $expectedUrl,
                ],
            ]);
    }

    /**
     * @return array{0: \App\Models\Project, 1: \App\Models\User, 2: \App\Models\User}
     */
    private function createProjectStructure(string $projectTitle): array
    {
        $project = Project::factory()->create(['title' => $projectTitle]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'name' => 'Equipo Principal',
        ]);

        $memberUser = User::factory()->create();
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $memberUser->id,
        ]);

        $position = ProjectPosition::firstOrCreate(['name' => 'Director']);
        $staffUser = User::factory()->create();

        ProjectStaff::create([
            'project_id' => $project->id,
            'user_id' => $staffUser->id,
            'project_position_id' => $position->id,
            'status' => 'active',
        ]);

        $project->setRelation('groups', collect([$group]));

        return [$project->fresh('groups'), $memberUser, $staffUser];
    }
}
