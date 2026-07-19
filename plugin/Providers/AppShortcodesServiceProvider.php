<?php

namespace SimpleRewardOffer\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Services\SpaBoot;
use SimpleRewardOffer\WPBones\Support\ServiceProvider;

/**
 * Registers the support front-end SPA shortcode and enqueues its built bundle.
 *
 *   [simplerewardoffer_support_app]  → public/apps/support-app.js
 *
 * (The user + admin apps are served at /reward and /offerwall-admin by
 * SpaRouteServiceProvider — template takeovers, not shortcodes.)
 *
 * Each handler enqueues the @wordpress/scripts bundle (deps/version read from the
 * generated .asset.php), localizes the SimpleRewardOffer boot object (REST base, cookie /
 * CSRF names), and renders the SPA root <div>. Authorization is enforced entirely
 * server-side by the REST Guard — these bundles carry no secrets.
 */
class AppShortcodesServiceProvider extends ServiceProvider
{
  /** @var array<string,array{app:string,role:string}> shortcode => app config */
  private array $apps = [
    // The user + admin apps moved to template takeovers (SpaRouteServiceProvider:
    // /reward and /offerwall-admin). Only the support app is still shortcode-hosted.
    'simplerewardoffer_support_app' => ['app' => 'support-app', 'role' => 'support'],
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
      return '<div class="simplerewardoffer-app simplerewardoffer-app--missing">'
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

    wp_localize_script($app, 'SimpleRewardOffer', SpaBoot::data($role));

    return '<div id="simplerewardoffer-' . esc_attr($role) . '-root" class="simplerewardoffer-app"></div>';
  }
}
