<?php

if (!defined('ABSPATH')) {
    exit();
}

/*
|--------------------------------------------------------------------------
| Custom configuration
|--------------------------------------------------------------------------
|
| Reward-offerwall settings. Read anywhere via SimpleRO()->config('custom.<key>').
| Money is stored in integer minor units (cents); coins are integers. Never float.
|
*/

return [

  // Default conversion when turning a provider money payout into coins:
  //   coins = round(payout_minor_units / 100 * coins_per_currency_unit)
  // A provider row may override this with its own coin_rate.
  'coins_per_currency_unit' => 100,

  // Auth / session tuning (see SimpleRO\API\Auth\Guard).
  'auth' => [
    'session_ttl_days'        => 14,   // absolute session lifetime
    'reset_ttl_minutes'       => 30,   // password-reset token lifetime
    'login_max_attempts'      => 5,    // failures before soft-lock
    'login_window_minutes'    => 15,   // sliding window for the counter
    'cookie_session'          => 'ro_session',
    'cookie_csrf'             => 'ro_csrf',
    'csrf_header'             => 'X-RO-CSRF',
  ],

  // The user SPA is served at this path by a full template takeover
  // (SpaRouteServiceProvider) — NOT a shortcode page.
  'reward_slug' => 'reward',

  // Default for the site-level external-id prefix. Admins override this at runtime
  // via PUT /admin/settings (stored in the simple_ro_settings option). The
  // {external_id} iframe macro expands to "<prefix>-<user_id>-<user_hash>".
  'external_id' => [
    'prefix' => '',
  ],

  // Lucky Wheel (WheelController). One free spin per UTC day. The server picks a
  // segment by weight and credits `coins` — the client never asserts a prize.
  'wheel' => [
    'segments' => [
      ['label' => '5', 'coins' => 5, 'weight' => 30],
      ['label' => '10', 'coins' => 10, 'weight' => 25],
      ['label' => '25', 'coins' => 25, 'weight' => 18],
      ['label' => '50', 'coins' => 50, 'weight' => 12],
      ['label' => '2', 'coins' => 2, 'weight' => 8],
      ['label' => '100', 'coins' => 100, 'weight' => 4],
      ['label' => '15', 'coins' => 15, 'weight' => 2],
      ['label' => '1', 'coins' => 1, 'weight' => 1],
    ],
  ],

  // Bonus rewards (BonusController). `type`: daily (claimable once per UTC day),
  // one_time (once ever), milestone (once, after `req` approved rewards).
  'bonuses' => [
    ['key' => 'daily_login', 'name' => 'Daily Login', 'desc' => 'Log in every day', 'coins' => 10, 'type' => 'daily', 'icon' => 'fa-calendar-check', 'color' => '#00b67a'],
    ['key' => 'first_offer', 'name' => 'First Reward', 'desc' => 'Earn your first reward', 'coins' => 50, 'type' => 'milestone', 'req' => 1, 'icon' => 'fa-star', 'color' => '#13a0e8'],
    ['key' => 'five_offers', 'name' => '5 Rewards Done', 'desc' => 'Earn 5 rewards', 'coins' => 100, 'type' => 'milestone', 'req' => 5, 'icon' => 'fa-trophy', 'color' => '#ff2d6c'],
    ['key' => 'ten_offers', 'name' => '10 Rewards Done', 'desc' => 'Earn 10 rewards', 'coins' => 250, 'type' => 'milestone', 'req' => 10, 'icon' => 'fa-medal', 'color' => '#4179d6'],
  ],

  // Referral: coins credited to the referrer when a referred user earns their
  // first approved reward (ReferralService).
  'referral' => [
    'bonus_coins' => 200,
  ],

  // WP pages hosting the staff SPAs (by slug). The shortcodes
  // [simple_ro_admin_app] / [simple_ro_support_app] are placed on these pages.
  // (The user app moved to the /reward takeover; no 'user' page anymore.)
  'pages' => [
    'admin'   => 'offerwall-admin',
    'support' => 'offerwall-support',
  ],
];
