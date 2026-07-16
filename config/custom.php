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

  // WP pages hosting each front-end SPA (by slug). The shortcodes
  // [simple_ro_user_app] / [simple_ro_admin_app] / [simple_ro_support_app]
  // are placed on these pages.
  'pages' => [
    'user'    => 'dashboard',
    'admin'   => 'offerwall-admin',
    'support' => 'offerwall-support',
  ],
];
