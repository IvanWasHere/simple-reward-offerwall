<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\Settings;
use SimpleRO\Services\SpaBoot;
use SimpleRO\WPBones\Support\ServiceProvider;

/**
 * SpaRouteServiceProvider — serves the RewardVault Vite SPAs by OVERTAKING the
 * WordPress template at their slugs:
 *
 *   /reward           → the user app          (public/apps/user)
 *   /offerwall-admin  → the admin dashboard   (public/apps/admin)
 *
 * A rewrite rule maps each slug (and its /* sub-paths) to an internal query var;
 * on template_redirect we render a bare HTML document (no theme header/footer)
 * that mounts the matching Vite bundle, then exit. Hashed asset URLs are read
 * from public/apps/<dir>/.vite/manifest.json so cache-busting is automatic.
 *
 * Auth is NOT gated here — the SPA renders its own login screen when /auth/me
 * returns 401 (and the admin app additionally rejects non-admin sessions). All
 * real authority is enforced server-side by the REST Guard.
 */
class SpaRouteServiceProvider extends ServiceProvider
{
  private const QUERY_VAR = 'simple_ro_spa';

  public function register()
  {
    // WPBones invokes register() during the `init` action (priority 10), so the
    // rewrite rules are added directly here — a nested add_action('init', ...)
    // would be too late to fire in the same request.
    $this->addRewriteRules();
    add_filter('query_vars', [$this, 'registerQueryVar']);
    add_action('template_redirect', [$this, 'maybeRenderSpa']);
  }

  /** @return array<string,array{dir:string,slug:string,title:string}> app-key => config */
  private function apps(): array
  {
    return [
      'user'  => [
        'dir'   => 'user',
        'slug'  => (string) $this->plugin->config('custom.reward_slug', 'reward'),
        'title' => 'RewardVault',
      ],
      'admin' => [
        'dir'   => 'admin',
        'slug'  => (string) $this->plugin->config('custom.admin_slug', 'offerwall-admin'),
        'title' => 'RewardVault Admin',
      ],
    ];
  }

  public function addRewriteRules(): void
  {
    foreach ($this->apps() as $key => $app) {
      add_rewrite_rule('^' . $app['slug'] . '(?:/.*)?/?$', 'index.php?' . self::QUERY_VAR . '=' . $key, 'top');
    }
  }

  public function registerQueryVar(array $vars): array
  {
    $vars[] = self::QUERY_VAR;
    return $vars;
  }

  public function maybeRenderSpa(): void
  {
    $key = (string) get_query_var(self::QUERY_VAR);
    $apps = $this->apps();
    if (!isset($apps[$key])) {
      return;
    }

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    echo $this->renderDocument($key, $apps[$key]); // phpcs:ignore -- self-built, escaped below.
    exit;
  }

  /** @param array{dir:string,slug:string,title:string} $app */
  private function renderDocument(string $role, array $app): string
  {
    [$jsSrc, $cssHrefs] = $this->assets($app['dir']);
    $boot = wp_json_encode(SpaBoot::data($role));

    $tags = '';
    foreach ($cssHrefs as $href) {
      $tags .= '<link rel="stylesheet" href="' . esc_url($href) . '">' . "\n";
    }

    if ($jsSrc === '') {
      $body = '<div id="root"><p style="font-family:sans-serif;padding:32px">'
        . sprintf(
          /* translators: %s is an npm script name. */
          esc_html__('This app has not been built yet. Run `npm run %s`.', 'simple-reward-offerwall'),
          'build:' . $app['dir']
        )
        . '</p></div>';
      $script = '';
    } else {
      $body = '<div id="root"></div>';
      $script = '<script type="module" src="' . esc_url($jsSrc) . '"></script>';
    }

    return '<!doctype html><html ' . get_language_attributes() . '>'
      . '<head>'
      . '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">'
      . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
      . '<title>' . esc_html($role === 'admin' ? Settings::appName() . ' Admin' : Settings::appName()) . '</title>'
      . '<link rel="preconnect" href="https://fonts.googleapis.com">'
      . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
      . '<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">'
      . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">'
      . $tags
      . '<script>window.SimpleRO=' . $boot . ';</script>'
      . '</head>'
      . '<body>' . $body . $script . '</body>'
      . '</html>';
  }

  /**
   * Resolve the built entry JS + CSS from a Vite app's manifest.
   *
   * @return array{0:string,1:string[]} [jsSrc, cssHrefs]
   */
  private function assets(string $dir): array
  {
    $plugin = $this->plugin;
    $manifestPath = $plugin->basePath . '/public/apps/' . $dir . '/.vite/manifest.json';
    $baseUrl = rtrim($plugin->apps, '/') . '/' . $dir;

    if (!file_exists($manifestPath)) {
      return ['', []];
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $entry = $manifest['index.html'] ?? null;
    if (!is_array($entry) || empty($entry['file'])) {
      return ['', []];
    }

    $css = [];
    foreach (($entry['css'] ?? []) as $file) {
      $css[] = $baseUrl . '/' . ltrim((string) $file, '/');
    }

    return [$baseUrl . '/' . ltrim((string) $entry['file'], '/'), $css];
  }
}
