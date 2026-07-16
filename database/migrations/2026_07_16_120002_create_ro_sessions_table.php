<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_sessions — opaque server-side sessions. We store only sha256(token); the raw
 * token lives in the httpOnly `ro_session` cookie. csrf_hash backs the double-submit
 * CSRF check. InnoDB for future FOR UPDATE / transactional revocation.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_sessions',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        token_hash char(64) NOT NULL default '',
        csrf_hash char(64) NOT NULL default '',
        account_type varchar(20) NOT NULL default 'user',
        ip varchar(45) NOT NULL default '',
        user_agent varchar(255) NOT NULL default '',
        created_at datetime NULL default NULL,
        last_used_at datetime NULL default NULL,
        expires_at datetime NULL default NULL,
        revoked_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token_hash (token_hash),
        KEY user_id (user_id),
        KEY expires_at (expires_at)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
