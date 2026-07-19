<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoSession — maps to wp_simplerewardoffer_sessions. See RoUser note on escaping.
 */
class RoSession extends Model
{
  protected $table = 'simplerewardoffer_sessions';
}
