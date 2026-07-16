<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_login_attempts — audit + rate-limit source. We count recent failures per
 * (email, ip) inside a sliding window to soft-lock brute-force attempts.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_login_attempts',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        email varchar(190) NOT NULL default '',
        ip varchar(45) NOT NULL default '',
        success tinyint(1) NOT NULL default 0,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY email (email),
        KEY ip (ip),
        KEY created_at (created_at)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
