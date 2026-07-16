<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoReward — maps to wp_ro_rewards.
 */
class RoReward extends Model
{
  protected $table = 'ro_rewards';
}
