<?php

use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\DeliverableFileController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PhaseController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGroupController;
use App\Http\Controllers\ProjectPositionController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\RepositoryProjectController;
use App\Http\Controllers\RubricController;
use App\Http\Controllers\ThematicLineController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserProjectEligibilityController;
use Illuminate\Support\Facades\Route;

Route::prefix('pg')->group(function () {
    // Academic Periods routes
    Route::get('academic-period-states/dropdown', [AcademicPeriodController::class, 'stateDropdown']);
    Route::get('academic-periods/dropdown', [AcademicPeriodController::class, 'dropdown']);
    Route::apiResource('academic-periods', AcademicPeriodController::class);

    // Phases routes (read-only, no store method)
    Route::get('phases/dropdown', [PhaseController::class, 'dropdown']);
    Route::get('phases', [PhaseController::class, 'index']);
    Route::get('phases/{phase}', [PhaseController::class, 'show']);
    Route::put('phases/{phase}', [PhaseController::class, 'update']);
    Route::delete('phases/{phase}', [PhaseController::class, 'destroy']);

    // Deliverables routes with nested file operations
    Route::get('deliverables/dropdown', [DeliverableController::class, 'dropdown']);
    Route::apiResource('deliverables', DeliverableController::class);

    // Deliverable Files routes (scoped under deliverables - index and store only)
    Route::get('deliverables/{deliverable_id}/files', [DeliverableFileController::class, 'index']);
    Route::post('deliverables/{deliverable_id}/files', [DeliverableFileController::class, 'store']);

    // Files routes (handles show/update/delete for all files)
    Route::get('files/dropdown', [FileController::class, 'dropdown']);
    Route::apiResource('files', FileController::class)->except('store', 'create');

    // Thematic Lines routes
    Route::get('thematic-lines/dropdown', [ThematicLineController::class, 'dropdown']);
    Route::apiResource('thematic-lines', ThematicLineController::class);

    // Rubrics routes
    Route::get('rubrics/dropdown', [RubricController::class, 'dropdown']);
    Route::apiResource('rubrics', RubricController::class);

    Route::middleware('auth.atlas')->group(function () {
        Route::apiResource('proposals', ProposalController::class);
    });

    // Projects routes
    Route::get('projects/dropdown', [ProjectController::class, 'dropdown']);
    Route::apiResource('projects', ProjectController::class);

    // Repository Projects routes
    Route::get('repository-projects', [RepositoryProjectController::class, 'index']);
    Route::post('repository-projects', [RepositoryProjectController::class, 'store']);
    Route::get('repository-projects/{repositoryProject}', [RepositoryProjectController::class, 'show']);
    Route::put('repository-projects/{repositoryProject}', [RepositoryProjectController::class, 'update']);

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
});
