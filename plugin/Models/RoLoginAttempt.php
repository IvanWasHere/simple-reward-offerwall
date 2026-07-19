<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoLoginAttempt — maps to wp_simplerewardoffer_login_attempts. See RoUser note on escaping.
 */
class RoLoginAttempt extends Model
{
  protected $table = 'simplerewardoffer_login_attempts';
}
