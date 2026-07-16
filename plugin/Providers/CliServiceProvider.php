<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Support\ServiceProvider;

/**
 * CliServiceProvider — registers `wp simple-ro make-admin` for bootstrapping /
 * promoting staff accounts (there is no self-service path to admin/support).
 *
 *   wp simple-ro make-admin --email=you@example.com --password=secret1234 [--type=admin|support]
 */
class CliServiceProvider extends ServiceProvider
{
  public function register()
  {
    if (!defined('WP_CLI') || !WP_CLI) {
      return;
    }

    \WP_CLI::add_command('simple-ro make-admin', [$this, 'makeAdmin']);
  }

  /**
   * @param array $args
   * @param array $assoc  --email, --password, --type, --name
   */
  public function makeAdmin($args, $assoc)
  {
    global $wpdb;

    $email = strtolower(sanitize_email($assoc['email'] ?? ''));
    $password = (string) ($assoc['password'] ?? '');
    $type = $assoc['type'] ?? 'admin';
    if (!in_array($type, ['admin', 'support', 'user'], true)) {
      $type = 'admin';
    }
    $name = sanitize_text_field($assoc['name'] ?? '');

    if (!is_email($email)) {
      \WP_CLI::error('A valid --email is required.');
    }

    $users = $wpdb->prefix . 'ro_users';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$users} WHERE email = %s LIMIT 1", $email));
    $now = gmdate('Y-m-d H:i:s');

    if ($existing) {
      $update = ['type' => $type, 'status' => 'active', 'updated_at' => $now];
      if ($password !== '') {
        $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
      }
      $wpdb->update($users, $update, ['id' => (int) $existing->id]);
      \WP_CLI::success("Updated {$email} to type '{$type}' (id {$existing->id}).");
      return;
    }

    if (strlen($password) < 10) {
      \WP_CLI::error('New accounts need --password of at least 10 characters.');
    }

    $wpdb->insert($users, [
      'unique_user_hash' => bin2hex(random_bytes(16)),
      'email'            => $email,
      'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
      'type'             => $type,
      'status'           => 'active',
      'display_name'     => $name,
      'created_at'       => $now,
      'updated_at'       => $now,
    ]);

    \WP_CLI::success("Created {$type} account {$email} (id {$wpdb->insert_id}).");
  }
}
