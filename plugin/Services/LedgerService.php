<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * LedgerService — the append-only coin ledger is the single source of truth for
 * balances. Every credit/debit is idempotent via UNIQUE(ref_type, ref_id, reason).
 */
class LedgerService
{
  public static function balance(int $userId): int
  {
    global $wpdb;
    $t = $wpdb->prefix . 'ro_coin_ledger';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(delta),0) FROM {$t} WHERE user_id = %d", $userId));
  }

  /**
   * Insert a ledger entry. Idempotent: a duplicate (ref_type, ref_id, reason)
   * is silently ignored (unique index). Returns true when a row was inserted.
   */
  public static function entry(int $userId, int $delta, string $reason, string $refType, int $refId): bool
  {
    global $wpdb;

    $suppress = $wpdb->suppress_errors(true);
    $ok = $wpdb->insert(
      $wpdb->prefix . 'ro_coin_ledger',
      [
        'user_id'    => $userId,
        'delta'      => $delta,
        'reason'     => $reason,
        'ref_type'   => $refType,
        'ref_id'     => $refId,
        'created_at' => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%d', '%s', '%s', '%d']
    );
    $wpdb->suppress_errors($suppress);

    // Maintain the denormalised leaderboard counters on a genuine new credit.
    if ($ok && $delta > 0) {
      self::addEarning($userId, $delta);
    }

    return (bool) $ok;
  }

  /**
   * Add to a user's today / this-week (since Sunday) / this-month earning
   * counters in a single UPDATE, resetting any counter whose period marker has
   * rolled over. Powers the leaderboard without SUM()-ing the ledger.
   */
  private static function addEarning(int $userId, int $delta): void
  {
    global $wpdb;
    $t = $wpdb->prefix . 'ro_users';

    $now = time();
    $today = gmdate('Y-m-d', $now);
    $weekStart = gmdate('Y-m-d', $now - ((int) gmdate('w', $now)) * DAY_IN_SECONDS); // last Sunday (UTC)
    $month = gmdate('Y-m', $now);

    $wpdb->query($wpdb->prepare(
      "UPDATE {$t} SET
         earned_today = (CASE WHEN earn_day = %s THEN earned_today ELSE 0 END) + %d,
         earn_day = %s,
         earned_week = (CASE WHEN earn_week = %s THEN earned_week ELSE 0 END) + %d,
         earn_week = %s,
         earned_month = (CASE WHEN earn_month = %s THEN earned_month ELSE 0 END) + %d,
         earn_month = %s
       WHERE id = %d",
      $today, $delta, $today,
      $weekStart, $delta, $weekStart,
      $month, $delta, $month,
      $userId
    ));
  }
}
