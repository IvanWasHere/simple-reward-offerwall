<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoOffer — maps to wp_simplerewardoffer_offers.
 */
class RoOffer extends Model
{
  protected $table = 'simplerewardoffer_offers';
}
