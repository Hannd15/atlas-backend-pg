<?php

return [
    'actions' => [
        'noop' => \App\Services\RequestActions\NoOpAction::class,
        'project_group.add_member' => \App\Services\RequestActions\AddProjectGroupMemberAction::class,
        'proposal.committee' => \App\Services\RequestActions\CreateProposalFromApprovalAction::class,
        'proposal.student.director' => \App\Services\RequestActions\ForwardProposalToCommitteeAction::class,
        'project.staff.assign' => \App\Services\RequestActions\AssignProjectStaffAction::class,
    ],
];
