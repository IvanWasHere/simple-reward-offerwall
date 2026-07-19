<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoProviderCallback — maps to wp_simplerewardoffer_provider_callbacks.
 */
class RoProviderCallback extends Model
{
  protected $table = 'simplerewardoffer_provider_callbacks';
}
