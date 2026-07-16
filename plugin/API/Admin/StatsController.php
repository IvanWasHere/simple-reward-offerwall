<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * StatsController (admin) — headline counters for the admin dashboard.
 */
class StatsController extends RestController
{
  public function index()
  {
    global $wpdb;
    $p = $wpdb->prefix;

    $var = static function (string $sql) use ($wpdb) {
      return (int) $wpdb->get_var($sql);
    };

    $stats = [
      'users' => [
        'total'   => $var("SELECT COUNT(*) FROM {$p}ro_users"),
        'user'    => $var("SELECT COUNT(*) FROM {$p}ro_users WHERE type = 'user'"),
        'support' => $var("SELECT COUNT(*) FROM {$p}ro_users WHERE type = 'support'"),
        'admin'   => $var("SELECT COUNT(*) FROM {$p}ro_users WHERE type = 'admin'"),
        'blocked' => $var("SELECT COUNT(*) FROM {$p}ro_users WHERE status = 'blocked'"),
      ],
      'providers' => [
        'total'  => $var("SELECT COUNT(*) FROM {$p}ro_providers"),
        'active' => $var("SELECT COUNT(*) FROM {$p}ro_providers WHERE status = 'active'"),
      ],
      'offers' => [
        'active' => $var("SELECT COUNT(*) FROM {$p}ro_offers WHERE active = 1 AND admin_disabled = 0"),
      ],
      'rewards' => [
        'pending'       => $var("SELECT COUNT(*) FROM {$p}ro_rewards WHERE status = 'pending'"),
        'pendingCoins'  => $var("SELECT COALESCE(SUM(coins_value),0) FROM {$p}ro_rewards WHERE status = 'pending'"),
        'approved'      => $var("SELECT COUNT(*) FROM {$p}ro_rewards WHERE status = 'approved'"),
      ],
      'redemptions' => [
        'pending'      => $var("SELECT COUNT(*) FROM {$p}ro_redemptions WHERE status = 'pending'"),
        'pendingCoins' => $var("SELECT COALESCE(SUM(coins_spent),0) FROM {$p}ro_redemptions WHERE status = 'pending'"),
      ],
      'coins' => [
        // Total coins currently held by users (sum of the whole ledger).
        'outstanding' => $var("SELECT COALESCE(SUM(delta),0) FROM {$p}ro_coin_ledger"),
      ],
      'callbacks' => [
        'total'    => $var("SELECT COUNT(*) FROM {$p}ro_callbacks"),
        'last24h'  => $var($wpdb->prepare(
          "SELECT COUNT(*) FROM {$p}ro_callbacks WHERE created_at > %s",
          gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
        )),
      ],
      'supportTickets' => [
        'open'    => $var("SELECT COUNT(*) FROM {$p}ro_support_requests WHERE status = 'open'"),
        'pending' => $var("SELECT COUNT(*) FROM {$p}ro_support_requests WHERE status = 'pending'"),
      ],
    ];

    return $this->response(['stats' => $stats]);
  }
}
