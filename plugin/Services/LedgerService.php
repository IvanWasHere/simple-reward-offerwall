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

    return (bool) $ok;
  }
}
