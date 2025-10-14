<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="AcademicPeriod",
 *     required={"name","start_date","end_date"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Phase",
 *     required={"period_id","name","start_date","end_date"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="period_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ThematicLine",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="trl_expected", type="string"),
 *     @OA\Property(property="abet_criteria", type="string"),
 *     @OA\Property(property="min_score", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Rubric",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="min_value", type="integer"),
 *     @OA\Property(property="max_value", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Proposal",
 *     required={"title","proposer_id"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="proposer_id", type="integer"),
 *     @OA\Property(property="preferred_director_id", type="integer"),
 *     @OA\Property(property="thematic_line_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Project",
 *     required={"title"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="proposal_id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroup",
 *     required={"project_id","name"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="GroupMember",
 *     required={"group_id","user_id"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="group_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectPosition",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectStaff",
 *     required={"project_id","user_id","project_position_id"},
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="project_position_id", type="integer"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="UserProjectEligibility",
 *     required={"user_id","project_position_id"},
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="project_position_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Deliverable",
 *     required={"phase_id","name","due_date"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="phase_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="due_date", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="DeliverableFile",
 *     required={"deliverable_id","file_id"},
 *     @OA\Property(property="deliverable_id", type="integer"),
 *     @OA\Property(property="file_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="File",
 *     required={"name","extension","url"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="extension", type="string"),
 *     @OA\Property(property="url", type="string", format="uri"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Submission",
 *     required={"deliverable_id","project_id","submission_date"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="deliverable_id", type="integer"),
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="submission_date", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="SubmissionFile",
 *     required={"submission_id","file_id"},
 *     @OA\Property(property="submission_id", type="integer"),
 *     @OA\Property(property="file_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Evaluation",
 *     required={"submission_id","user_id","evaluator_id","rubric_id","evaluation_date"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="submission_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="evaluator_id", type="integer"),
 *     @OA\Property(property="rubric_id", type="integer"),
 *     @OA\Property(property="grade", type="number", format="float"),
 *     @OA\Property(property="comments", type="string"),
 *     @OA\Property(property="evaluation_date", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RubricThematicLine",
 *     required={"rubric_id","thematic_line_id"},
 *     @OA\Property(property="rubric_id", type="integer"),
 *     @OA\Property(property="thematic_line_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RubricDeliverable",
 *     required={"rubric_id","deliverable_id"},
 *     @OA\Property(property="rubric_id", type="integer"),
 *     @OA\Property(property="deliverable_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProject",
 *     required={"title"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectFile",
 *     required={"repository_item_id","file_id"},
 *     @OA\Property(property="repository_item_id", type="integer"),
 *     @OA\Property(property="file_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProposal",
 *     required={"title"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="proposal_id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProposalFile",
 *     required={"repository_proposal_id","file_id"},
 *     @OA\Property(property="repository_proposal_id", type="integer"),
 *     @OA\Property(property="file_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Meeting",
 *     required={"project_id","meeting_date","created_by"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="project_id", type="integer"),
 *     @OA\Property(property="meeting_date", type="string", format="date"),
 *     @OA\Property(property="observations", type="string"),
 *     @OA\Property(property="created_by", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="MeetingAttendee",
 *     required={"meeting_id","user_id"},
 *     @OA\Property(property="meeting_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Schemas
{
}
