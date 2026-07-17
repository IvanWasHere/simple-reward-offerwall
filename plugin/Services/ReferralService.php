<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * ReferralService — share codes + the referrer credit.
 *
 * The credit is idempotent by construction: LedgerService uses
 * UNIQUE(ref_type, ref_id, reason), and we key the entry on the *referred*
 * user's id (ref_type 'referral', reason 'referral_bonus'). So creditReferrer()
 * can be called on every reward approval and the referrer is paid exactly once.
 */
class ReferralService
{
  /** A short, human-shareable, collision-checked code. */
  public static function generateCode(): string
  {
    global $wpdb;
    $t = $wpdb->prefix . 'ro_users';

    do {
      $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
      $taken = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE referral_code = %s LIMIT 1", $code));
    } while ($taken);

    return $code;
  }

  /** Resolve a referral code to a user id, or 0. */
  public static function referrerIdForCode(string $code): int
  {
    global $wpdb;
    $code = strtoupper(trim($code));
    if ($code === '') {
      return 0;
    }
    $t = $wpdb->prefix . 'ro_users';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE referral_code = %s LIMIT 1", $code));
  }

  /**
   * Credit the referrer of $userId (if any). Idempotent — safe to call on every
   * reward approval; the referrer is paid at most once per referred user.
   */
  public static function creditReferrer(int $userId): void
  {
    global $wpdb;
    $t = $wpdb->prefix . 'ro_users';

    $referrerId = (int) $wpdb->get_var($wpdb->prepare("SELECT referred_by FROM {$t} WHERE id = %d LIMIT 1", $userId));
    if ($referrerId <= 0) {
      return;
    }

    $bonus = (int) SimpleRO()->config('custom.referral.bonus_coins', 200);
    if ($bonus <= 0) {
      return;
    }

    LedgerService::entry($referrerId, $bonus, 'referral_bonus', 'referral', $userId);
  }

  /**
   * Referral summary for a user: their code + how many they referred + coins
   * earned from referrals.
   *
   * @return array{code:string,referredCount:int,coinsEarned:int,shareUrl:string}
   */
  public static function summary(int $userId): array
  {
    global $wpdb;
    $u = $wpdb->prefix . 'ro_users';
    $l = $wpdb->prefix . 'ro_coin_ledger';

    $code = (string) $wpdb->get_var($wpdb->prepare("SELECT referral_code FROM {$u} WHERE id = %d LIMIT 1", $userId));
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$u} WHERE referred_by = %d", $userId));
    $earned = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(delta),0) FROM {$l} WHERE user_id = %d AND reason = 'referral_bonus'",
      $userId
    ));

    return [
      'code'          => $code,
      'referredCount' => $count,
      'coinsEarned'   => $earned,
      'shareUrl'      => esc_url_raw(add_query_arg('ref', $code, home_url('/reward'))),
    ];
  }
}
