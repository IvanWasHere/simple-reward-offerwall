<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_callbacks — every received S2S postback (audit log). UNIQUE(provider_id,
 * transaction_id) enforces idempotency: a repeated postback is a no-op. `amount`
 * is the provider payout in currency units (not coins).
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_callbacks',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        provider_id bigint(20) unsigned NOT NULL default 0,
        provider_callback_id bigint(20) unsigned NOT NULL default 0,
        transaction_id varchar(190) NOT NULL default '',
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
        UNIQUE KEY provider_txn (provider_id, transaction_id),
        KEY user_id (user_id),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
