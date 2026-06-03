<?php
namespace Modules\NetworkDevices\Providers\Menu;

use Modules\NetworkDevices\Constants\Permission;
use Modules\CoreUI\Services\BaseMenuProvider;

class DeviceMenuProvider extends BaseMenuProvider
{
  protected array $config = [
    "group" => "server",
    "location" => "sidebar",
    "icon" => "bi bi-router",
    "order" => 50,
    // sesuaikan posisi
    "permission" => null,
    // permission untuk melihat grup ini (null = tampil selalu)
  ];

  public function __construct() {
    $moduleName = "NetworkDevices";
    parent::__construct($moduleName);
  }

  /**
  * Get all menus
  */
  public function getMenus(): array
  {
    return [
      // Item dropdown
      $this->item([
        "title" => "Perangkat Jaringan",
        "icon" => "bi bi-hdd-network",
        "type" => "dropdown",
        "order" => 1,
        "children" => [
          $this->item([
            "title" => "Dashboard",
            "icon" => "bi bi-speedometer2",
            "route" => "admin.network.dashboard", // route name nanti
            "order" => 1,
            "permission" => Permission::VIEW_DEVICES,
          ]),
          // Bisa tambah item lain (misal Discovery Status) jika perlu
        ],
      ]),
    ];
  }
}