<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoReward — maps to wp_simplerewardoffer_rewards.
 */
class RoReward extends Model
{
  protected $table = 'simplerewardoffer_rewards';
}
