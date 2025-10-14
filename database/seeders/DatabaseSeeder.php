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
            'repository_proposal_files',
            'repository_proposals',
            'repository_project_files',
            'repository_projects',
            'rubric_deliverables',
            'evaluations',
            'submission_files',
            'submissions',
            'deliverable_files',
            'deliverables',
            'files',
            'group_members',
            'project_groups',
            'project_staff',
            'user_project_eligibilities',
            'project_positions',
            'projects',
            'proposals',
            'rubric_thematic_lines',
            'rubrics',
            'thematic_lines',
            'phases',
            'academic_periods',
            'users',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $this->call([
            UserSeeder::class,
            AcademicPeriodSeeder::class,
            PhaseSeeder::class,
            ThematicLineSeeder::class,
            RubricSeeder::class,
            RubricThematicLineSeeder::class,
            ProjectPositionSeeder::class,
            UserProjectEligibilitySeeder::class,
            ProposalSeeder::class,
            ProjectSeeder::class,
            ProjectStaffSeeder::class,
            ProjectGroupSeeder::class,
            GroupMemberSeeder::class,
            FileSeeder::class,
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
