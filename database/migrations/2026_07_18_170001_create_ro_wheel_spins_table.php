<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_wheel_spins — one row per daily Lucky Wheel spin. UNIQUE(user_id, spin_date)
 * enforces one spin per UTC day (the second insert of the day fails). The credited
 * coins are recorded here and mirrored into simplerewardoffer_coin_ledger (reason 'wheel_spin',
 * ref_type 'wheel', ref_id = this row's id) so the ledger stays the source of truth.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_wheel_spins',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        spin_date date NOT NULL,
        segment_index int(11) NOT NULL default 0,
        coins bigint(20) NOT NULL default 0,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_day (user_id, spin_date),
        KEY user_id (user_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
