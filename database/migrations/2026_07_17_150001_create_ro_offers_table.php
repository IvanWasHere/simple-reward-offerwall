<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_offers — offers pulled from a provider (primarily static_api, saved on a
 * schedule; offerwall_api offers are usually served live/cached, not stored).
 * Deduped by (provider_id, provider_offer_id). total_payout is the provider's
 * payout in currency units; raw_json keeps the untouched source for audit.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_offers',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        provider_id bigint(20) unsigned NOT NULL default 0,
        provider_offer_id varchar(190) NOT NULL default '',
        name varchar(255) NOT NULL default '',
        tasks longtext NULL,
        total_payout decimal(20,6) NOT NULL default '0.000000',
        device varchar(60) NOT NULL default '',
        os varchar(60) NOT NULL default '',
        country varchar(190) NOT NULL default '',
        icons longtext NULL,
        link text NULL,
        raw_json longtext NULL,
        active tinyint(1) NOT NULL default 1,
        admin_disabled tinyint(1) NOT NULL default 0,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY provider_offer (provider_id, provider_offer_id),
        KEY active (active),
        KEY admin_disabled (admin_disabled)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
