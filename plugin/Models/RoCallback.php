<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoCallback — maps to wp_simplerewardoffer_callbacks.
 */
class RoCallback extends Model
{
  protected $table = 'simplerewardoffer_callbacks';
}
