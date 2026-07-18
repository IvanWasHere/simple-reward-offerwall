<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * SpaBoot — the `window.SimpleRO` boot payload shared by every front-end SPA
 * entry point (the /reward template takeover and the admin/support shortcodes).
 *
 * Carries no secrets: REST base + cookie/CSRF names + a couple of URLs. All
 * authorization is enforced server-side by the REST Guard.
 */
class SpaBoot
{
  public static function data(string $role): array
  {
    $plugin = SimpleRO();

    return [
      'restBase'   => esc_url_raw(rtrim(rest_url('simple-ro/v1'), '/')),
      'app'        => $role,
      'appName'    => Settings::appName(),
      'appIconUrl' => Settings::appIconUrl(),
      'cookieCsrf' => $plugin->config('custom.auth.cookie_csrf', 'ro_csrf'),
      'csrfHeader' => $plugin->config('custom.auth.csrf_header', 'X-RO-CSRF'),
      'pages'      => $plugin->config('custom.pages', []),
      'homeUrl'    => esc_url_raw(home_url('/')),
      'rewardUrl'  => esc_url_raw(home_url('/' . ltrim((string) $plugin->config('custom.reward_slug', 'reward'), '/'))),
      'adminUrl'   => esc_url_raw(home_url('/' . ltrim((string) $plugin->config('custom.admin_slug', 'offerwall-admin'), '/'))),
    ];
  }
}
