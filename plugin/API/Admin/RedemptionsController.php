<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * RedemptionsController (admin) — the redemption-approval queue.
 * Approve settles the already-reserved debit; reject writes a compensating refund
 * entry (append-only ledger) and restores finite stock.
 */
class RedemptionsController extends RestController
{
  public function index()
  {
    global $wpdb;

    $status = (string) $this->request->get_param('status');
    $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';

    $r = $wpdb->prefix . 'ro_redemptions';
    $p = $wpdb->prefix . 'ro_payouts';
    $u = $wpdb->prefix . 'ro_users';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT rd.id, rd.user_id, rd.coins_spent, rd.status, rd.created_at,
              u.email AS user_email, p.name AS payout_name, p.value_money, p.currency
         FROM {$r} rd
         LEFT JOIN {$u} u ON u.id = rd.user_id
         LEFT JOIN {$p} p ON p.id = rd.payout_id
        WHERE rd.status = %s
        ORDER BY rd.id DESC
        LIMIT 500",
      $status
    ));

    return $this->response(['redemptions' => $rows ?: []]);
  }

  public function approve()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $admin = Guard::user($this->request);
    $r = $wpdb->prefix . 'ro_redemptions';

    $wpdb->query('START TRANSACTION');

    $rd = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$r} WHERE id = %d FOR UPDATE", $id));
    if (!$rd) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_not_found', __('Redemption not found.', 'simple-reward-offerwall'), 404);
    }
    if ($rd->status !== 'pending') {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_conflict', __('Redemption is not pending.', 'simple-reward-offerwall'), 409);
    }

    // The coins were already reserved (debited) at request time; approval just
    // finalizes the request. Balance is unchanged.
    $wpdb->update(
      $r,
      ['status' => 'approved', 'approved_by' => (int) $admin->id, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $id],
      ['%s', '%d', '%s'],
      ['%d']
    );

    $wpdb->query('COMMIT');

    return $this->response(['redemption' => ['id' => $id, 'status' => 'approved']]);
  }

  public function reject()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $admin = Guard::user($this->request);
    $r = $wpdb->prefix . 'ro_redemptions';

    $wpdb->query('START TRANSACTION');

    $rd = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$r} WHERE id = %d FOR UPDATE", $id));
    if (!$rd) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_not_found', __('Redemption not found.', 'simple-reward-offerwall'), 404);
    }
    if ($rd->status !== 'pending') {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_conflict', __('Redemption is not pending.', 'simple-reward-offerwall'), 409);
    }

    $wpdb->update(
      $r,
      ['status' => 'rejected', 'approved_by' => (int) $admin->id, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $id],
      ['%s', '%d', '%s'],
      ['%d']
    );

    // Refund the reserved coins with a compensating (idempotent) ledger entry.
    LedgerService::entry((int) $rd->user_id, (int) $rd->coins_spent, 'redemption_refunded', 'redemption', $id);

    // Restore finite stock (leave unlimited stock = -1 untouched).
    $wpdb->query($wpdb->prepare(
      "UPDATE {$wpdb->prefix}ro_payouts SET stock = stock + 1 WHERE id = %d AND stock >= 0",
      (int) $rd->payout_id
    ));

    $wpdb->query('COMMIT');

    return $this->response([
      'redemption' => ['id' => $id, 'status' => 'rejected'],
      'balance'    => LedgerService::balance((int) $rd->user_id),
    ]);
  }
}
