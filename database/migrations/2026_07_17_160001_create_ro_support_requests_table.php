<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Database\Migrations\Migration;

/**
 * ro_support_requests — support tickets opened by users. assigned_to is a support
 * (or admin) ro_users id, 0 = unassigned. status: open | pending | closed.
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'ro_support_requests',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        user_id bigint(20) unsigned NOT NULL default 0,
        subject varchar(255) NOT NULL default '',
        status varchar(20) NOT NULL default 'open',
        assigned_to bigint(20) unsigned NOT NULL default 0,
        last_message_at datetime NULL default NULL,
        created_at datetime NULL default NULL,
        updated_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY assigned_to (assigned_to)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
