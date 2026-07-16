<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * AccountController — the signed-in user's coins, ledger and rewards.
 * All routes are guarded by Guard::role('user'); the session is already resolved.
 */
class AccountController extends RestController
{
  public function balance()
  {
    $user = Guard::user($this->request);
    return $this->response(['balance' => LedgerService::balance((int) $user->id)]);
  }

  public function rewards()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $r = $wpdb->prefix . 'ro_rewards';
    $c = $wpdb->prefix . 'ro_callbacks';
    $p = $wpdb->prefix . 'ro_providers';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT rw.id, rw.coins_value, rw.status, rw.created_at,
              cb.amount, cb.currency, cb.transaction_id, p.name AS provider_name
         FROM {$r} rw
         LEFT JOIN {$c} cb ON cb.id = rw.callback_id
         LEFT JOIN {$p} p ON p.id = cb.provider_id
        WHERE rw.user_id = %d
        ORDER BY rw.id DESC
        LIMIT 200",
      (int) $user->id
    ));

    return $this->response(['rewards' => $rows]);
  }

  public function ledger()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $t = $wpdb->prefix . 'ro_coin_ledger';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT id, delta, reason, ref_type, ref_id, created_at
         FROM {$t}
        WHERE user_id = %d
        ORDER BY id DESC
        LIMIT 200",
      (int) $user->id
    ));

    return $this->response([
      'balance' => LedgerService::balance((int) $user->id),
      'entries' => $rows,
    ]);
  }
}
