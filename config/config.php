<?php

return [
  'name' => 'NetworkDevices',
  'api' => [
    'base_url' => env('DEVICE_DISCOVERY_URL', 'http://192.168.1.200:5000'),
    'token' => env('DEVICE_DISCOVERY_TOKEN', ''),
    'timeout' => 5,
    'use_secure' => env('DEVICE_DISCOVERY_SECURE', false)
  ],
  'refresh_interval' => 30, // detik untuk polling (jika tidak pakai WebSocket)
];