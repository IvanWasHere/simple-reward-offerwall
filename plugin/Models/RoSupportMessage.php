<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoSupportMessage — maps to wp_simplerewardoffer_support_messages.
 */
class RoSupportMessage extends Model
{
  protected $table = 'simplerewardoffer_support_messages';
}
