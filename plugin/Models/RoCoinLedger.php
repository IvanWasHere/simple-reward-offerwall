<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoCoinLedger — maps to wp_simplerewardoffer_coin_ledger. Append-only; balance = SUM(delta).
 */
class RoCoinLedger extends Model
{
  protected $table = 'simplerewardoffer_coin_ledger';
}
