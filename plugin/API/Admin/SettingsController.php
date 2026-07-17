<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\Settings;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * SettingsController (admin) — site-level settings. Guarded by Guard::role('admin').
 */
class SettingsController extends RestController
{
  public function show()
  {
    return $this->response(['settings' => $this->present()]);
  }

  public function update()
  {
    if ($this->request->get_param('external_id_prefix') !== null) {
      // Lands verbatim in outbound provider URLs, so keep it URL-safe.
      $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->request->get_param('external_id_prefix'));
      Settings::set('external_id_prefix', $prefix);
    }

    return $this->response(['settings' => $this->present()]);
  }

  private function present(): array
  {
    return [
      'externalIdPrefix' => Settings::externalIdPrefix(),
    ];
  }
}
