<?php

return [
    'academic-periods' => [
        'model' => App\Models\AcademicPeriod::class,
        'tag' => 'Academic Periods',
        'rules' => [
            'store' => [
                'name' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ],
            'update' => [
                'name' => 'sometimes|string|max:255',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
            ],
        ],
    ],

    'phases' => [
        'model' => App\Models\Phase::class,
        'tag' => 'Phases',
        'rules' => [
            'store' => [
                'period_id' => 'required|exists:academic_periods,id',
                'name' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ],
            'update' => [
                'period_id' => 'sometimes|exists:academic_periods,id',
                'name' => 'sometimes|string|max:255',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
            ],
        ],
    ],

    'thematic-lines' => [
        'model' => App\Models\ThematicLine::class,
        'tag' => 'Thematic Lines',
        'rules' => [
            'store' => [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'trl_expected' => 'nullable|string|max:255',
                'abet_criteria' => 'nullable|string',
                'min_score' => 'nullable|integer|min:0',
            ],
            'update' => [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'trl_expected' => 'nullable|string|max:255',
                'abet_criteria' => 'nullable|string',
                'min_score' => 'nullable|integer|min:0',
            ],
        ],
    ],

    'rubrics' => [
        'model' => App\Models\Rubric::class,
        'tag' => 'Rubrics',
        'rules' => [
            'store' => [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'min_value' => 'nullable|integer',
                'max_value' => 'nullable|integer|gte:min_value',
            ],
            'update' => [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'min_value' => 'nullable|integer',
                'max_value' => 'nullable|integer|gte:min_value',
            ],
        ],
    ],

    'proposals' => [
        'model' => App\Models\Proposal::class,
        'tag' => 'Proposals',
        'rules' => [
            'store' => [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|max:50',
                'type' => 'nullable|string|max:100',
                'proposer_id' => 'required|exists:users,id',
                'preferred_director_id' => 'nullable|exists:users,id',
                'thematic_line_id' => 'nullable|exists:thematic_lines,id',
            ],
            'update' => [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|max:50',
                'type' => 'nullable|string|max:100',
                'proposer_id' => 'sometimes|exists:users,id',
                'preferred_director_id' => 'nullable|exists:users,id',
                'thematic_line_id' => 'nullable|exists:thematic_lines,id',
            ],
        ],
    ],

    'projects' => [
        'model' => App\Models\Project::class,
        'tag' => 'Projects',
        'rules' => [
            'store' => [
                'proposal_id' => 'nullable|exists:proposals,id',
                'title' => 'required|string|max:255',
                'status' => 'nullable|string|max:50',
            ],
            'update' => [
                'proposal_id' => 'nullable|exists:proposals,id',
                'title' => 'sometimes|string|max:255',
                'status' => 'nullable|string|max:50',
            ],
        ],
    ],

    'project-groups' => [
        'model' => App\Models\ProjectGroup::class,
        'tag' => 'Project Groups',
        'rules' => [
            'store' => [
                'project_id' => 'required|exists:projects,id',
                'name' => 'required|string|max:255',
            ],
            'update' => [
                'project_id' => 'sometimes|exists:projects,id',
                'name' => 'sometimes|string|max:255',
            ],
        ],
    ],

    'group-members' => [
        'model' => App\Models\GroupMember::class,
        'tag' => 'Group Members',
        'rules' => [
            'store' => [
                'group_id' => 'required|exists:project_groups,id',
                'user_id' => 'required|exists:users,id',
            ],
            'update' => [
                'group_id' => 'sometimes|exists:project_groups,id',
                'user_id' => 'sometimes|exists:users,id',
            ],
        ],
    ],

    'project-positions' => [
        'model' => App\Models\ProjectPosition::class,
        'tag' => 'Project Positions',
        'rules' => [
            'store' => [
                'name' => 'required|string|max:255|unique:project_positions,name',
            ],
            'update' => [
                'name' => 'sometimes|string|max:255',
            ],
        ],
    ],

    'project-staff' => [
        'model' => App\Models\ProjectStaff::class,
        'tag' => 'Project Staff',
        'composite_key' => ['project_id', 'user_id', 'project_position_id'],
        'immutable' => ['project_id', 'user_id', 'project_position_id'],
        'rules' => [
            'store' => [
                'project_id' => 'required|exists:projects,id',
                'user_id' => 'required|exists:users,id',
                'project_position_id' => 'required|exists:project_positions,id',
                'status' => 'nullable|string|max:50',
            ],
            'update' => [
                'status' => 'sometimes|string|max:50',
            ],
        ],
    ],

    'user-project-eligibilities' => [
        'model' => App\Models\UserProjectEligibility::class,
        'tag' => 'User Project Eligibilities',
        'composite_key' => ['user_id', 'project_position_id'],
        'immutable' => ['user_id', 'project_position_id'],
        'rules' => [
            'store' => [
                'user_id' => 'required|exists:users,id',
                'project_position_id' => 'required|exists:project_positions,id',
            ],
            'update' => [],
        ],
    ],

    'deliverables' => [
        'model' => App\Models\Deliverable::class,
        'tag' => 'Deliverables',
        'rules' => [
            'store' => [
                'phase_id' => 'required|exists:phases,id',
                'name' => 'required|string|max:255',
                'due_date' => 'required|date',
            ],
            'update' => [
                'phase_id' => 'sometimes|exists:phases,id',
                'name' => 'sometimes|string|max:255',
                'due_date' => 'sometimes|date',
            ],
        ],
    ],

    'deliverable-files' => [
        'model' => App\Models\DeliverableFile::class,
        'tag' => 'Deliverable Files',
        'composite_key' => ['deliverable_id', 'file_id'],
        'immutable' => ['deliverable_id', 'file_id'],
        'rules' => [
            'store' => [
                'deliverable_id' => 'required|exists:deliverables,id',
                'file_id' => 'required|exists:files,id',
            ],
            'update' => [],
        ],
    ],

    'files' => [
        'model' => App\Models\File::class,
        'tag' => 'Files',
        'rules' => [
            'store' => [
                'name' => 'required|string|max:255',
                'extension' => 'required|string|max:10',
                'url' => 'required|url',
            ],
            'update' => [
                'name' => 'sometimes|string|max:255',
                'extension' => 'sometimes|string|max:10',
                'url' => 'sometimes|url',
            ],
        ],
    ],

    'submissions' => [
        'model' => App\Models\Submission::class,
        'tag' => 'Submissions',
        'rules' => [
            'store' => [
                'deliverable_id' => 'required|exists:deliverables,id',
                'project_id' => 'required|exists:projects,id',
                'submission_date' => 'required|date',
            ],
            'update' => [
                'deliverable_id' => 'sometimes|exists:deliverables,id',
                'project_id' => 'sometimes|exists:projects,id',
                'submission_date' => 'sometimes|date',
            ],
        ],
    ],

    'submission-files' => [
        'model' => App\Models\SubmissionFile::class,
        'tag' => 'Submission Files',
        'composite_key' => ['submission_id', 'file_id'],
        'immutable' => ['submission_id', 'file_id'],
        'rules' => [
            'store' => [
                'submission_id' => 'required|exists:submissions,id',
                'file_id' => 'required|exists:files,id',
            ],
            'update' => [],
        ],
    ],

    'evaluations' => [
        'model' => App\Models\Evaluation::class,
        'tag' => 'Evaluations',
        'rules' => [
            'store' => [
                'submission_id' => 'required|exists:submissions,id',
                'user_id' => 'required|exists:users,id',
                'evaluator_id' => 'required|exists:users,id',
                'rubric_id' => 'required|exists:rubrics,id',
                'grade' => 'nullable|numeric|min:0',
                'comments' => 'nullable|string',
                'evaluation_date' => 'required|date',
            ],
            'update' => [
                'submission_id' => 'sometimes|exists:submissions,id',
                'user_id' => 'sometimes|exists:users,id',
                'evaluator_id' => 'sometimes|exists:users,id',
                'rubric_id' => 'sometimes|exists:rubrics,id',
                'grade' => 'nullable|numeric|min:0',
                'comments' => 'nullable|string',
                'evaluation_date' => 'sometimes|date',
            ],
        ],
    ],

    'rubric-thematic-lines' => [
        'model' => App\Models\RubricThematicLine::class,
        'tag' => 'Rubric Thematic Lines',
        'composite_key' => ['rubric_id', 'thematic_line_id'],
        'immutable' => ['rubric_id', 'thematic_line_id'],
        'rules' => [
            'store' => [
                'rubric_id' => 'required|exists:rubrics,id',
                'thematic_line_id' => 'required|exists:thematic_lines,id',
            ],
            'update' => [],
        ],
    ],

    'rubric-deliverables' => [
        'model' => App\Models\RubricDeliverable::class,
        'tag' => 'Rubric Deliverables',
        'composite_key' => ['rubric_id', 'deliverable_id'],
        'immutable' => ['rubric_id', 'deliverable_id'],
        'rules' => [
            'store' => [
                'rubric_id' => 'required|exists:rubrics,id',
                'deliverable_id' => 'required|exists:deliverables,id',
            ],
            'update' => [],
        ],
    ],

    'repository-projects' => [
        'model' => App\Models\RepositoryProject::class,
        'tag' => 'Repository Projects',
        'rules' => [
            'store' => [
                'project_id' => 'nullable|exists:projects,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
            ],
            'update' => [
                'project_id' => 'nullable|exists:projects,id',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ],
        ],
    ],

    'repository-project-files' => [
        'model' => App\Models\RepositoryProjectFile::class,
        'tag' => 'Repository Project Files',
        'composite_key' => ['repository_item_id', 'file_id'],
        'immutable' => ['repository_item_id', 'file_id'],
        'rules' => [
            'store' => [
                'repository_item_id' => 'required|exists:repository_projects,id',
                'file_id' => 'required|exists:files,id',
            ],
            'update' => [],
        ],
    ],

    'repository-proposals' => [
        'model' => App\Models\RepositoryProposal::class,
        'tag' => 'Repository Proposals',
        'rules' => [
            'store' => [
                'proposal_id' => 'nullable|exists:proposals,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
            ],
            'update' => [
                'proposal_id' => 'nullable|exists:proposals,id',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ],
        ],
    ],

    'repository-proposal-files' => [
        'model' => App\Models\RepositoryProposalFile::class,
        'tag' => 'Repository Proposal Files',
        'composite_key' => ['repository_proposal_id', 'file_id'],
        'immutable' => ['repository_proposal_id', 'file_id'],
        'rules' => [
            'store' => [
                'repository_proposal_id' => 'required|exists:repository_proposals,id',
                'file_id' => 'required|exists:files,id',
            ],
            'update' => [],
        ],
    ],

    'meetings' => [
        'model' => App\Models\Meeting::class,
        'tag' => 'Meetings',
        'rules' => [
            'store' => [
                'project_id' => 'required|exists:projects,id',
                'meeting_date' => 'required|date',
                'observations' => 'nullable|string',
                'created_by' => 'required|exists:users,id',
            ],
            'update' => [
                'project_id' => 'sometimes|exists:projects,id',
                'meeting_date' => 'sometimes|date',
                'observations' => 'nullable|string',
                'created_by' => 'sometimes|exists:users,id',
            ],
        ],
    ],

    'meeting-attendees' => [
        'model' => App\Models\MeetingAttendee::class,
        'tag' => 'Meeting Attendees',
        'composite_key' => ['meeting_id', 'user_id'],
        'immutable' => ['meeting_id', 'user_id'],
        'rules' => [
            'store' => [
                'meeting_id' => 'required|exists:meetings,id',
                'user_id' => 'required|exists:users,id',
            ],
            'update' => [],
        ],
    ],
];
