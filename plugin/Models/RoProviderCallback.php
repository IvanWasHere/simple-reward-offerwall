<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoProviderCallback — maps to wp_ro_provider_callbacks.
 */
class RoProviderCallback extends Model
{
  protected $table = 'ro_provider_callbacks';
}
