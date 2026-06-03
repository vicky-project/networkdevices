<?php
namespace Modules\NetworkDevices\Constants;

class Permission
{
  const VIEW_DEVICES = 'network.devices.view';
  const MANAGE_DEVICES = 'network.devices.manage';

  public static function all(): array
  {
    return [
      self::VIEW_DEVICES => 'Melihat dashboard perangkat jaringan',
      self::MANAGE_DEVICES => 'Mengubah nama, WOL, dan kontrol perangkat',
    ];
  }
}