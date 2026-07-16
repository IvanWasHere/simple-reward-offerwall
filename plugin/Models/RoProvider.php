<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoProvider — maps to wp_ro_providers. Use $wpdb->prepare for untrusted lookups.
 */
class RoProvider extends Model
{
  protected $table = 'ro_providers';
}
