<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoRedemption — maps to wp_ro_redemptions.
 */
class RoRedemption extends Model
{
  protected $table = 'ro_redemptions';
}
