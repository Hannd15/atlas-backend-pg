<?php

use App\Http\Controllers\Api\ResourceController;
use Illuminate\Support\Facades\Route;

$resources = array_keys(config('api-resources'));

foreach ($resources as $resource) {
    Route::apiResource($resource, ResourceController::class)
        ->parameter($resource, 'identifier');
}
