<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * LeaderboardController — top earners by lifetime coins credited (positive ledger
 * deltas only, so redemptions/refunds don't distort the ranking). Exposes only a
 * display name + amount; no emails or ids.
 */
class LeaderboardController extends RestController
{
  public function index()
  {
    global $wpdb;
    $l = $wpdb->prefix . 'ro_coin_ledger';
    $u = $wpdb->prefix . 'ro_users';

    $rows = $wpdb->get_results(
      "SELECT u.display_name, u.email, SUM(l.delta) AS earned
         FROM {$l} l
         INNER JOIN {$u} u ON u.id = l.user_id
        WHERE l.delta > 0 AND u.type = 'user'
        GROUP BY l.user_id
        ORDER BY earned DESC
        LIMIT 10"
    );

    $out = [];
    $rank = 1;
    foreach ($rows ?: [] as $r) {
      $name = trim((string) $r->display_name);
      if ($name === '') {
        $name = 'Player ' . strtoupper(substr(md5((string) $r->email), 0, 4));
      }
      $out[] = [
        'rank'   => $rank++,
        'name'   => $name,
        'earned' => (int) $r->earned,
        'avatar' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'P', 0, 2)),
      ];
    }

    return $this->response(['leaderboard' => $out]);
  }
}
