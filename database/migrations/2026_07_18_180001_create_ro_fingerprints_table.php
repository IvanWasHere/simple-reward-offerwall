<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_fingerprints — a device/browser fingerprint captured each time a user logs
 * in. The client runs fingerprinter-js (19 collectors + bot detection); the server
 * adds IP + request user-agent. `visitor_id` is the library's stable SHA-256 hash
 * so the same device is recognisable across logins; `data` holds the FULL library
 * result (all collectors under `components`, plus confidence/entropy/suspectAnalysis).
 * The indexed columns (platform/language/languages/timezone/screen) come from a
 * small navigator summary for filtering/display; `country` is the 2-letter ISO
 * code resolved server-side from the request IP (Services/GeoIp). Shown to admins
 * on the user detail page; admins can delete old rows.
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
        country char(2) NOT NULL default '',
        language varchar(60) NOT NULL default '',
        languages varchar(190) NOT NULL default '',
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
