<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * WheelController — the daily Lucky Wheel.
 *
 * Segments (label + coins + weight) come from config custom.wheel.segments. The
 * server picks the winning segment by weight and credits the coins; the client is
 * told which index won only so it can animate to it. One spin per UTC day is
 * enforced by UNIQUE(user_id, spin_date) on ro_wheel_spins.
 */
class WheelController extends RestController
{
  public function show()
  {
    $user = Guard::user($this->request);
    $today = $this->todaySpin((int) $user->id);

    return $this->response([
      'segments' => $this->publicSegments(),
      'canSpin'  => $today === null,
      'todaySpin' => $today,
    ]);
  }

  public function spin()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $date = gmdate('Y-m-d');

    $segments = $this->segments();
    if (empty($segments)) {
      return $this->responseError('ro_unavailable', __('The wheel is not configured.', 'simple-reward-offerwall'), 503);
    }

    $index = $this->weightedPick($segments);
    $coins = (int) $segments[$index]['coins'];

    // Claim today's spin. A duplicate (same user + date) fails the unique key.
    $suppress = $wpdb->suppress_errors(true);
    $ok = $wpdb->insert(
      $wpdb->prefix . 'ro_wheel_spins',
      [
        'user_id'       => $userId,
        'spin_date'     => $date,
        'segment_index' => $index,
        'coins'         => $coins,
        'created_at'    => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%s', '%d', '%d', '%s']
    );
    $wpdb->suppress_errors($suppress);

    if (!$ok) {
      return $this->responseError('ro_already_spun', __('You already spun today. Come back tomorrow!', 'simple-reward-offerwall'), 409);
    }

    $spinId = (int) $wpdb->insert_id;
    LedgerService::entry($userId, $coins, 'wheel_spin', 'wheel', $spinId);

    return $this->response([
      'index'   => $index,
      'coins'   => $coins,
      'segment' => ['label' => $segments[$index]['label'], 'coins' => $coins],
      'balance' => LedgerService::balance($userId),
    ]);
  }

  /** @return array<int,array{label:string,coins:int,weight:int}> */
  private function segments(): array
  {
    $raw = (array) SimpleRO()->config('custom.wheel.segments', []);
    $out = [];
    foreach ($raw as $s) {
      $out[] = [
        'label'  => (string) ($s['label'] ?? ''),
        'coins'  => (int) ($s['coins'] ?? 0),
        'weight' => max(1, (int) ($s['weight'] ?? 1)),
      ];
    }
    return $out;
  }

  /** Segments without weights (weights are server-only). */
  private function publicSegments(): array
  {
    return array_map(
      fn ($s) => ['label' => $s['label'], 'coins' => $s['coins']],
      $this->segments()
    );
  }

  private function weightedPick(array $segments): int
  {
    $total = array_sum(array_column($segments, 'weight'));
    $roll = random_int(1, $total);
    $acc = 0;
    foreach ($segments as $i => $s) {
      $acc += $s['weight'];
      if ($roll <= $acc) {
        return $i;
      }
    }
    return count($segments) - 1;
  }

  /** @return array{coins:int,segmentIndex:int}|null */
  private function todaySpin(int $userId): ?array
  {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT segment_index, coins FROM {$wpdb->prefix}ro_wheel_spins WHERE user_id = %d AND spin_date = %s LIMIT 1",
      $userId,
      gmdate('Y-m-d')
    ));
    return $row ? ['coins' => (int) $row->coins, 'segmentIndex' => (int) $row->segment_index] : null;
  }
}
