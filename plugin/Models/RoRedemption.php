<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoRedemption — maps to wp_simplerewardoffer_redemptions.
 */
class RoRedemption extends Model
{
  protected $table = 'simplerewardoffer_redemptions';
}
