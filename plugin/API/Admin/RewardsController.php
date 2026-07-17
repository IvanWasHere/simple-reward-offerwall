<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\Services\ReferralService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * RewardsController (admin) — the reward-approval queue.
 * Approving a reward credits the coin ledger inside a transaction; the unique
 * ledger ref makes it safe against double-approval.
 */
class RewardsController extends RestController
{
  public function index()
  {
    global $wpdb;

    $status = (string) $this->request->get_param('status');
    $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';

    $r = $wpdb->prefix . 'ro_rewards';
    $c = $wpdb->prefix . 'ro_callbacks';
    $p = $wpdb->prefix . 'ro_providers';
    $u = $wpdb->prefix . 'ro_users';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT rw.id, rw.user_id, rw.coins_value, rw.status, rw.created_at,
              u.email AS user_email,
              cb.amount, cb.currency, cb.transaction_id,
              p.name AS provider_name
         FROM {$r} rw
         LEFT JOIN {$u} u ON u.id = rw.user_id
         LEFT JOIN {$c} cb ON cb.id = rw.callback_id
         LEFT JOIN {$p} p ON p.id = cb.provider_id
        WHERE rw.status = %s
        ORDER BY rw.id DESC
        LIMIT 500",
      $status
    ));

    return $this->response(['rewards' => $rows ?: []]);
  }

  public function approve()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $admin = Guard::user($this->request);
    $r = $wpdb->prefix . 'ro_rewards';

    $wpdb->query('START TRANSACTION');

    $reward = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$r} WHERE id = %d FOR UPDATE", $id));
    if (!$reward) {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_not_found', __('Reward not found.', 'simple-reward-offerwall'), 404);
    }
    if ($reward->status !== 'pending') {
      $wpdb->query('ROLLBACK');
      return $this->responseError('ro_conflict', __('Reward is not pending.', 'simple-reward-offerwall'), 409);
    }

    $wpdb->update(
      $r,
      ['status' => 'approved', 'approved_by' => (int) $admin->id, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $id],
      ['%s', '%d', '%s'],
      ['%d']
    );

    LedgerService::entry((int) $reward->user_id, (int) $reward->coins_value, 'reward_approved', 'reward', $id);

    // Pay the referrer (idempotent — at most once per referred user).
    ReferralService::creditReferrer((int) $reward->user_id);

    $wpdb->query('COMMIT');

    return $this->response([
      'reward'  => ['id' => $id, 'status' => 'approved'],
      'balance' => LedgerService::balance((int) $reward->user_id),
    ]);
  }

  public function reject()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $admin = Guard::user($this->request);
    $r = $wpdb->prefix . 'ro_rewards';

    $reward = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$r} WHERE id = %d LIMIT 1", $id));
    if (!$reward) {
      return $this->responseError('ro_not_found', __('Reward not found.', 'simple-reward-offerwall'), 404);
    }
    if ($reward->status !== 'pending') {
      return $this->responseError('ro_conflict', __('Reward is not pending.', 'simple-reward-offerwall'), 409);
    }

    $wpdb->update(
      $r,
      ['status' => 'rejected', 'approved_by' => (int) $admin->id, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $id],
      ['%s', '%d', '%s'],
      ['%d']
    );

    return $this->response(['reward' => ['id' => $id, 'status' => 'rejected']]);
  }
}
