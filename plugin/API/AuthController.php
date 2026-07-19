<?php

namespace SimpleRewardOffer\API;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Services\ReferralService;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * AuthController — custom registration / login / password-reset for offerwall accounts.
 *
 * Security posture:
 *  - Passwords hashed with password_hash(PASSWORD_DEFAULT); verified with password_verify.
 *  - All user/token lookups use $wpdb->prepare (the ORM does not escape where() values).
 *  - Login is rate-limited + timing-equalised to resist enumeration/brute force.
 *  - Sessions are opaque hashed tokens in httpOnly cookies (see Guard).
 */
class AuthController extends RestController
{
  /** Minimum password length. */
  private const MIN_PASSWORD = 10;

  /** A fixed bogus hash so unknown-email logins still spend a verify() cycle. */
  private const DUMMY_HASH = '$2y$10$usesomesillystringforsalt1234567890abcdefghijklmnopqrstuvw';

  /* ---------------------------------------------------------------------
   | POST /auth/register
   * ------------------------------------------------------------------- */
  public function register()
  {
    global $wpdb;

    $email = $this->email();
    $password = (string) $this->request->get_param('password');
    $displayName = sanitize_text_field((string) $this->request->get_param('display_name'));

    if (!$email) {
      return $this->responseError('ro_invalid_email', __('A valid email address is required.', 'simple-reward-offerwall'), 422);
    }
    if (strlen($password) < self::MIN_PASSWORD) {
      return $this->responseError(
        'ro_weak_password',
        sprintf(__('Password must be at least %d characters.', 'simple-reward-offerwall'), self::MIN_PASSWORD),
        422
      );
    }

    $users = $wpdb->prefix . 'simplerewardoffer_users';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$users} WHERE email = %s LIMIT 1", $email));
    if ($exists) {
      return $this->responseError('ro_email_taken', __('An account with that email already exists.', 'simple-reward-offerwall'), 409);
    }

    $referredBy = ReferralService::referrerIdForCode((string) $this->request->get_param('ref'));

    $now = gmdate('Y-m-d H:i:s');
    $ok = $wpdb->insert(
      $users,
      [
        'unique_user_hash' => bin2hex(random_bytes(16)),
        'email'            => $email,
        'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
        'type'             => 'user',
        'status'           => 'active',
        'display_name'     => $displayName,
        'referral_code'    => ReferralService::generateCode(),
        'referred_by'      => $referredBy,
        'created_at'       => $now,
        'updated_at'       => $now,
      ],
      ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
    );

    if (!$ok) {
      return $this->responseError('ro_register_failed', __('Could not create the account.', 'simple-reward-offerwall'), 500);
    }

    $userId = (int) $wpdb->insert_id;
    Guard::issueSession($userId, 'user');

    return $this->response(['user' => $this->userPayload($userId)], 201);
  }

  /* ---------------------------------------------------------------------
   | POST /auth/login
   * ------------------------------------------------------------------- */
  public function login()
  {
    global $wpdb;

    $email = $this->email();
    $password = (string) $this->request->get_param('password');
    $ip = $this->ip();

    if (!$email || $password === '') {
      return $this->responseError('ro_invalid_credentials', __('Email and password are required.', 'simple-reward-offerwall'), 422);
    }

    if ($this->isRateLimited($email, $ip)) {
      return $this->responseError('ro_rate_limited', __('Too many attempts. Please try again later.', 'simple-reward-offerwall'), 429);
    }

    $users = $wpdb->prefix . 'simplerewardoffer_users';
    $user = $wpdb->get_row($wpdb->prepare(
      "SELECT id, password_hash, type, status FROM {$users} WHERE email = %s LIMIT 1",
      $email
    ));

    // Always run verify (dummy hash when unknown) to keep timing uniform.
    $hash = $user->password_hash ?? self::DUMMY_HASH;
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
      $this->recordAttempt($email, $ip, false);
      return $this->responseError('ro_invalid_credentials', __('Invalid email or password.', 'simple-reward-offerwall'), 401);
    }

    if ($user->status === 'blocked') {
      $this->recordAttempt($email, $ip, false);
      return $this->responseError('ro_blocked', __('This account has been blocked.', 'simple-reward-offerwall'), 403);
    }

    // Transparent hash upgrade.
    if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
      $wpdb->update(
        $users,
        ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => gmdate('Y-m-d H:i:s')],
        ['id' => (int) $user->id],
        ['%s', '%s'],
        ['%d']
      );
    }

    $this->recordAttempt($email, $ip, true);
    Guard::issueSession((int) $user->id, $user->type);

    return $this->response(['user' => $this->userPayload((int) $user->id)]);
  }

  /* ---------------------------------------------------------------------
   | POST /auth/forgot
   * ------------------------------------------------------------------- */
  public function forgot()
  {
    global $wpdb;

    $email = $this->email();
    // Always respond the same way to avoid account enumeration.
    $generic = $this->response([
      'message' => __('If an account exists for that email, a reset link has been sent.', 'simple-reward-offerwall'),
    ]);

    if (!$email) {
      return $generic;
    }

    // Throttle by IP so a valid email can't be used to flood a victim's inbox or
    // grow the resets table. The response stays uniform regardless.
    if ($this->forgotThrottled($this->ip())) {
      return $generic;
    }

    $users = $wpdb->prefix . 'simplerewardoffer_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$users} WHERE email = %s LIMIT 1", $email));
    if (!$user || $user->status === 'blocked') {
      return $generic;
    }

    $token = bin2hex(random_bytes(32));
    $ttl = (int) SimpleRewardOffer()->config('custom.auth.reset_ttl_minutes', 30);
    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_password_resets',
      [
        'user_id'    => (int) $user->id,
        'token_hash' => hash('sha256', $token),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl * MINUTE_IN_SECONDS),
        'created_at' => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%s', '%s', '%s']
    );

    $this->sendResetEmail($email, $token);

    return $generic;
  }

  /* ---------------------------------------------------------------------
   | POST /auth/reset
   * ------------------------------------------------------------------- */
  public function reset()
  {
    global $wpdb;

    $token = (string) $this->request->get_param('token');
    $password = (string) $this->request->get_param('password');

    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
      return $this->responseError('ro_invalid_token', __('Invalid or expired reset token.', 'simple-reward-offerwall'), 422);
    }
    if (strlen($password) < self::MIN_PASSWORD) {
      return $this->responseError(
        'ro_weak_password',
        sprintf(__('Password must be at least %d characters.', 'simple-reward-offerwall'), self::MIN_PASSWORD),
        422
      );
    }

    $resets = $wpdb->prefix . 'simplerewardoffer_password_resets';
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, user_id, expires_at, used_at FROM {$resets} WHERE token_hash = %s LIMIT 1",
      hash('sha256', $token)
    ));

    if (!$row || !empty($row->used_at) || (strtotime($row->expires_at . ' UTC') < time())) {
      return $this->responseError('ro_invalid_token', __('Invalid or expired reset token.', 'simple-reward-offerwall'), 422);
    }

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->update(
      $wpdb->prefix . 'simplerewardoffer_users',
      ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => $now],
      ['id' => (int) $row->user_id],
      ['%s', '%s'],
      ['%d']
    );
    $wpdb->update($resets, ['used_at' => $now], ['id' => (int) $row->id], ['%s'], ['%d']);

    // Invalidate every existing session for this user.
    Guard::revokeAllForUser((int) $row->user_id);

    return $this->response(['message' => __('Your password has been reset. Please sign in.', 'simple-reward-offerwall')]);
  }

  /* ---------------------------------------------------------------------
   | GET /auth/me   (public — self-reports auth state)
   * ------------------------------------------------------------------- */
  public function me()
  {
    $user = Guard::user($this->request);
    if (!$user) {
      // Anonymous visitor: report logged-out state as 200 with a null user rather
      // than a 401. This is the SPA's "am I signed in?" probe on every page load;
      // a 4xx would surface a red error in the browser console on the login screen
      // even though it's the expected path. The client reads `user === null`.
      return $this->response(['user' => null]);
    }
    return $this->response(['user' => $this->publicUser($user)]);
  }

  /* ---------------------------------------------------------------------
   | DELETE /auth/session   (logout — requires auth + CSRF via Guard)
   * ------------------------------------------------------------------- */
  public function logout()
  {
    Guard::revokeCurrent();
    return $this->response(['message' => __('Signed out.', 'simple-reward-offerwall')]);
  }

  /* ---------------------------------------------------------------------
   | Helpers
   * ------------------------------------------------------------------- */

  private function email(): ?string
  {
    $email = strtolower(trim((string) $this->request->get_param('email')));
    $email = sanitize_email($email);
    return is_email($email) ? $email : null;
  }

  private function ip(): string
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
  }

  private function isRateLimited(string $email, string $ip): bool
  {
    global $wpdb;

    $max = (int) SimpleRewardOffer()->config('custom.auth.login_max_attempts', 5);
    $window = (int) SimpleRewardOffer()->config('custom.auth.login_window_minutes', 15);
    $since = gmdate('Y-m-d H:i:s', time() - $window * MINUTE_IN_SECONDS);
    $attempts = $wpdb->prefix . 'simplerewardoffer_login_attempts';

    $count = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$attempts} WHERE success = 0 AND created_at > %s AND (email = %s OR ip = %s)",
      $since,
      $email,
      $ip
    ));

    return $count >= $max;
  }

  /** Allow a bounded number of forgot-password requests per IP per window. */
  private function forgotThrottled(string $ip): bool
  {
    $key = 'simplerewardoffer_forgot_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= 5) {
      return true;
    }
    set_transient($key, $count + 1, (int) SimpleRewardOffer()->config('custom.auth.login_window_minutes', 15) * MINUTE_IN_SECONDS);
    return false;
  }

  private function recordAttempt(string $email, string $ip, bool $success): void
  {
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_login_attempts',
      ['email' => $email, 'ip' => $ip, 'success' => $success ? 1 : 0, 'created_at' => gmdate('Y-m-d H:i:s')],
      ['%s', '%s', '%d', '%s']
    );
  }

  private function sendResetEmail(string $email, string $token): void
  {
    // The user app lives at the /reward takeover; AuthScreens reads ?token=.
    $slug = trim((string) SimpleRewardOffer()->config('custom.reward_slug', 'reward'), '/');
    $url = home_url('/' . $slug . '/') . '?token=' . rawurlencode($token);

    $subject = __('Reset your password', 'simple-reward-offerwall');
    $message = sprintf(
      /* translators: %s is the password reset URL. */
      __("We received a request to reset your password.\n\nUse the link below (valid for a limited time):\n%s\n\nIf you didn't request this, you can ignore this email.", 'simple-reward-offerwall'),
      $url
    );

    wp_mail($email, $subject, $message);
  }

  /** Build the public user payload from a fresh DB read (post-write). */
  private function userPayload(int $userId): array
  {
    global $wpdb;
    $users = $wpdb->prefix . 'simplerewardoffer_users';
    $u = $wpdb->get_row($wpdb->prepare(
      "SELECT id, email, type, status, display_name, unique_user_hash FROM {$users} WHERE id = %d LIMIT 1",
      $userId
    ));
    return $u ? $this->publicUser($u) : [];
  }

  private function publicUser(object $u): array
  {
    return [
      'id'          => (int) $u->id,
      'email'       => $u->email,
      'type'        => $u->type,
      'status'      => $u->status,
      'displayName' => $u->display_name,
      'hash'        => $u->unique_user_hash,
    ];
  }
}
