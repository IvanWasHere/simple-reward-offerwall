<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\SpaBoot;
use SimpleRO\WPBones\Support\ServiceProvider;

/**
 * Registers the staff front-end SPA shortcodes and enqueues their built bundles.
 *
 *   [simple_ro_admin_app]    → public/apps/admin-app.js
 *   [simple_ro_support_app]  → public/apps/support-app.js
 *
 * (The user app is served at /reward by SpaRouteServiceProvider, not a shortcode.)
 *
 * Each handler enqueues the @wordpress/scripts bundle (deps/version read from the
 * generated .asset.php), localizes the SimpleRO boot object (REST base, cookie /
 * CSRF names), and renders the SPA root <div>. Authorization is enforced entirely
 * server-side by the REST Guard — these bundles carry no secrets.
 */
class AppShortcodesServiceProvider extends ServiceProvider
{
  /** @var array<string,array{app:string,root:string}> shortcode => app config */
  private array $apps = [
    // The user app moved to the /reward template takeover (SpaRouteServiceProvider);
    // only the staff apps are shortcode-hosted now.
    'simple_ro_admin_app'   => ['app' => 'admin-app', 'role' => 'admin'],
    'simple_ro_support_app' => ['app' => 'support-app', 'role' => 'support'],
  ];

  public function register()
  {
    foreach ($this->apps as $shortcode => $cfg) {
      add_shortcode($shortcode, function () use ($cfg) {
        return $this->renderApp($cfg['app'], $cfg['role']);
      });
    }
  }

  private function renderApp(string $app, string $role): string
  {
    $plugin = $this->plugin;
    $assetFile = $plugin->basePath . '/public/apps/' . $app . '.asset.php';

    if (!file_exists($assetFile)) {
      return '<div class="simple-ro-app simple-ro-app--missing">'
        . esc_html__('This dashboard has not been built yet.', 'simple-reward-offerwall')
        . '</div>';
    }

    $asset = include $assetFile;
    $deps = $asset['dependencies'] ?? [];
    $ver = $asset['version'] ?? $plugin->Version;

    wp_enqueue_script($app, $plugin->apps . '/' . $app . '.js', $deps, $ver, true);

    // Core-registered @wordpress/components stylesheet so the SPA looks native
    // on the front-end (it isn't auto-loaded outside wp-admin).
    wp_enqueue_style('wp-components');

    $cssFile = $plugin->basePath . '/public/apps/' . $app . '.css';
    if (file_exists($cssFile)) {
      wp_enqueue_style($app, $plugin->apps . '/' . $app . '.css', [], $ver);
    }

    wp_localize_script($app, 'SimpleRO', SpaBoot::data($role));

    return '<div id="simple-ro-' . esc_attr($role) . '-root" class="simple-ro-app"></div>';
  }
}
