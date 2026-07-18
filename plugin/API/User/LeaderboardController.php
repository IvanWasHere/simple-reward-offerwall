<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * LeaderboardController — top 25 earners for today / this week (since Sunday) /
 * this month. Reads the denormalised per-period counters on ro_users (maintained
 * by LedgerService) filtered to the current period marker, so it never SUM()s the
 * ledger. Exposes only a display name + amount; no emails or ids.
 */
class LeaderboardController extends RestController
{
  public function index()
  {
    global $wpdb;
    $u = $wpdb->prefix . 'ro_users';

    // Resolve period → the counter column, its marker column, and the current
    // marker value. Columns come from a fixed whitelist (safe to interpolate).
    $now = time();
    $period = (string) $this->request->get_param('period');
    switch ($period) {
      case 'today':
        $col = 'earned_today';
        $markerCol = 'earn_day';
        $marker = gmdate('Y-m-d', $now);
        break;
      case 'month':
        $col = 'earned_month';
        $markerCol = 'earn_month';
        $marker = gmdate('Y-m', $now);
        break;
      case 'week':
      default:
        $period = 'week';
        $col = 'earned_week';
        $markerCol = 'earn_week';
        $marker = gmdate('Y-m-d', $now - ((int) gmdate('w', $now)) * DAY_IN_SECONDS);
        break;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT display_name, email, {$col} AS earned
         FROM {$u}
        WHERE type = 'user' AND {$markerCol} = %s AND {$col} > 0
        ORDER BY {$col} DESC
        LIMIT 25",
      $marker
    ));

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

    return $this->response(['period' => $period, 'leaderboard' => $out]);
  }
}
