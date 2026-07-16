<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_rewards — a coin reward derived from a callback, pending admin approval.
 * coins_value is signed (negative for chargebacks/reversals). On approval a
 * matching ro_coin_ledger entry is written.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_rewards',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        callback_id bigint(20) unsigned NOT NULL default 0,
        coins_value bigint(20) NOT NULL default 0,
        status varchar(20) NOT NULL default 'pending',
        approved_by bigint(20) unsigned NOT NULL default 0,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY callback_id (callback_id),
        KEY user_id (user_id),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
