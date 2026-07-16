<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoCallback — maps to wp_ro_callbacks.
 */
class RoCallback extends Model
{
  protected $table = 'ro_callbacks';
}
