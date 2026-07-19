<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoSupportRequest — maps to wp_simplerewardoffer_support_requests.
 */
class RoSupportRequest extends Model
{
  protected $table = 'simplerewardoffer_support_requests';
}
