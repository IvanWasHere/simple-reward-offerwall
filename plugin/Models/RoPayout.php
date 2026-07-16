<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoPayout — maps to wp_ro_payouts (redeemable rewards catalog).
 */
class RoPayout extends Model
{
  protected $table = 'ro_payouts';
}
