<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'meeting_attendees',
            'meetings',
            'repository_project_files',
            'repository_projects',
            'repository_proposal_files',
            'repository_proposals',
            'rubric_deliverables',
            'evaluations',
            'submission_files',
            'submissions',
            'deliverable_files',
            'deliverables',
            'proposal_files',
            'files',
            'group_members',
            'project_groups',
            'project_staff',
            'user_project_eligibilities',
            'project_positions',
            'project_statuses',
            'projects',
            'proposals',
            'proposal_statuses',
            'proposal_types',
            'rubric_thematic_lines',
            'rubrics',
            'thematic_lines',
            'phases',
            'academic_periods',
            'academic_period_states',
            'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->call([
            AcademicPeriodStateSeeder::class,
            AcademicPeriodSeeder::class,
            PhaseSeeder::class,
            ProjectStatusSeeder::class,
            ThematicLineSeeder::class,
            RubricSeeder::class,
            RubricThematicLineSeeder::class,

            ProjectPositionSeeder::class,
            UserProjectEligibilitySeeder::class,
            ProposalTypeSeeder::class,
            ProposalStatusSeeder::class,
            FileSeeder::class,
            ProposalSeeder::class,
            ProposalFileSeeder::class,
            ProjectSeeder::class,
            ProjectStaffSeeder::class,
            ProjectGroupSeeder::class,
            GroupMemberSeeder::class,
            DeliverableSeeder::class,
            DeliverableFileSeeder::class,
            SubmissionSeeder::class,
            SubmissionFileSeeder::class,
            EvaluationSeeder::class,
            RubricDeliverableSeeder::class,
            RepositoryProjectSeeder::class,
            RepositoryProjectFileSeeder::class,
            RepositoryProposalSeeder::class,
            RepositoryProposalFileSeeder::class,
            MeetingSeeder::class,
            MeetingAttendeeSeeder::class,
        ]);
    }
}
