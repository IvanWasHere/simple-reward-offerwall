<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoSession — maps to wp_ro_sessions. See RoUser note on escaping.
 */
class RoSession extends Model
{
  protected $table = 'ro_sessions';
}
