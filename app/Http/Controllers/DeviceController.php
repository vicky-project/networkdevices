<?php

namespace Modules\NetworkDevices\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NetworkDevices\Services\DeviceDiscoveryService;

class DeviceController extends Controller
{
  protected DeviceDiscoveryService $discovery;

  public function __construct(DeviceDiscoveryService $discovery) {
    $this->discovery = $discovery;
    $this->middleware('auth');
  }

  /**
  * Halaman dashboard utama
  */
  public function dashboard() {
    $devices = $this->discovery->getDevices();
    $health = $this->discovery->health();

    return view('networkdevices::dashboard', [
      'devices' => $devices,
      'serviceOnline' => !empty($health['status']) && $health['status'] === 'ok',
      'refreshInterval' => config('networkdevices.refresh_interval', 30),
    ]);
  }

  /**
  * Endpoint API untuk mendapatkan data perangkat (AJAX)
  */
  public function getDevicesAjax() {
    try {
      return response()->json($this->discovery->getDevices());
    } catch(\Exception $e) {
      \Log::error('Failed to get Devices', [
        'message' => $e->getMessage(),
        'trace' => $e->getTrace()
      ]);
      return response()->json([], 500);
    }
  }

  /**
  * Update nama kustom perangkat
  */
  public function updateName(Request $request, string $ip) {
    $request->validate(['name' => 'required|string|max:100']);
    $result = $this->discovery->setCustomName($ip, $request->input('name'));

    return response()->json($result);
  }

  /**
  * Wake-on-LAN
  */
  public function wake(Request $request) {
    $request->validate(['mac' => 'required|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/']);
    $result = $this->discovery->wakeOnLan($request->input('mac'));

    return response()->json($result);
  }

  /**
  * Kontrol perangkat (generik)
  */
  public function control(Request $request) {
    $request->validate([
      'ip' => 'required|ip',
      'action' => 'required|string',
      'params' => 'array',
    ]);

    try {
      $result = $this->discovery->controlDevice(
        $request->input('ip'),
        $request->input('action'),
        $request->input('params', [])
      );

      return response()->json($result);
    } catch(\Exception $e) {
      \Log::error();
      return response()->json([], 500);
    }
  }
}