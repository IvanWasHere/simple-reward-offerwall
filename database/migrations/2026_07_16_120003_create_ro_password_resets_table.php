<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_password_resets — single-use, hashed, time-limited password reset tokens.
 * Only sha256(token) is stored.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_password_resets',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        token_hash char(64) NOT NULL default '',
        expires_at datetime NULL default NULL,
        used_at datetime NULL default NULL,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token_hash (token_hash),
        KEY user_id (user_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
