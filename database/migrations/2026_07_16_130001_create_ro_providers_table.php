<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_providers — an offerwall network integration.
 *
 * type: 'iframe' (web offerwall shown in an <iframe>), 'offerwall_api' (JSON we
 * render), or 'static_api' (pulled on a schedule). `url` is a template holding
 * {macro_*} tokens; `macros` maps each token to a source key (user_id, adslot_id,
 * session_id, user_hash). `coin_rate` = coins granted per 1.00 of provider payout.
 * `offer_schema` names a built-in OfferSchema (OfferSchemaRegistry) that drives
 * offer field mapping + callback interpretation ('' = legacy config.field_map).
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_providers',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        unique_provider_hash char(32) NOT NULL default '',
        name varchar(190) NOT NULL default '',
        type varchar(30) NOT NULL default 'iframe',
        url text NULL,
        macros longtext NULL,
        adslot_id varchar(190) NOT NULL default '',
        api_key varchar(255) NOT NULL default '',
        api_secret varchar(255) NOT NULL default '',
        coin_rate decimal(16,4) NOT NULL default '1.0000',
        offer_schema varchar(60) NOT NULL default '',
        config longtext NULL,
        status varchar(20) NOT NULL default 'active',
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_provider_hash (unique_provider_hash),
        KEY type (type),
        KEY status (status)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
