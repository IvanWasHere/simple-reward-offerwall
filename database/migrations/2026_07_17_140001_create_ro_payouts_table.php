<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_payouts — the redeemable rewards catalog (e.g. gift cards).
 * value_money is stored in integer minor units (cents); value_coins is the coin
 * price. stock = -1 means unlimited.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_payouts',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        name varchar(190) NOT NULL default '',
        value_money bigint(20) NOT NULL default 0,
        value_coins bigint(20) unsigned NOT NULL default 0,
        currency varchar(10) NOT NULL default 'USD',
        small_icon varchar(255) NOT NULL default '',
        midsize_icon varchar(255) NOT NULL default '',
        large_icon varchar(255) NOT NULL default '',
        stock int(11) NOT NULL default -1,
        status varchar(20) NOT NULL default 'active',
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
