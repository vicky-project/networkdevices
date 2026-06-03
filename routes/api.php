<?php

use Illuminate\Support\Facades\Route;
use Modules\NetworkDevices\Http\Controllers\NetworkDevicesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('networkdevices', NetworkDevicesController::class)->names('networkdevices');
});
