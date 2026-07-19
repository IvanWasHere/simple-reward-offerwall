<?php

namespace SimpleRewardOffer\API\Auth;

if (!defined('ABSPATH')) {
  exit();
}

use WP_Error;
use WP_REST_Request;

/**
 * Guard — custom token auth for the REST API.
 *
 * Sessions are opaque tokens: the raw token lives in the httpOnly `simplerewardoffer_session`
 * cookie; only sha256(token) is stored in wp_simplerewardoffer_sessions. A second, non-httpOnly
 * `simplerewardoffer_csrf` cookie backs a double-submit CSRF check on mutating requests.
 *
 * Every lookup that touches request-controlled input uses $wpdb->prepare(),
 * because the WPBones query builder does not escape where() values.
 *
 * Usage in routes:
 *   Route::get('/me/balance', 'SimpleRewardOffer\API\...@...', ['permission_callback' => Guard::role('user')]);
 *   Route::post('/admin/...',  ...,                    ['permission_callback' => Guard::role('admin')]);
 *   Route::delete('/auth/session', ...,                ['permission_callback' => Guard::authenticated()]);
 */
class Guard
{
  /** @var array<int,array|null> resolved sessions keyed by request object id */
  private static $cache = [];

  private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

  /* ---------------------------------------------------------------------
   | permission_callback factories
   * ------------------------------------------------------------------- */

  /**
   * Require one of the given account types. `admin` is always allowed on
   * support routes (superset). Returns a closure suitable for permission_callback.
   *
   * @param string ...$types  e.g. 'user', 'admin', 'support'
   */
  public static function role(string ...$types): callable
  {
    // admin is a superset of support
    if (in_array('support', $types, true) && !in_array('admin', $types, true)) {
      $types[] = 'admin';
    }

    return function (WP_REST_Request $request) use ($types) {
      $record = self::resolve($request);

      if (!$record) {
        return new WP_Error('ro_unauthenticated', __('Authentication required.', 'simple-reward-offerwall'), ['status' => 401]);
      }

      if (!in_array($record['user']->type, $types, true)) {
        return new WP_Error('ro_forbidden', __('You do not have access to this resource.', 'simple-reward-offerwall'), ['status' => 403]);
      }

      if (in_array(strtoupper($request->get_method()), self::MUTATING, true) && !self::verifyCsrf($request, $record['session'])) {
        return new WP_Error('ro_csrf', __('Invalid or missing CSRF token.', 'simple-reward-offerwall'), ['status' => 403]);
      }

      self::touch($record['session']->id);

      return true;
    };
  }

  /**
   * Require any authenticated account (user/admin/support).
   */
  public static function authenticated(): callable
  {
    return self::role('user', 'admin', 'support');
  }

  /* ---------------------------------------------------------------------
   | Resolution
   * ------------------------------------------------------------------- */

  /**
   * Resolve the current session + user from the session cookie, or null.
   * Does NOT enforce CSRF (role() does that for mutating methods).
   *
   * @return array{session:object,user:object}|null
   */
  public static function resolve(WP_REST_Request $request): ?array
  {
    $key = spl_object_id($request);
    if (array_key_exists($key, self::$cache)) {
      return self::$cache[$key];
    }

    return self::$cache[$key] = self::lookup();
  }

  private static function lookup(): ?array
  {
    global $wpdb;

    $raw = $_COOKIE[self::cfg('cookie_session', 'simplerewardoffer_session')] ?? '';
    if (!is_string($raw) || strlen($raw) !== 64 || !ctype_xdigit($raw)) {
      return null;
    }

    $tokenHash = hash('sha256', $raw);
    $sessions  = $wpdb->prefix . 'simplerewardoffer_sessions';
    $users     = $wpdb->prefix . 'simplerewardoffer_users';

    // phpcs:ignore WordPress.DB.PreparedSQL -- table names are trusted; value is prepared.
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT s.id AS session_id, s.user_id, s.csrf_hash, s.account_type, s.expires_at, s.revoked_at,
                u.id AS uid, u.email, u.type, u.status, u.display_name, u.unique_user_hash
           FROM {$sessions} s
           INNER JOIN {$users} u ON u.id = s.user_id
          WHERE s.token_hash = %s
          LIMIT 1",
        $tokenHash
      )
    );

    if (!$row) {
      return null;
    }
    if (!empty($row->revoked_at)) {
      return null;
    }
    if (!empty($row->expires_at) && strtotime($row->expires_at . ' UTC') < time()) {
      return null;
    }
    if ($row->status === 'blocked') {
      return null;
    }

    $session = (object) [
      'id'           => (int) $row->session_id,
      'user_id'      => (int) $row->user_id,
      'csrf_hash'    => $row->csrf_hash,
      'account_type' => $row->account_type,
    ];

    $user = (object) [
      'id'               => (int) $row->uid,
      'email'            => $row->email,
      'type'             => $row->type,
      'status'           => $row->status,
      'display_name'     => $row->display_name,
      'unique_user_hash' => $row->unique_user_hash,
    ];

    return ['session' => $session, 'user' => $user];
  }

  /**
   * Convenience for controllers: the authenticated user, or null.
   */
  public static function user(WP_REST_Request $request): ?object
  {
    $record = self::resolve($request);
    return $record ? $record['user'] : null;
  }

  /* ---------------------------------------------------------------------
   | Session lifecycle
   * ------------------------------------------------------------------- */

  /**
   * Create a session for a user, set the cookies, and return the raw values.
   *
   * @return array{token:string,csrf:string,expires_at:string}
   */
  public static function issueSession(int $userId, string $accountType): array
  {
    global $wpdb;

    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $csrf  = bin2hex(random_bytes(32));
    $now   = gmdate('Y-m-d H:i:s');
    $ttl   = (int) self::cfg('session_ttl_days', 14);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl * DAY_IN_SECONDS);

    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_sessions',
      [
        'user_id'      => $userId,
        'token_hash'   => hash('sha256', $token),
        'csrf_hash'    => hash('sha256', $csrf),
        'account_type' => $accountType,
        'ip'           => self::ip(),
        'user_agent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'created_at'   => $now,
        'last_used_at' => $now,
        'expires_at'   => $expiresAt,
      ],
      ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    $maxAge = $ttl * DAY_IN_SECONDS;
    self::setCookie(self::cfg('cookie_session', 'simplerewardoffer_session'), $token, $maxAge, true);
    self::setCookie(self::cfg('cookie_csrf', 'simplerewardoffer_csrf'), $csrf, $maxAge, false);

    return ['token' => $token, 'csrf' => $csrf, 'expires_at' => $expiresAt];
  }

  /**
   * Revoke the session tied to the current request cookie and clear cookies.
   */
  public static function revokeCurrent(): void
  {
    global $wpdb;

    $raw = $_COOKIE[self::cfg('cookie_session', 'simplerewardoffer_session')] ?? '';
    if (is_string($raw) && strlen($raw) === 64 && ctype_xdigit($raw)) {
      $wpdb->update(
        $wpdb->prefix . 'simplerewardoffer_sessions',
        ['revoked_at' => gmdate('Y-m-d H:i:s')],
        ['token_hash' => hash('sha256', $raw)],
        ['%s'],
        ['%s']
      );
    }

    self::clearCookies();
  }

  /**
   * Revoke every session for a user (used on password reset, block, type change).
   */
  public static function revokeAllForUser(int $userId): void
  {
    global $wpdb;

    $wpdb->update(
      $wpdb->prefix . 'simplerewardoffer_sessions',
      ['revoked_at' => gmdate('Y-m-d H:i:s')],
      ['user_id' => $userId, 'revoked_at' => null],
      ['%s'],
      ['%d', '%s']
    );
  }

  public static function clearCookies(): void
  {
    self::setCookie(self::cfg('cookie_session', 'simplerewardoffer_session'), '', -3600, true);
    self::setCookie(self::cfg('cookie_csrf', 'simplerewardoffer_csrf'), '', -3600, false);
  }

  /* ---------------------------------------------------------------------
   | Internals
   * ------------------------------------------------------------------- */

  private static function verifyCsrf(WP_REST_Request $request, object $session): bool
  {
    $header = (string) $request->get_header(self::cfg('csrf_header', 'X-RO-CSRF'));
    if ($header === '' || strlen($header) !== 64 || !ctype_xdigit($header)) {
      return false;
    }
    return hash_equals($session->csrf_hash, hash('sha256', $header));
  }

  private static function touch(int $sessionId): void
  {
    global $wpdb;
    $wpdb->update(
      $wpdb->prefix . 'simplerewardoffer_sessions',
      ['last_used_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $sessionId],
      ['%s'],
      ['%d']
    );
  }

  private static function setCookie(string $name, string $value, int $maxAge, bool $httpOnly): void
  {
    if (headers_sent()) {
      return;
    }
    setcookie($name, $value, [
      'expires'  => $maxAge > 0 ? time() + $maxAge : time() - 3600,
      'path'     => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
      'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
      'secure'   => self::isHttps(),
      'httponly' => $httpOnly,
      'samesite' => 'Strict',
    ]);
    // keep the current request's view of cookies consistent
    if ($maxAge > 0) {
      $_COOKIE[$name] = $value;
    } else {
      unset($_COOKIE[$name]);
    }
  }

  private static function ip(): string
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
  }

  /**
   * True when the request reached the site over HTTPS — including via a
   * TLS-terminating reverse proxy that sets X-Forwarded-Proto. Session auth
   * should always be HTTPS-only, so the Secure cookie flag must not silently
   * drop when is_ssl() is false behind a proxy.
   */
  private static function isHttps(): bool
  {
    if (is_ssl()) {
      return true;
    }
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return is_string($proto) && strtolower(explode(',', $proto)[0]) === 'https';
  }

  private static function cfg(string $key, $default = null)
  {
    return SimpleRewardOffer()->config('custom.auth.' . $key, $default);
  }
}
