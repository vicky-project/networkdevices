<?php

use Illuminate\Support\Facades\Route;
use Modules\NetworkDevices\Http\Controllers\DeviceController;

Route::middleware(['web', 'auth'])->prefix('network')->group(function () {
  Route::get('/dashboard', [DeviceController::class, 'dashboard'])->name('network.dashboard');
  Route::get('/devices', [DeviceController::class, 'getDevicesAjax'])->name('network.devices.ajax');
  Route::post('/devices/{ip}/name', [DeviceController::class, 'updateName'])->name('network.devices.name');
  Route::post('/wake', [DeviceController::class, 'wake'])->name('network.wake');
  Route::post('/control', [DeviceController::class, 'control'])->name('network.control');
});