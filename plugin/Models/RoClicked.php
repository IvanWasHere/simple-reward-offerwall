<?php

namespace SimpleRewardOffer\Models;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Model;

/**
 * RoClicked — maps to wp_simplerewardoffer_clicked.
 */
class RoClicked extends Model
{
  protected $table = 'simplerewardoffer_clicked';
}
