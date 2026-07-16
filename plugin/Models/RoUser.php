<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoUser — maps to wp_ro_users.
 *
 * NOTE: the WPBones query builder does NOT escape values in where()/update()
 * (only insert() escapes). Use $wpdb->prepare() for any lookup built from
 * untrusted input (email, token). This model is a convenience for trusted
 * single-row writes/reads.
 */
class RoUser extends Model
{
  protected $table = 'ro_users';
}
