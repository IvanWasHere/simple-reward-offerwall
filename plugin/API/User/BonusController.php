<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * BonusController — daily / one_time / milestone bonuses defined in
 * config custom.bonuses. Claims credit coins idempotently through the ledger:
 *   ref_type 'bonus', ref_id = 0 (once-ever) or YYYYMMDD (daily), reason
 *   'bonus_<key>'. The UNIQUE(ref_type, ref_id, reason) index makes a repeat
 *   claim a silent no-op, so "already claimed" is detected without extra locking.
 */
class BonusController extends RestController
{
  public function index()
  {
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $approved = $this->approvedCount($userId);

    $out = [];
    foreach ($this->bonuses() as $b) {
      $claimed = $this->isClaimed($userId, $b);
      $eligible = $this->isEligible($b, $approved);
      $row = [
        'key'     => $b['key'],
        'name'    => $b['name'],
        'desc'    => $b['desc'],
        'coins'   => (int) $b['coins'],
        'type'    => $b['type'],
        'icon'    => $b['icon'] ?? 'fa-gift',
        'color'   => $b['color'] ?? '#00b67a',
        'claimed' => $claimed,
        'canClaim' => !$claimed && $eligible,
      ];
      if (($b['type'] ?? '') === 'milestone') {
        $row['progress'] = ['current' => min($approved, (int) $b['req']), 'req' => (int) $b['req']];
      }
      $out[] = $row;
    }

    return $this->response(['bonuses' => $out]);
  }

  public function claim()
  {
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $key = (string) $this->request->get_param('key');

    $bonus = null;
    foreach ($this->bonuses() as $b) {
      if ($b['key'] === $key) {
        $bonus = $b;
        break;
      }
    }
    if (!$bonus) {
      return $this->responseError('ro_not_found', __('Unknown bonus.', 'simple-reward-offerwall'), 404);
    }

    if (!$this->isEligible($bonus, $this->approvedCount($userId))) {
      return $this->responseError('ro_locked', __('This bonus is not available yet.', 'simple-reward-offerwall'), 422);
    }

    $refId = ($bonus['type'] === 'daily') ? (int) gmdate('Ymd') : 0;
    $credited = LedgerService::entry($userId, (int) $bonus['coins'], 'bonus_' . $key, 'bonus', $refId);

    if (!$credited) {
      return $this->responseError('ro_already_claimed', __('You already claimed this bonus.', 'simple-reward-offerwall'), 409);
    }

    return $this->response([
      'claimed' => true,
      'coins'   => (int) $bonus['coins'],
      'balance' => LedgerService::balance($userId),
    ]);
  }

  /** @return array<int,array<string,mixed>> */
  private function bonuses(): array
  {
    return (array) SimpleRO()->config('custom.bonuses', []);
  }

  private function approvedCount(int $userId): int
  {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->prefix}ro_rewards WHERE user_id = %d AND status = 'approved'",
      $userId
    ));
  }

  private function isEligible(array $bonus, int $approved): bool
  {
    if (($bonus['type'] ?? '') === 'milestone') {
      return $approved >= (int) ($bonus['req'] ?? 0);
    }
    return true; // daily / one_time are always eligible until claimed
  }

  private function isClaimed(int $userId, array $bonus): bool
  {
    global $wpdb;
    $refId = (($bonus['type'] ?? '') === 'daily') ? (int) gmdate('Ymd') : 0;
    return (bool) $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM {$wpdb->prefix}ro_coin_ledger
        WHERE user_id = %d AND ref_type = 'bonus' AND ref_id = %d AND reason = %s LIMIT 1",
      $userId,
      $refId,
      'bonus_' . $bonus['key']
    ));
  }
}
