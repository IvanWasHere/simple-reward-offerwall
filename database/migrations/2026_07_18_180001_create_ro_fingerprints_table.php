<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_fingerprints — a device/browser fingerprint captured each time a user logs
 * in (client collects navigator/screen/timezone signals; the server adds IP +
 * request user-agent). visitor_id is a sha256 of the stable signal subset so the
 * same device is recognisable across logins. Shown to admins on the user detail
 * page; admins can delete old rows.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_fingerprints',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        visitor_id char(64) NOT NULL default '',
        ip varchar(45) NOT NULL default '',
        user_agent varchar(512) NOT NULL default '',
        platform varchar(120) NOT NULL default '',
        language varchar(60) NOT NULL default '',
        timezone varchar(80) NOT NULL default '',
        screen varchar(40) NOT NULL default '',
        data longtext NULL,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY visitor_id (visitor_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
