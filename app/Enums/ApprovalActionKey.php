<?php

namespace App\Enums;

enum ApprovalActionKey: string
{
    case NoOp = 'noop';

    case ProjectGroupAddMember = 'project_group.add_member';

    case ProposalCommittee = 'proposal.committee';

    case ProposalStudentDirector = 'proposal.student.director';
}