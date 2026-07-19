<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Services\LedgerService;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * RedemptionsController (user) — browse the payout catalog and redeem coins.
 *
 * Redeeming reserves (debits) coins immediately inside a single InnoDB
 * transaction. The user row is locked FOR UPDATE as a mutex so two concurrent
 * redemptions can't both pass the balance check (no double-spend). Admin approval
 * settles the debit; rejection writes a compensating refund entry.
 */
class RedemptionsController extends RestController
{
  public function catalog()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $t = $wpdb->prefix . 'simplerewardoffer_payouts';

    $rows = $wpdb->get_results(
      "SELECT * FROM {$t} WHERE status = 'active' AND stock <> 0 ORDER BY value_coins ASC"
    );

    $payouts = array_map(function ($r) {
      return [
        'id'          => (int) $r->id,
        'name'        => $r->name,
        'valueMoney'  => (int) $r->value_money,
        'valueCoins'  => (int) $r->value_coins,
        'currency'    => $r->currency,
        'smallIcon'   => $r->small_icon,
        'midsizeIcon' => $r->midsize_icon,
        'largeIcon'   => $r->large_icon,
      ];
    }, $rows ?: []);

    return $this->response([
      'payouts' => $payouts,
      'balance' => LedgerService::balance((int) $user->id),
    ]);
  }

  public function store()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $payoutId = (int) $this->request->get_param('payout_id');

    if ($payoutId <= 0) {
      return $this->responseError('ro_invalid', __('A payout is required.', 'simple-reward-offerwall'), 422);
    }

    $usersT = $wpdb->prefix . 'simplerewardoffer_users';
    $payoutsT = $wpdb->prefix . 'simplerewardoffer_payouts';

    $wpdb->query('START TRANSACTION');

    // Serialize concurrent redemptions for this user (mutex).
    if (!$wpdb->get_row($wpdb->prepare("SELECT id FROM {$usersT} WHERE id = %d FOR UPDATE", $userId))) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_not_found', __('Account not found.', 'simple-reward-offerwall'), 404);
    }

    $payout = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$payoutsT} WHERE id = %d FOR UPDATE", $payoutId));
    if (!$payout || $payout->status !== 'active') {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_not_found', __('Payout not available.', 'simple-reward-offerwall'), 404);
    }
    if ((int) $payout->stock === 0) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_out_of_stock', __('This reward is out of stock.', 'simple-reward-offerwall'), 409);
    }

    $cost = (int) $payout->value_coins;

    $balance = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(delta),0) FROM {$wpdb->prefix}simplerewardoffer_coin_ledger WHERE user_id = %d",
      $userId
    ));

    if ($balance < $cost) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_insufficient', __('Not enough coins.', 'simple-reward-offerwall'), 422);
    }

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_redemptions',
      [
        'user_id'     => $userId,
        'payout_id'   => $payoutId,
        'coins_spent' => $cost,
        'status'      => 'pending',
        'created_at'  => $now,
        'updated_at'  => $now,
      ],
      ['%d', '%d', '%d', '%s', '%s', '%s']
    );
    $redemptionId = (int) $wpdb->insert_id;

    // Reserve = debit now, so the balance can't be double-spent while pending.
    LedgerService::entry($userId, -$cost, 'redemption_reserved', 'redemption', $redemptionId);

    if ((int) $payout->stock > 0) {
      $wpdb->query($wpdb->prepare("UPDATE {$payoutsT} SET stock = stock - 1 WHERE id = %d", $payoutId));
    }

    $wpdb->query('COMMIT');

    return $this->response([
      'redemption' => ['id' => $redemptionId, 'status' => 'pending', 'coinsSpent' => $cost],
      'balance'    => LedgerService::balance($userId),
    ], 201);
  }

  public function mine()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $r = $wpdb->prefix . 'simplerewardoffer_redemptions';
    $p = $wpdb->prefix . 'simplerewardoffer_payouts';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT rd.id, rd.coins_spent, rd.status, rd.created_at, p.name AS payout_name, p.value_money, p.currency
         FROM {$r} rd
         LEFT JOIN {$p} p ON p.id = rd.payout_id
        WHERE rd.user_id = %d
        ORDER BY rd.id DESC
        LIMIT 200",
      (int) $user->id
    ));

    return $this->response(['redemptions' => $rows ?: []]);
  }
}
