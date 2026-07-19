<?php

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Database\Migrations\Migration;

/**
 * simplerewardoffer_support_messages — threaded replies on a support ticket. author_type records
 * whether the author was the user or a staff member (support/admin).
 */
return new class extends Migration {
  public function up()
  {
    $this->create(
      'simplerewardoffer_support_messages',
      "(
        id bigint(20) unsigned NOT NULL auto_increment,
        ticket_id bigint(20) unsigned NOT NULL default 0,
        author_id bigint(20) unsigned NOT NULL default 0,
        author_type varchar(20) NOT NULL default 'user',
        body text NULL,
        created_at datetime NULL default NULL,
        PRIMARY KEY  (id),
        KEY ticket_id (ticket_id)
      ) ENGINE=InnoDB {$this->charsetCollate};"
    );
  }
};
