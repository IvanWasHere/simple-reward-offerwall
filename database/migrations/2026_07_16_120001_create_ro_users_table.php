<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_users — offerwall accounts (user / admin / support), fully independent of wp_users.
 * Passwords are stored as password_hash() output. Timestamps are UTC, written by the app.
 *
 * referral_code: this user's own share code. referred_by: the user id that referred
 * them (0 = organic). dbDelta is additive, so adding these columns re-runs safely.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_users',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        unique_user_hash char(32) NOT NULL default '',
        email varchar(190) NOT NULL default '',
        password_hash varchar(255) NOT NULL default '',
        type varchar(20) NOT NULL default 'user',
        status varchar(20) NOT NULL default 'active',
        display_name varchar(190) NOT NULL default '',
        referral_code varchar(20) NOT NULL default '',
        referred_by bigint(20) unsigned NOT NULL default 0,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_user_hash (unique_user_hash),
        UNIQUE KEY email (email),
        KEY type (type),
        KEY status (status),
        KEY referral_code (referral_code)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
