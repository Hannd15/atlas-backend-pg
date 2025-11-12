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
use App\Http\Controllers\RubricController;
use App\Http\Controllers\ThematicLineController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserProjectEligibilityController;
use Illuminate\Support\Facades\Route;

Route::prefix('pg')->group(function () {
    // Academic Periods routes
    Route::get('academic-periods/dropdown', [AcademicPeriodController::class, 'dropdown']);
    Route::apiResource('academic-periods', AcademicPeriodController::class);

    // Phases routes (read-only, no store method)
    Route::get('phases/dropdown', [PhaseController::class, 'dropdown']);
    Route::get('phases', [PhaseController::class, 'index']);
    Route::get('phases/{phase}', [PhaseController::class, 'show']);
    Route::put('phases/{phase}', [PhaseController::class, 'update']);
    Route::delete('phases/{phase}', [PhaseController::class, 'destroy']);

    // Deliverables routes
    Route::get('deliverables/dropdown', [DeliverableController::class, 'dropdown']);
    Route::apiResource('deliverables', DeliverableController::class);

    // Files routes
    Route::get('files/dropdown', [FileController::class, 'dropdown']);
    Route::apiResource('files', FileController::class);

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

    // Project Groups routes
    Route::get('project-groups/dropdown', [ProjectGroupController::class, 'dropdown']);
    Route::apiResource('project-groups', ProjectGroupController::class);

    // Deliverable Files routes (composite key)
    Route::get('deliverable-files', [DeliverableFileController::class, 'index']);
    Route::post('deliverable-files', [DeliverableFileController::class, 'store']);
    Route::get('deliverable-files/{deliverable_id}/{file_id}', [DeliverableFileController::class, 'show']);
    Route::put('deliverable-files/{deliverable_id}/{file_id}', [DeliverableFileController::class, 'update']);
    Route::delete('deliverable-files/{deliverable_id}/{file_id}', [DeliverableFileController::class, 'destroy']);

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
    Route::get('user-project-eligibilities/by-user/dropdown', [UserProjectEligibilityController::class, 'byUserDropdown']);
    Route::get('user-project-eligibilities/by-position', [UserProjectEligibilityController::class, 'byPosition']);
    Route::get('user-project-eligibilities/by-position/dropdown', [UserProjectEligibilityController::class, 'byPositionDropdown']);
});
