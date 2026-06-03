<?php

use Illuminate\Support\Facades\Route;
use Modules\NetworkDevices\Http\Controllers\NetworkDevicesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('networkdevices', NetworkDevicesController::class)->names('networkdevices');
});
