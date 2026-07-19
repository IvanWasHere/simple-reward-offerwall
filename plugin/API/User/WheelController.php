<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Services\LedgerService;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * WheelController — the daily Lucky Wheel.
 *
 * Segments (label + coins + weight) come from config custom.wheel.segments. The
 * server picks the winning segment by weight and credits the coins; the client is
 * told which index won only so it can animate to it. One spin per UTC day is
 * enforced by UNIQUE(user_id, spin_date) on simplerewardoffer_wheel_spins.
 */
class WheelController extends RestController
{
  /** One spin per this many days (rolling cooldown from the last spin). */
  private const COOLDOWN_DAYS = 7;

  public function show()
  {
    $user = Guard::user($this->request);
    $last = $this->lastSpin((int) $user->id);
    $cooldownEnds = $this->cooldownEnds($last);

    return $this->response([
      'segments'   => $this->publicSegments(),
      'canSpin'    => time() >= $cooldownEnds,
      'lastSpin'   => $last ? ['coins' => $last['coins']] : null,
      'nextSpinAt' => time() < $cooldownEnds ? gmdate('c', $cooldownEnds) : null,
    ]);
  }

  public function spin()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $userId = (int) $user->id;

    $segments = $this->segments();
    if (empty($segments)) {
      return $this->responseError('ro_unavailable', __('The wheel is not configured.', 'simple-reward-offerwall'), 503);
    }

    // Weekly cooldown: block if the last spin was within COOLDOWN_DAYS.
    $cooldownEnds = $this->cooldownEnds($this->lastSpin($userId));
    if (time() < $cooldownEnds) {
      return $this->responseError('ro_already_spun', __('You already spun this week. Come back later!', 'simple-reward-offerwall'), 409);
    }

    $index = $this->weightedPick($segments);
    $coins = (int) $segments[$index]['coins'];

    // spin_date keeps the daily unique index as a same-day race guard.
    $suppress = $wpdb->suppress_errors(true);
    $ok = $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_wheel_spins',
      [
        'user_id'       => $userId,
        'spin_date'     => gmdate('Y-m-d'),
        'segment_index' => $index,
        'coins'         => $coins,
        'created_at'    => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%s', '%d', '%d', '%s']
    );
    $wpdb->suppress_errors($suppress);

    if (!$ok) {
      return $this->responseError('ro_already_spun', __('You already spun this week. Come back later!', 'simple-reward-offerwall'), 409);
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
    $raw = (array) SimpleRewardOffer()->config('custom.wheel.segments', []);
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

  /** The user's most recent spin, or null. @return array{createdAt:string,coins:int}|null */
  private function lastSpin(int $userId): ?array
  {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT created_at, coins FROM {$wpdb->prefix}simplerewardoffer_wheel_spins WHERE user_id = %d ORDER BY id DESC LIMIT 1",
      $userId
    ));
    return $row ? ['createdAt' => $row->created_at, 'coins' => (int) $row->coins] : null;
  }

  /** Unix time when the cooldown from $last expires (0 if never spun). */
  private function cooldownEnds(?array $last): int
  {
    if (!$last) {
      return 0;
    }
    return (int) strtotime($last['createdAt'] . ' UTC') + self::COOLDOWN_DAYS * DAY_IN_SECONDS;
  }
}
