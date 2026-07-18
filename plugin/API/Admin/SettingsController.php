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

    if ($this->request->get_param('app_name') !== null) {
      Settings::set('app_name', sanitize_text_field((string) $this->request->get_param('app_name')));
    }

    if ($this->request->get_param('app_icon_id') !== null) {
      $iconId = (int) $this->request->get_param('app_icon_id');
      // 0 clears; otherwise it must be a real image attachment.
      if ($iconId > 0 && !wp_attachment_is_image($iconId)) {
        return $this->responseError('ro_invalid', __('The selected app icon must be an image.', 'simple-reward-offerwall'), 422);
      }
      Settings::set('app_icon_id', $iconId);
    }

    return $this->response(['settings' => $this->present()]);
  }

  private function present(): array
  {
    return [
      'externalIdPrefix' => Settings::externalIdPrefix(),
      'appName'          => Settings::appName(),
      'appIconId'        => Settings::appIconId(),
      'appIconUrl'       => Settings::appIconUrl(),
    ];
  }
}
