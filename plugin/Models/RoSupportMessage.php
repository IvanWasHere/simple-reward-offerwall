<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoSupportMessage — maps to wp_ro_support_messages.
 */
class RoSupportMessage extends Model
{
  protected $table = 'ro_support_messages';
}
