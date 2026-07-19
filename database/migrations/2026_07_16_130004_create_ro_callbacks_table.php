<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_callbacks — every received S2S postback (audit log). Idempotency key is
 * UNIQUE(provider_id, transaction_id, callback_type): a network (e.g. ayet) shares
 * one transaction_id across a paid conversion and its "optional" (visible-unpaid)
 * callback, so callback_type keeps them as distinct rows. `amount` is the provider
 * payout in currency units (not coins).
 */
return new class extends Migration {
  public function up()
  {
    global $wpdb;

    $this->create(
      'ro_callbacks',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        provider_id bigint(20) unsigned NOT NULL default 0,
        provider_callback_id bigint(20) unsigned NOT NULL default 0,
        transaction_id varchar(190) NOT NULL default '',
        callback_type varchar(30) NOT NULL default '',
        user_id bigint(20) unsigned NOT NULL default 0,
        provider_offer_id varchar(190) NOT NULL default '',
        task_id varchar(190) NOT NULL default '',
        amount decimal(20,6) NOT NULL default '0.000000',
        currency varchar(10) NOT NULL default '',
        raw_payload longtext NULL,
        mapped longtext NULL,
        signature_ok tinyint(1) NOT NULL default 0,
        status varchar(20) NOT NULL default 'received',
        ip varchar(45) NOT NULL default '',
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY provider_txn_type (provider_id, transaction_id, callback_type),
        KEY user_id (user_id),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );

    // dbDelta adds the callback_type column + new composite index on an existing
    // table but cannot drop the old 2-column UNIQUE, which would still block the
    // conversion/optional shared-txn pair. Retire it explicitly (idempotent).
    $table = $wpdb->prefix . 'ro_callbacks';
    if ($wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'provider_txn'")) {
      $wpdb->query("ALTER TABLE {$table} DROP INDEX provider_txn");
    }
    if (!$wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'provider_txn_type'")) {
      $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY provider_txn_type (provider_id, transaction_id, callback_type)");
    }
  }
};
