<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_redemptions — a user's request to redeem coins for a payout. Coins are
 * reserved (debited) at request time inside a transaction; approval settles the
 * debit, rejection writes a compensating refund entry. InnoDB for FOR UPDATE.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_redemptions',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        payout_id bigint(20) unsigned NOT NULL default 0,
        coins_spent bigint(20) unsigned NOT NULL default 0,
        status varchar(20) NOT NULL default 'pending',
        approved_by bigint(20) unsigned NOT NULL default 0,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY payout_id (payout_id),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
