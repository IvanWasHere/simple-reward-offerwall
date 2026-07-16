<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoPasswordReset — maps to wp_ro_password_resets. See RoUser note on escaping.
 */
class RoPasswordReset extends Model
{
  protected $table = 'ro_password_resets';
}
