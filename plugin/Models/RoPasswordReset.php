<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoPasswordReset — maps to wp_simplerewardoffer_password_resets. See RoUser note on escaping.
 */
class RoPasswordReset extends Model
{
  protected $table = 'simplerewardoffer_password_resets';
}
