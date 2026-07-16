<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoLoginAttempt — maps to wp_ro_login_attempts. See RoUser note on escaping.
 */
class RoLoginAttempt extends Model
{
  protected $table = 'ro_login_attempts';
}
