<?php

use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\DeliverableFileController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\PhaseController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGroupController;
use App\Http\Controllers\ProjectPositionController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\RepositoryProjectController;
use App\Http\Controllers\RepositoryProjectFileController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionEvaluationController;
use App\Http\Controllers\SubmissionFileController;
use App\Http\Controllers\ThematicLineController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserProjectEligibilityController;
use Illuminate\Support\Facades\Route;

Route::scopeBindings()->prefix('pg')->group(function () {
    // Academic Periods routes
    Route::apiResource('academic-periods', AcademicPeriodController::class);

    Route::apiResource('academic-periods.phases', PhaseController::class)->only(['index', 'show', 'update']);

    Route::apiResource('academic-periods.phases.deliverables', DeliverableController::class);

    // Deliverable Files routes (fully scoped under academic periods > phases > deliverables)
    Route::get('deliverable-files', [DeliverableFileController::class, 'getAll']);
    Route::get('academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/files', [DeliverableFileController::class, 'index']);
    Route::post('academic-periods/{academic_period}/phases/{phase}/deliverables/{deliverable}/files', [DeliverableFileController::class, 'store']);

    // Submission Files routes
    Route::get('submission-files', [SubmissionFileController::class, 'getAll']);
    Route::get('submissions/{submission_id}/files', [SubmissionFileController::class, 'index']);
    Route::post('submissions/{submission_id}/files', [SubmissionFileController::class, 'store']);

    // Files routes (handles show/update/delete for all files)
    Route::get('files/dropdown', [FileController::class, 'dropdown']);
    Route::get('files/{file}/download', [FileController::class, 'download']);
    Route::apiResource('files', FileController::class)->except('store', 'create');

    // Submissions routes and nested evaluations
    Route::apiResource('submissions', SubmissionController::class);
    Route::get('submissions/{submission}/evaluations', [SubmissionEvaluationController::class, 'index']);
    Route::post('submissions/{submission}/evaluations', [SubmissionEvaluationController::class, 'store']);
    Route::get('evaluations/{evaluation}', [SubmissionEvaluationController::class, 'show']);
    Route::put('evaluations/{evaluation}', [SubmissionEvaluationController::class, 'update']);
    Route::delete('evaluations/{evaluation}', [SubmissionEvaluationController::class, 'destroy']);

    // Thematic Lines routes
    Route::get('thematic-lines/dropdown', [ThematicLineController::class, 'dropdown']);
    Route::apiResource('thematic-lines', ThematicLineController::class);

    // Rubrics routes
    Route::get('rubrics/dropdown', [RubricController::class, 'dropdown']);
    Route::apiResource('rubrics', RubricController::class);
    Route::post('rubrics/{rubric}/thematic-lines/{thematicLine}', [RubricController::class, 'attachThematicLine']);
    Route::delete('rubrics/{rubric}/thematic-lines/{thematicLine}', [RubricController::class, 'detachThematicLine']);
    Route::post('rubrics/{rubric}/deliverables/{deliverable}', [RubricController::class, 'attachDeliverable']);
    Route::delete('rubrics/{rubric}/deliverables/{deliverable}', [RubricController::class, 'detachDeliverable']);

    Route::middleware('auth.atlas')->group(function () {
        Route::apiResource('proposals', ProposalController::class);
        Route::get('proposals/{proposal}/files', [\App\Http\Controllers\ProposalFileController::class, 'index']);
        Route::post('proposals/{proposal}/files', [\App\Http\Controllers\ProposalFileController::class, 'store']);
    });

    // Projects routes
    Route::get('projects/dropdown', [ProjectController::class, 'dropdown']);
    Route::apiResource('projects', ProjectController::class);

    // Meetings routes
    Route::get('meetings', [MeetingController::class, 'index']);
    Route::get('projects/{project}/meetings', [MeetingController::class, 'projectMeetings']);
    Route::post('projects/{project}/meetings', [MeetingController::class, 'store']);
    Route::get('projects/{project}/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::put('projects/{project}/meetings/{meeting}', [MeetingController::class, 'update']);
    Route::delete('projects/{project}/meetings/{meeting}', [MeetingController::class, 'destroy']);

    // Repository Projects routes
    Route::get('repository-projects', [RepositoryProjectController::class, 'index']);
    Route::post('repository-projects', [RepositoryProjectController::class, 'store']);
    Route::get('repository-projects/{repositoryProject}', [RepositoryProjectController::class, 'show']);
    Route::put('repository-projects/{repositoryProject}', [RepositoryProjectController::class, 'update']);

    // Repository Project Files routes
    Route::get('repository-project-files', [RepositoryProjectFileController::class, 'getAll']);
    Route::get('repository-projects/{repositoryProject}/files', [RepositoryProjectFileController::class, 'index']);
    Route::post('repository-projects/{repositoryProject}/files', [RepositoryProjectFileController::class, 'store']);

    // Project Groups routes
    Route::get('project-groups/dropdown', [ProjectGroupController::class, 'dropdown']);
    Route::apiResource('project-groups', ProjectGroupController::class);

    // Users routes
    Route::get('users/dropdown', [UserController::class, 'dropdown']);
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);

    // Project Positions routes
    Route::get('project-positions/dropdown', [ProjectPositionController::class, 'dropdown']);
    Route::apiResource('project-positions', ProjectPositionController::class);

    // User Project Eligibilities routes
    Route::get('user-project-eligibilities/by-user', [UserProjectEligibilityController::class, 'byUser']);
    Route::get('user-project-eligibilities/by-position', [UserProjectEligibilityController::class, 'byPosition']);
    Route::get('user-project-eligibilities/by-user/dropdown', [UserProjectEligibilityController::class, 'byUserDropdown']);
    Route::get('user-project-eligibilities/by-position/dropdown', [UserProjectEligibilityController::class, 'byPositionDropdown']);
});
