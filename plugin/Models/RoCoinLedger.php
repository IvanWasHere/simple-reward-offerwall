<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoCoinLedger — maps to wp_ro_coin_ledger. Append-only; balance = SUM(delta).
 */
class RoCoinLedger extends Model
{
  protected $table = 'ro_coin_ledger';
}
