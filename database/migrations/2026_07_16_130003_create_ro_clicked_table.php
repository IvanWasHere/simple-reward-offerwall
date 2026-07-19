<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_clicked — a user opening an offerwall / offer. session_nonce is embedded in
 * the outbound URL (macro) so an incoming postback can be correlated back to the
 * click. offer_id is 0 for whole-offerwall (iframe) opens.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_clicked',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        provider_id bigint(20) unsigned NOT NULL default 0,
        offer_id bigint(20) unsigned NOT NULL default 0,
        provider_offer_id varchar(190) NOT NULL default '',
        user_id bigint(20) unsigned NOT NULL default 0,
        session_nonce char(32) NOT NULL default '',
        target_url text NULL,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY provider_id (provider_id),
        KEY user_id (user_id),
        KEY session_nonce (session_nonce)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
