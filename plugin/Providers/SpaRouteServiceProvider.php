<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\SpaBoot;
use SimpleRO\WPBones\Support\ServiceProvider;

/**
 * SpaRouteServiceProvider — serves the RewardVault user SPA at /reward by
 * OVERTAKING the WordPress template.
 *
 * A rewrite rule maps /reward and /reward/* to an internal query var; on
 * template_redirect we render a bare HTML document (no theme header/footer)
 * that mounts the Vite bundle, then exit. The bundle's hashed asset URLs are
 * read from public/apps/user/.vite/manifest.json so cache-busting is automatic.
 *
 * Client-side routing (react-router, basename=/reward) handles the sub-paths;
 * the "(?:/.*)?" in the rule makes every /reward/* URL serve the same shell.
 *
 * Auth is NOT gated here — /reward is public and the SPA renders login/register
 * when /auth/me returns 401.
 */
class SpaRouteServiceProvider extends ServiceProvider
{
  private const QUERY_VAR = 'simple_ro_spa';

  public function register()
  {
    // WPBones invokes register() during the `init` action (priority 10), so the
    // rewrite rule is added directly here — a nested add_action('init', ...)
    // would be too late to fire in the same request.
    $this->addRewriteRule();
    add_filter('query_vars', [$this, 'registerQueryVar']);
    add_action('template_redirect', [$this, 'maybeRenderSpa']);
  }

  public function addRewriteRule(): void
  {
    add_rewrite_rule('^' . $this->slug() . '(?:/.*)?/?$', 'index.php?' . self::QUERY_VAR . '=user', 'top');
  }

  public function registerQueryVar(array $vars): array
  {
    $vars[] = self::QUERY_VAR;
    return $vars;
  }

  public function maybeRenderSpa(): void
  {
    if (get_query_var(self::QUERY_VAR) !== 'user') {
      return;
    }

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    echo $this->renderDocument('user'); // phpcs:ignore -- self-built, escaped below.
    exit;
  }

  private function slug(): string
  {
    return (string) $this->plugin->config('custom.reward_slug', 'reward');
  }

  private function renderDocument(string $role): string
  {
    [$jsSrc, $cssHrefs] = $this->assets();
    $boot = wp_json_encode(SpaBoot::data($role));

    $tags = '';
    foreach ($cssHrefs as $href) {
      $tags .= '<link rel="stylesheet" href="' . esc_url($href) . '">' . "\n";
    }

    if ($jsSrc === '') {
      // Bundle not built yet — render a helpful placeholder instead of a blank page.
      $body = '<div id="root"><p style="font-family:sans-serif;padding:32px">'
        . esc_html__('The RewardVault app has not been built yet. Run `npm run build:user`.', 'simple-reward-offerwall')
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
      . '<title>' . esc_html__('RewardVault', 'simple-reward-offerwall') . '</title>'
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
   * Resolve the built entry JS + CSS from the Vite manifest.
   *
   * @return array{0:string,1:string[]} [jsSrc, cssHrefs]
   */
  private function assets(): array
  {
    $plugin = $this->plugin;
    $manifestPath = $plugin->basePath . '/public/apps/user/.vite/manifest.json';
    $baseUrl = rtrim($plugin->apps, '/') . '/user';

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
