<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Settings — site-level, admin-editable configuration stored as a single WP
 * option (`simple_ro_settings`). Falls back to config/custom.php defaults.
 *
 * Currently holds `external_id_prefix`: the leading label an admin sets once for
 * the whole site, used to build the external identifier sent to offerwall
 * providers — `<prefix>-<user_id>-<user_hash>` (see buildExternalId / the
 * {external_id} macro in IframeAdapter).
 */
class Settings
{
  private const OPTION = 'simple_ro_settings';

  public static function all(): array
  {
    $stored = get_option(self::OPTION, []);
    return is_array($stored) ? $stored : [];
  }

  public static function get(string $key, $default = null)
  {
    $all = self::all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
  }

  public static function set(string $key, $value): void
  {
    $all = self::all();
    $all[$key] = $value;
    update_option(self::OPTION, $all);
  }

  /** The admin-defined external-id prefix (config default if unset). */
  public static function externalIdPrefix(): string
  {
    $val = self::get('external_id_prefix', null);
    if ($val === null) {
      $val = SimpleRO()->config('custom.external_id.prefix', '');
    }
    return (string) $val;
  }

  /** The global app/brand name shown across all SPAs (default 'RewardVault'). */
  public static function appName(): string
  {
    $val = self::get('app_name', null);
    $val = ($val === null || $val === '') ? SimpleRO()->config('custom.app_name', 'RewardVault') : $val;
    return (string) $val;
  }

  /** The media-library attachment id chosen as the app icon (0 = none). */
  public static function appIconId(): int
  {
    return (int) self::get('app_icon_id', 0);
  }

  /** Resolved URL for the app icon, or '' when unset. */
  public static function appIconUrl(): string
  {
    $id = self::appIconId();
    if ($id <= 0) {
      return '';
    }
    $url = wp_get_attachment_image_url($id, 'full');
    return $url ? (string) $url : '';
  }

  /**
   * Build the external identifier: `<prefix>-<user_id>-<user_hash>`. The prefix
   * is omitted when unset, so the id is never left with a dangling leading dash.
   */
  public static function buildExternalId(int $userId, string $userHash): string
  {
    $parts = array_filter(
      [self::externalIdPrefix(), (string) $userId, $userHash],
      static fn ($p) => $p !== ''
    );
    return implode('-', $parts);
  }
}
