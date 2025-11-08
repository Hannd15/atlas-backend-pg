<?php

use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\DeliverableFileController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PhaseController;
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

    // Deliverable Files routes (composite key)
    Route::get('deliverable-files', [DeliverableFileController::class, 'index']);
    Route::post('deliverable-files', [DeliverableFileController::class, 'store']);
    Route::get('deliverable-files/{deliverable_id}/{file_id}', [DeliverableFileController::class, 'show']);
    Route::delete('deliverable-files/{deliverable_id}/{file_id}', [DeliverableFileController::class, 'destroy']);
});
