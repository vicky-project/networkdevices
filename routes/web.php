<?php

use Illuminate\Support\Facades\Route;
use Modules\NetworkDevices\Http\Controllers\DeviceController;
use Modules\NetworkDevices\Constants\Permission;

Route::middleware(['web', 'auth'])->prefix('admin/network')->name('admin.network.')->group(function () {
  // Dashboard
  Route::get('/dashboard', [DeviceController::class, 'dashboard'])
  ->name('dashboard')
  ->middleware('permission:' . Permission::VIEW_DEVICES);

  // AJAX data devices
  Route::get('/devices', [DeviceController::class, 'getDevicesAjax'])
  ->name('devices.ajax')->middleware('permission:'. Permission::VIEW_DEVICES);

  // Wake on LAN
  Route::post('/wake', [DeviceController::class, 'wake'])
  ->name('wake');

  // Kontrol generik
  Route::post('/control', [DeviceController::class, 'control'])
  ->name('control');

  // Update nama
  Route::post('/devices/{ip}/name', [DeviceController::class, 'updateName'])
  ->name('devices.name')
  ->middleware('permission:' . Permission::MANAGE_DEVICES);
});