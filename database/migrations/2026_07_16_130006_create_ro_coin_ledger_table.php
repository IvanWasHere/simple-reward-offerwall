<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_coin_ledger — append-only, the single source of truth for coin balances.
 * balance = SUM(delta) per user. UNIQUE(ref_type, ref_id, reason) makes each
 * credit/debit idempotent (a duplicate approve cannot double-credit). Never
 * delete or update rows; corrections are compensating entries.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_coin_ledger',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        delta bigint(20) NOT NULL default 0,
        reason varchar(40) NOT NULL default '',
        ref_type varchar(40) NOT NULL default '',
        ref_id bigint(20) unsigned NOT NULL default 0,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY ref_unique (ref_type, ref_id, reason),
        KEY user_id (user_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
