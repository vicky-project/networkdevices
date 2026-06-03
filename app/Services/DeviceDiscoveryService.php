<?php

namespace Modules\NetworkDevices\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class DeviceDiscoveryService
{
  protected string $baseUrl;
  protected string $token;
  protected int $timeout;

  public function __construct() {
    $this->baseUrl = config('networkdevices.api.base_url');
    $this->token = config('networkdevices.api.token');
    $this->timeout = config('networkdevices.api.timeout', 5);
  }

  /**
  * Ambil semua perangkat
  */
  public function getDevices(): array
  {
    return $this->request('GET', '/devices');
  }

  /**
  * Set nama kustom perangkat
  */
  public function setCustomName(string $ip, string $name): array
  {
    return $this->request('POST', "/devices/{$ip}/name", ['name' => $name]);
  }

  /**
  * Kirim magic packet Wake-on-LAN
  */
  public function wakeOnLan(string $mac): array
  {
    return $this->request('POST', "/wake/{$mac}");
  }

  /**
  * Kontrol perangkat generik (opsional)
  */
  public function controlDevice(string $ip, string $action, array $params = []): array
  {
    return $this->request('POST', '/control', [
      'ip' => $ip,
      'action' => $action,
      'params' => $params,
    ]);
  }

  /**
  * Cek health service
  */
  public function health(): array
  {
    return $this->request('GET', '/health');
  }

  /**
  * Helper untuk HTTP request ke API
  */
  protected function request(string $method, string $endpoint, array $data = []): array
  {
    $http = Http::timeout($this->timeout)
    ->withHeaders($this->getHeaders());

    $response = $method === 'GET'
    ? $http->get($this->baseUrl . $endpoint)
    : $http->post($this->baseUrl . $endpoint, $data);

    if ($response->successful()) {
      return $response->json() ?? [];
    }

    // Log error jika perlu
    \Log::warning("DeviceDiscovery API error: {$response->status()} - {$response->body()}");

    return [];
  }

  protected function getHeaders(): array
  {
    $headers = ['Accept' => 'application/json'];
    if ($this->token) {
      $headers['X-API-Token'] = $this->token;
    }
    return $headers;
  }
}