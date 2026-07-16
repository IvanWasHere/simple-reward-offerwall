<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoOffer — maps to wp_ro_offers.
 */
class RoOffer extends Model
{
  protected $table = 'ro_offers';
}
