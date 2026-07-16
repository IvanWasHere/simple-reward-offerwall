<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoSupportRequest — maps to wp_ro_support_requests.
 */
class RoSupportRequest extends Model
{
  protected $table = 'ro_support_requests';
}
