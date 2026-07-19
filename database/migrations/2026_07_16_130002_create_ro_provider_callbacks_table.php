<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_provider_callbacks — S2S postback configs (a provider can have many).
 *
 * The provider is given .../callback/{unique_hash}. `param_map` maps incoming
 * request keys to our canonical fields (transaction_id, user_id, amount,
 * provider_offer_id, task_id, currency, status). Authenticity is verified with a
 * signature: `signature_algo` (hmac_sha256 | sha256_concat | md5_concat | none)
 * over the string built per `signature_source` using `secret`. `signature_source`
 * is `ordered_params` or `concat:<field>,<field>,…` (e.g. Lootably).
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_provider_callbacks',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        provider_id bigint(20) unsigned NOT NULL default 0,
        name varchar(190) NOT NULL default '',
        unique_hash char(32) NOT NULL default '',
        param_map longtext NULL,
        signature_param varchar(64) NOT NULL default '',
        signature_algo varchar(20) NOT NULL default 'hmac_sha256',
        signature_source varchar(190) NOT NULL default 'ordered_params',
        secret varchar(255) NOT NULL default '',
        ip_allowlist text NULL,
        active tinyint(1) NOT NULL default 1,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_hash (unique_hash),
        KEY provider_id (provider_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
