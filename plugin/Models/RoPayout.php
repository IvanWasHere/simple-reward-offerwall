<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoPayout — maps to wp_simplerewardoffer_payouts (redeemable rewards catalog).
 */
class RoPayout extends Model
{
  protected $table = 'simplerewardoffer_payouts';
}
