<?php

namespace SimpleRO\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Model;

/**
 * RoClicked — maps to wp_ro_clicked.
 */
class RoClicked extends Model
{
  protected $table = 'ro_clicked';
}
