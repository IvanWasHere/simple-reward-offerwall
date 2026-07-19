<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoProvider — maps to wp_simplerewardoffer_providers. Use $wpdb->prepare for untrusted lookups.
 */
class RoProvider extends Model
{
  protected $table = 'simplerewardoffer_providers';
}
