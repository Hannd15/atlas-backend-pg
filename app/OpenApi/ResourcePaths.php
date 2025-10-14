<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

class ResourcePaths
{
    /**
     * @OA\Get(
     *     path="/api/academic-periods",
     *     operationId="listAcademicPeriods",
     *     tags={"Academic Periods"},
     *     summary="List Academic Periods",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Academic Periods",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AcademicPeriod"))
     *         )
     *     )
     * )
     */
    public function academicPeriodsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/academic-periods",
     *     operationId="createAcademicPeriod",
     *     tags={"Academic Periods"},
     *     summary="Create AcademicPeriod",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AcademicPeriod")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/AcademicPeriod"))
     * )
     */
    public function academicPeriodsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/academic-periods/{identifier}",
     *     operationId="showAcademicPeriod",
     *     tags={"Academic Periods"},
     *     summary="Show AcademicPeriod",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/AcademicPeriod")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function academicPeriodsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/academic-periods/{identifier}",
     *     operationId="updateAcademicPeriod",
     *     tags={"Academic Periods"},
     *     summary="Update AcademicPeriod",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AcademicPeriod")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/AcademicPeriod")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function academicPeriodsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/academic-periods/{identifier}",
     *     operationId="deleteAcademicPeriod",
     *     tags={"Academic Periods"},
     *     summary="Delete AcademicPeriod",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function academicPeriodsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/phases",
     *     operationId="listPhases",
     *     tags={"Phases"},
     *     summary="List Phases",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Phases",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Phase"))
     *         )
     *     )
     * )
     */
    public function phasesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/phases",
     *     operationId="createPhase",
     *     tags={"Phases"},
     *     summary="Create Phase",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Phase")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Phase"))
     * )
     */
    public function phasesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/phases/{identifier}",
     *     operationId="showPhase",
     *     tags={"Phases"},
     *     summary="Show Phase",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Phase")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function phasesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/phases/{identifier}",
     *     operationId="updatePhase",
     *     tags={"Phases"},
     *     summary="Update Phase",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Phase")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Phase")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function phasesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/phases/{identifier}",
     *     operationId="deletePhase",
     *     tags={"Phases"},
     *     summary="Delete Phase",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function phasesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/thematic-lines",
     *     operationId="listThematicLines",
     *     tags={"Thematic Lines"},
     *     summary="List Thematic Lines",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Thematic Lines",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ThematicLine"))
     *         )
     *     )
     * )
     */
    public function thematicLinesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/thematic-lines",
     *     operationId="createThematicLine",
     *     tags={"Thematic Lines"},
     *     summary="Create ThematicLine",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ThematicLine")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ThematicLine"))
     * )
     */
    public function thematicLinesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/thematic-lines/{identifier}",
     *     operationId="showThematicLine",
     *     tags={"Thematic Lines"},
     *     summary="Show ThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/ThematicLine")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function thematicLinesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/thematic-lines/{identifier}",
     *     operationId="updateThematicLine",
     *     tags={"Thematic Lines"},
     *     summary="Update ThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ThematicLine")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/ThematicLine")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function thematicLinesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/thematic-lines/{identifier}",
     *     operationId="deleteThematicLine",
     *     tags={"Thematic Lines"},
     *     summary="Delete ThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function thematicLinesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/rubrics",
     *     operationId="listRubrics",
     *     tags={"Rubrics"},
     *     summary="List Rubrics",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Rubrics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Rubric"))
     *         )
     *     )
     * )
     */
    public function rubricsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/rubrics",
     *     operationId="createRubric",
     *     tags={"Rubrics"},
     *     summary="Create Rubric",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Rubric")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Rubric"))
     * )
     */
    public function rubricsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/rubrics/{identifier}",
     *     operationId="showRubric",
     *     tags={"Rubrics"},
     *     summary="Show Rubric",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Rubric")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/rubrics/{identifier}",
     *     operationId="updateRubric",
     *     tags={"Rubrics"},
     *     summary="Update Rubric",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Rubric")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Rubric")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/rubrics/{identifier}",
     *     operationId="deleteRubric",
     *     tags={"Rubrics"},
     *     summary="Delete Rubric",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/proposals",
     *     operationId="listProposals",
     *     tags={"Proposals"},
     *     summary="List Proposals",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Proposals",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Proposal"))
     *         )
     *     )
     * )
     */
    public function proposalsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/proposals",
     *     operationId="createProposal",
     *     tags={"Proposals"},
     *     summary="Create Proposal",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Proposal")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Proposal"))
     * )
     */
    public function proposalsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/proposals/{identifier}",
     *     operationId="showProposal",
     *     tags={"Proposals"},
     *     summary="Show Proposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Proposal")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function proposalsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/proposals/{identifier}",
     *     operationId="updateProposal",
     *     tags={"Proposals"},
     *     summary="Update Proposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Proposal")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Proposal")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function proposalsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/proposals/{identifier}",
     *     operationId="deleteProposal",
     *     tags={"Proposals"},
     *     summary="Delete Proposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function proposalsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/projects",
     *     operationId="listProjects",
     *     tags={"Projects"},
     *     summary="List Projects",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Projects",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Project"))
     *         )
     *     )
     * )
     */
    public function projectsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/projects",
     *     operationId="createProject",
     *     tags={"Projects"},
     *     summary="Create Project",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Project")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Project"))
     * )
     */
    public function projectsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/projects/{identifier}",
     *     operationId="showProject",
     *     tags={"Projects"},
     *     summary="Show Project",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Project")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/projects/{identifier}",
     *     operationId="updateProject",
     *     tags={"Projects"},
     *     summary="Update Project",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Project")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Project")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/projects/{identifier}",
     *     operationId="deleteProject",
     *     tags={"Projects"},
     *     summary="Delete Project",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/project-groups",
     *     operationId="listProjectGroups",
     *     tags={"Project Groups"},
     *     summary="List Project Groups",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Project Groups",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProjectGroup"))
     *         )
     *     )
     * )
     */
    public function projectGroupsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/project-groups",
     *     operationId="createProjectGroup",
     *     tags={"Project Groups"},
     *     summary="Create ProjectGroup",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectGroup")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ProjectGroup"))
     * )
     */
    public function projectGroupsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/project-groups/{identifier}",
     *     operationId="showProjectGroup",
     *     tags={"Project Groups"},
     *     summary="Show ProjectGroup",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/ProjectGroup")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectGroupsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/project-groups/{identifier}",
     *     operationId="updateProjectGroup",
     *     tags={"Project Groups"},
     *     summary="Update ProjectGroup",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectGroup")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/ProjectGroup")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectGroupsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/project-groups/{identifier}",
     *     operationId="deleteProjectGroup",
     *     tags={"Project Groups"},
     *     summary="Delete ProjectGroup",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectGroupsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/group-members",
     *     operationId="listGroupMembers",
     *     tags={"Group Members"},
     *     summary="List Group Members",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Group Members",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/GroupMember"))
     *         )
     *     )
     * )
     */
    public function groupMembersIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/group-members",
     *     operationId="createGroupMember",
     *     tags={"Group Members"},
     *     summary="Create GroupMember",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/GroupMember")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/GroupMember"))
     * )
     */
    public function groupMembersStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/group-members/{identifier}",
     *     operationId="showGroupMember",
     *     tags={"Group Members"},
     *     summary="Show GroupMember",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/GroupMember")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function groupMembersShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/group-members/{identifier}",
     *     operationId="updateGroupMember",
     *     tags={"Group Members"},
     *     summary="Update GroupMember",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/GroupMember")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/GroupMember")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function groupMembersUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/group-members/{identifier}",
     *     operationId="deleteGroupMember",
     *     tags={"Group Members"},
     *     summary="Delete GroupMember",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function groupMembersDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/project-positions",
     *     operationId="listProjectPositions",
     *     tags={"Project Positions"},
     *     summary="List Project Positions",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Project Positions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProjectPosition"))
     *         )
     *     )
     * )
     */
    public function projectPositionsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/project-positions",
     *     operationId="createProjectPosition",
     *     tags={"Project Positions"},
     *     summary="Create ProjectPosition",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectPosition")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ProjectPosition"))
     * )
     */
    public function projectPositionsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/project-positions/{identifier}",
     *     operationId="showProjectPosition",
     *     tags={"Project Positions"},
     *     summary="Show ProjectPosition",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/ProjectPosition")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectPositionsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/project-positions/{identifier}",
     *     operationId="updateProjectPosition",
     *     tags={"Project Positions"},
     *     summary="Update ProjectPosition",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectPosition")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/ProjectPosition")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectPositionsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/project-positions/{identifier}",
     *     operationId="deleteProjectPosition",
     *     tags={"Project Positions"},
     *     summary="Delete ProjectPosition",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectPositionsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/project-staff",
     *     operationId="listProjectStaff",
     *     tags={"Project Staff"},
     *     summary="List Project Staff",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Project Staff",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProjectStaff"))
     *         )
     *     )
     * )
     */
    public function projectStaffIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/project-staff",
     *     operationId="createProjectStaff",
     *     tags={"Project Staff"},
     *     summary="Create ProjectStaff",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectStaff")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ProjectStaff"))
     * )
     */
    public function projectStaffStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/project-staff/{identifier}",
     *     operationId="showProjectStaff",
     *     tags={"Project Staff"},
     *     summary="Show ProjectStaff",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of project_id, user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/ProjectStaff")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectStaffShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/project-staff/{identifier}",
     *     operationId="updateProjectStaff",
     *     tags={"Project Staff"},
     *     summary="Update ProjectStaff",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of project_id, user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProjectStaff")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/ProjectStaff")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectStaffUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/project-staff/{identifier}",
     *     operationId="deleteProjectStaff",
     *     tags={"Project Staff"},
     *     summary="Delete ProjectStaff",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of project_id, user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function projectStaffDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/user-project-eligibilities",
     *     operationId="listUserProjectEligibilities",
     *     tags={"User Project Eligibilities"},
     *     summary="List User Project Eligibilities",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated User Project Eligibilities",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserProjectEligibility"))
     *         )
     *     )
     * )
     */
    public function userProjectEligibilitiesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/user-project-eligibilities",
     *     operationId="createUserProjectEligibility",
     *     tags={"User Project Eligibilities"},
     *     summary="Create UserProjectEligibility",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UserProjectEligibility")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/UserProjectEligibility"))
     * )
     */
    public function userProjectEligibilitiesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/user-project-eligibilities/{identifier}",
     *     operationId="showUserProjectEligibility",
     *     tags={"User Project Eligibilities"},
     *     summary="Show UserProjectEligibility",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/UserProjectEligibility")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function userProjectEligibilitiesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/user-project-eligibilities/{identifier}",
     *     operationId="updateUserProjectEligibility",
     *     tags={"User Project Eligibilities"},
     *     summary="Update UserProjectEligibility",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UserProjectEligibility")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/UserProjectEligibility")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function userProjectEligibilitiesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/user-project-eligibilities/{identifier}",
     *     operationId="deleteUserProjectEligibility",
     *     tags={"User Project Eligibilities"},
     *     summary="Delete UserProjectEligibility",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of user_id, project_position_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function userProjectEligibilitiesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/deliverables",
     *     operationId="listDeliverables",
     *     tags={"Deliverables"},
     *     summary="List Deliverables",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Deliverables",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Deliverable"))
     *         )
     *     )
     * )
     */
    public function deliverablesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/deliverables",
     *     operationId="createDeliverable",
     *     tags={"Deliverables"},
     *     summary="Create Deliverable",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Deliverable")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Deliverable"))
     * )
     */
    public function deliverablesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/deliverables/{identifier}",
     *     operationId="showDeliverable",
     *     tags={"Deliverables"},
     *     summary="Show Deliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Deliverable")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverablesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/deliverables/{identifier}",
     *     operationId="updateDeliverable",
     *     tags={"Deliverables"},
     *     summary="Update Deliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Deliverable")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Deliverable")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverablesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/deliverables/{identifier}",
     *     operationId="deleteDeliverable",
     *     tags={"Deliverables"},
     *     summary="Delete Deliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverablesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/deliverable-files",
     *     operationId="listDeliverableFiles",
     *     tags={"Deliverable Files"},
     *     summary="List Deliverable Files",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Deliverable Files",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DeliverableFile"))
     *         )
     *     )
     * )
     */
    public function deliverableFilesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/deliverable-files",
     *     operationId="createDeliverableFile",
     *     tags={"Deliverable Files"},
     *     summary="Create DeliverableFile",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeliverableFile")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/DeliverableFile"))
     * )
     */
    public function deliverableFilesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/deliverable-files/{identifier}",
     *     operationId="showDeliverableFile",
     *     tags={"Deliverable Files"},
     *     summary="Show DeliverableFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of deliverable_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/DeliverableFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverableFilesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/deliverable-files/{identifier}",
     *     operationId="updateDeliverableFile",
     *     tags={"Deliverable Files"},
     *     summary="Update DeliverableFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of deliverable_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeliverableFile")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/DeliverableFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverableFilesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/deliverable-files/{identifier}",
     *     operationId="deleteDeliverableFile",
     *     tags={"Deliverable Files"},
     *     summary="Delete DeliverableFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of deliverable_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function deliverableFilesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/files",
     *     operationId="listFiles",
     *     tags={"Files"},
     *     summary="List Files",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Files",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/File"))
     *         )
     *     )
     * )
     */
    public function filesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/files",
     *     operationId="createFile",
     *     tags={"Files"},
     *     summary="Create File",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/File")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/File"))
     * )
     */
    public function filesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/files/{identifier}",
     *     operationId="showFile",
     *     tags={"Files"},
     *     summary="Show File",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/File")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function filesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/files/{identifier}",
     *     operationId="updateFile",
     *     tags={"Files"},
     *     summary="Update File",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/File")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/File")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function filesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/files/{identifier}",
     *     operationId="deleteFile",
     *     tags={"Files"},
     *     summary="Delete File",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function filesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/submissions",
     *     operationId="listSubmissions",
     *     tags={"Submissions"},
     *     summary="List Submissions",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Submissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Submission"))
     *         )
     *     )
     * )
     */
    public function submissionsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/submissions",
     *     operationId="createSubmission",
     *     tags={"Submissions"},
     *     summary="Create Submission",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Submission")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Submission"))
     * )
     */
    public function submissionsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/submissions/{identifier}",
     *     operationId="showSubmission",
     *     tags={"Submissions"},
     *     summary="Show Submission",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Submission")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/submissions/{identifier}",
     *     operationId="updateSubmission",
     *     tags={"Submissions"},
     *     summary="Update Submission",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Submission")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Submission")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/submissions/{identifier}",
     *     operationId="deleteSubmission",
     *     tags={"Submissions"},
     *     summary="Delete Submission",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/submission-files",
     *     operationId="listSubmissionFiles",
     *     tags={"Submission Files"},
     *     summary="List Submission Files",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Submission Files",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SubmissionFile"))
     *         )
     *     )
     * )
     */
    public function submissionFilesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/submission-files",
     *     operationId="createSubmissionFile",
     *     tags={"Submission Files"},
     *     summary="Create SubmissionFile",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SubmissionFile")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/SubmissionFile"))
     * )
     */
    public function submissionFilesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/submission-files/{identifier}",
     *     operationId="showSubmissionFile",
     *     tags={"Submission Files"},
     *     summary="Show SubmissionFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of submission_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/SubmissionFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionFilesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/submission-files/{identifier}",
     *     operationId="updateSubmissionFile",
     *     tags={"Submission Files"},
     *     summary="Update SubmissionFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of submission_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SubmissionFile")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/SubmissionFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionFilesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/submission-files/{identifier}",
     *     operationId="deleteSubmissionFile",
     *     tags={"Submission Files"},
     *     summary="Delete SubmissionFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of submission_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submissionFilesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/evaluations",
     *     operationId="listEvaluations",
     *     tags={"Evaluations"},
     *     summary="List Evaluations",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Evaluations",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Evaluation"))
     *         )
     *     )
     * )
     */
    public function evaluationsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/evaluations",
     *     operationId="createEvaluation",
     *     tags={"Evaluations"},
     *     summary="Create Evaluation",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Evaluation")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Evaluation"))
     * )
     */
    public function evaluationsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/evaluations/{identifier}",
     *     operationId="showEvaluation",
     *     tags={"Evaluations"},
     *     summary="Show Evaluation",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Evaluation")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function evaluationsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/evaluations/{identifier}",
     *     operationId="updateEvaluation",
     *     tags={"Evaluations"},
     *     summary="Update Evaluation",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Evaluation")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Evaluation")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function evaluationsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/evaluations/{identifier}",
     *     operationId="deleteEvaluation",
     *     tags={"Evaluations"},
     *     summary="Delete Evaluation",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function evaluationsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/rubric-thematic-lines",
     *     operationId="listRubricThematicLines",
     *     tags={"Rubric Thematic Lines"},
     *     summary="List Rubric Thematic Lines",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Rubric Thematic Lines",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RubricThematicLine"))
     *         )
     *     )
     * )
     */
    public function rubricThematicLinesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/rubric-thematic-lines",
     *     operationId="createRubricThematicLine",
     *     tags={"Rubric Thematic Lines"},
     *     summary="Create RubricThematicLine",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RubricThematicLine")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RubricThematicLine"))
     * )
     */
    public function rubricThematicLinesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/rubric-thematic-lines/{identifier}",
     *     operationId="showRubricThematicLine",
     *     tags={"Rubric Thematic Lines"},
     *     summary="Show RubricThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, thematic_line_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RubricThematicLine")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricThematicLinesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/rubric-thematic-lines/{identifier}",
     *     operationId="updateRubricThematicLine",
     *     tags={"Rubric Thematic Lines"},
     *     summary="Update RubricThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, thematic_line_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RubricThematicLine")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RubricThematicLine")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricThematicLinesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/rubric-thematic-lines/{identifier}",
     *     operationId="deleteRubricThematicLine",
     *     tags={"Rubric Thematic Lines"},
     *     summary="Delete RubricThematicLine",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, thematic_line_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricThematicLinesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/rubric-deliverables",
     *     operationId="listRubricDeliverables",
     *     tags={"Rubric Deliverables"},
     *     summary="List Rubric Deliverables",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Rubric Deliverables",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RubricDeliverable"))
     *         )
     *     )
     * )
     */
    public function rubricDeliverablesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/rubric-deliverables",
     *     operationId="createRubricDeliverable",
     *     tags={"Rubric Deliverables"},
     *     summary="Create RubricDeliverable",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RubricDeliverable")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RubricDeliverable"))
     * )
     */
    public function rubricDeliverablesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/rubric-deliverables/{identifier}",
     *     operationId="showRubricDeliverable",
     *     tags={"Rubric Deliverables"},
     *     summary="Show RubricDeliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, deliverable_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RubricDeliverable")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricDeliverablesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/rubric-deliverables/{identifier}",
     *     operationId="updateRubricDeliverable",
     *     tags={"Rubric Deliverables"},
     *     summary="Update RubricDeliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, deliverable_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RubricDeliverable")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RubricDeliverable")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricDeliverablesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/rubric-deliverables/{identifier}",
     *     operationId="deleteRubricDeliverable",
     *     tags={"Rubric Deliverables"},
     *     summary="Delete RubricDeliverable",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of rubric_id, deliverable_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function rubricDeliverablesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/repository-projects",
     *     operationId="listRepositoryProjects",
     *     tags={"Repository Projects"},
     *     summary="List Repository Projects",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Repository Projects",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RepositoryProject"))
     *         )
     *     )
     * )
     */
    public function repositoryProjectsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/repository-projects",
     *     operationId="createRepositoryProject",
     *     tags={"Repository Projects"},
     *     summary="Create RepositoryProject",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProject")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RepositoryProject"))
     * )
     */
    public function repositoryProjectsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/repository-projects/{identifier}",
     *     operationId="showRepositoryProject",
     *     tags={"Repository Projects"},
     *     summary="Show RepositoryProject",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RepositoryProject")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/repository-projects/{identifier}",
     *     operationId="updateRepositoryProject",
     *     tags={"Repository Projects"},
     *     summary="Update RepositoryProject",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProject")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProject")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/repository-projects/{identifier}",
     *     operationId="deleteRepositoryProject",
     *     tags={"Repository Projects"},
     *     summary="Delete RepositoryProject",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/repository-project-files",
     *     operationId="listRepositoryProjectFiles",
     *     tags={"Repository Project Files"},
     *     summary="List Repository Project Files",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Repository Project Files",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RepositoryProjectFile"))
     *         )
     *     )
     * )
     */
    public function repositoryProjectFilesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/repository-project-files",
     *     operationId="createRepositoryProjectFile",
     *     tags={"Repository Project Files"},
     *     summary="Create RepositoryProjectFile",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFile")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFile"))
     * )
     */
    public function repositoryProjectFilesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/repository-project-files/{identifier}",
     *     operationId="showRepositoryProjectFile",
     *     tags={"Repository Project Files"},
     *     summary="Show RepositoryProjectFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_item_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectFilesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/repository-project-files/{identifier}",
     *     operationId="updateRepositoryProjectFile",
     *     tags={"Repository Project Files"},
     *     summary="Update RepositoryProjectFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_item_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFile")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectFilesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/repository-project-files/{identifier}",
     *     operationId="deleteRepositoryProjectFile",
     *     tags={"Repository Project Files"},
     *     summary="Delete RepositoryProjectFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_item_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProjectFilesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/repository-proposals",
     *     operationId="listRepositoryProposals",
     *     tags={"Repository Proposals"},
     *     summary="List Repository Proposals",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Repository Proposals",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RepositoryProposal"))
     *         )
     *     )
     * )
     */
    public function repositoryProposalsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/repository-proposals",
     *     operationId="createRepositoryProposal",
     *     tags={"Repository Proposals"},
     *     summary="Create RepositoryProposal",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProposal")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RepositoryProposal"))
     * )
     */
    public function repositoryProposalsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/repository-proposals/{identifier}",
     *     operationId="showRepositoryProposal",
     *     tags={"Repository Proposals"},
     *     summary="Show RepositoryProposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RepositoryProposal")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/repository-proposals/{identifier}",
     *     operationId="updateRepositoryProposal",
     *     tags={"Repository Proposals"},
     *     summary="Update RepositoryProposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProposal")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProposal")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/repository-proposals/{identifier}",
     *     operationId="deleteRepositoryProposal",
     *     tags={"Repository Proposals"},
     *     summary="Delete RepositoryProposal",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/repository-proposal-files",
     *     operationId="listRepositoryProposalFiles",
     *     tags={"Repository Proposal Files"},
     *     summary="List Repository Proposal Files",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Repository Proposal Files",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RepositoryProposalFile"))
     *         )
     *     )
     * )
     */
    public function repositoryProposalFilesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/repository-proposal-files",
     *     operationId="createRepositoryProposalFile",
     *     tags={"Repository Proposal Files"},
     *     summary="Create RepositoryProposalFile",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProposalFile")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/RepositoryProposalFile"))
     * )
     */
    public function repositoryProposalFilesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/repository-proposal-files/{identifier}",
     *     operationId="showRepositoryProposalFile",
     *     tags={"Repository Proposal Files"},
     *     summary="Show RepositoryProposalFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_proposal_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/RepositoryProposalFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalFilesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/repository-proposal-files/{identifier}",
     *     operationId="updateRepositoryProposalFile",
     *     tags={"Repository Proposal Files"},
     *     summary="Update RepositoryProposalFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_proposal_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RepositoryProposalFile")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProposalFile")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalFilesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/repository-proposal-files/{identifier}",
     *     operationId="deleteRepositoryProposalFile",
     *     tags={"Repository Proposal Files"},
     *     summary="Delete RepositoryProposalFile",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of repository_proposal_id, file_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function repositoryProposalFilesDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/meetings",
     *     operationId="listMeetings",
     *     tags={"Meetings"},
     *     summary="List Meetings",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Meetings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Meeting"))
     *         )
     *     )
     * )
     */
    public function meetingsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/meetings",
     *     operationId="createMeeting",
     *     tags={"Meetings"},
     *     summary="Create Meeting",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Meeting")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/Meeting"))
     * )
     */
    public function meetingsStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/meetings/{identifier}",
     *     operationId="showMeeting",
     *     tags={"Meetings"},
     *     summary="Show Meeting",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/Meeting")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingsShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/meetings/{identifier}",
     *     operationId="updateMeeting",
     *     tags={"Meetings"},
     *     summary="Update Meeting",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Meeting")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Meeting")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingsUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/meetings/{identifier}",
     *     operationId="deleteMeeting",
     *     tags={"Meetings"},
     *     summary="Delete Meeting",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Resource identifier", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingsDestroy(): void {}
    /**
     * @OA\Get(
     *     path="/api/meeting-attendees",
     *     operationId="listMeetingAttendees",
     *     tags={"Meeting Attendees"},
     *     summary="List Meeting Attendees",
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated Meeting Attendees",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MeetingAttendee"))
     *         )
     *     )
     * )
     */
    public function meetingAttendeesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/api/meeting-attendees",
     *     operationId="createMeetingAttendee",
     *     tags={"Meeting Attendees"},
     *     summary="Create MeetingAttendee",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MeetingAttendee")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/MeetingAttendee"))
     * )
     */
    public function meetingAttendeesStore(): void {}

    /**
     * @OA\Get(
     *     path="/api/meeting-attendees/{identifier}",
     *     operationId="showMeetingAttendee",
     *     tags={"Meeting Attendees"},
     *     summary="Show MeetingAttendee",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of meeting_id, user_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with", in="query", description="Relations to eager load", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource details", @OA\JsonContent(ref="#/components/schemas/MeetingAttendee")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingAttendeesShow(): void {}

    /**
     * @OA\Put(
     *     path="/api/meeting-attendees/{identifier}",
     *     operationId="updateMeetingAttendee",
     *     tags={"Meeting Attendees"},
     *     summary="Update MeetingAttendee",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of meeting_id, user_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MeetingAttendee")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/MeetingAttendee")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingAttendeesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/api/meeting-attendees/{identifier}",
     *     operationId="deleteMeetingAttendee",
     *     tags={"Meeting Attendees"},
     *     summary="Delete MeetingAttendee",
     *     @OA\Parameter(name="identifier", in="path", required=true, description="Composite identifier composed of meeting_id, user_id separated by pipe (|).", @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function meetingAttendeesDestroy(): void {}
}
