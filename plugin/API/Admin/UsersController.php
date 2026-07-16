<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * UsersController (admin) — manage accounts: search, block/unblock, change type.
 * Blocking or changing an account's type revokes all of that user's sessions
 * immediately. Admins cannot block or demote themselves (lockout guard).
 */
class UsersController extends RestController
{
  private const TYPES = ['user', 'support', 'admin'];

  public function index()
  {
    global $wpdb;
    $u = $wpdb->prefix . 'ro_users';
    $l = $wpdb->prefix . 'ro_coin_ledger';

    $conds = [];
    $args = [];

    $type = (string) $this->request->get_param('type');
    if (in_array($type, self::TYPES, true)) {
      $conds[] = 'u.type = %s';
      $args[] = $type;
    }

    $status = (string) $this->request->get_param('status');
    if (in_array($status, ['active', 'blocked'], true)) {
      $conds[] = 'u.status = %s';
      $args[] = $status;
    }

    $q = trim((string) $this->request->get_param('q'));
    if ($q !== '') {
      $conds[] = 'u.email LIKE %s';
      $args[] = '%' . $wpdb->esc_like($q) . '%';
    }

    $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
    $sql = "SELECT u.id, u.email, u.type, u.status, u.display_name, u.created_at,
                   COALESCE(SUM(l.delta),0) AS balance
              FROM {$u} u
              LEFT JOIN {$l} l ON l.user_id = u.id
              {$where}
             GROUP BY u.id
             ORDER BY u.id DESC
             LIMIT 500";

    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

    return $this->response(['users' => array_map([$this, 'present'], $rows ?: [])]);
  }

  public function show()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');

    $u = $wpdb->get_row($wpdb->prepare(
      "SELECT id, email, type, status, display_name, created_at FROM {$wpdb->prefix}ro_users WHERE id = %d",
      $id
    ));
    if (!$u) {
      return $this->responseError('ro_not_found', __('User not found.', 'simple-reward-offerwall'), 404);
    }

    $counts = [
      'rewards'     => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ro_rewards WHERE user_id = %d", $id)),
      'redemptions' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ro_redemptions WHERE user_id = %d", $id)),
      'tickets'     => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ro_support_requests WHERE user_id = %d", $id)),
    ];

    return $this->response([
      'user'    => $this->present($u) + ['balance' => LedgerService::balance($id)],
      'counts'  => $counts,
    ]);
  }

  public function update()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $me = Guard::user($this->request);

    $current = $wpdb->get_row($wpdb->prepare(
      "SELECT id, type, status FROM {$wpdb->prefix}ro_users WHERE id = %d",
      $id
    ));
    if (!$current) {
      return $this->responseError('ro_not_found', __('User not found.', 'simple-reward-offerwall'), 404);
    }

    $newType = $this->request->get_param('type');
    $newStatus = $this->request->get_param('status');

    $update = [];
    $typeChanged = false;
    $blocked = false;

    if ($newType !== null) {
      if (!in_array($newType, self::TYPES, true)) {
        return $this->responseError('ro_invalid', __('Invalid type.', 'simple-reward-offerwall'), 422);
      }
      if ((int) $id === (int) $me->id && $newType !== $current->type) {
        return $this->responseError('ro_self', __('You cannot change your own role.', 'simple-reward-offerwall'), 422);
      }
      if ($newType !== $current->type) {
        $update['type'] = $newType;
        $typeChanged = true;
      }
    }

    if ($newStatus !== null) {
      if (!in_array($newStatus, ['active', 'blocked'], true)) {
        return $this->responseError('ro_invalid', __('Invalid status.', 'simple-reward-offerwall'), 422);
      }
      if ((int) $id === (int) $me->id && $newStatus === 'blocked') {
        return $this->responseError('ro_self', __('You cannot block yourself.', 'simple-reward-offerwall'), 422);
      }
      $update['status'] = $newStatus;
      $blocked = $newStatus === 'blocked';
    }

    if (!$update) {
      return $this->responseError('ro_invalid', __('Nothing to update.', 'simple-reward-offerwall'), 422);
    }

    $update['updated_at'] = gmdate('Y-m-d H:i:s');
    $wpdb->update($wpdb->prefix . 'ro_users', $update, ['id' => $id], null, ['%d']);

    // Any privilege/status change invalidates existing sessions.
    if ($typeChanged || $blocked) {
      Guard::revokeAllForUser($id);
    }

    return $this->response(['user' => $this->present($wpdb->get_row($wpdb->prepare(
      "SELECT id, email, type, status, display_name, created_at FROM {$wpdb->prefix}ro_users WHERE id = %d",
      $id
    )))]);
  }

  private function present($row): array
  {
    if (!$row) {
      return [];
    }
    $out = [
      'id'          => (int) $row->id,
      'email'       => $row->email,
      'type'        => $row->type,
      'status'      => $row->status,
      'displayName' => $row->display_name,
      'createdAt'   => $row->created_at,
    ];
    if (isset($row->balance)) {
      $out['balance'] = (int) $row->balance;
    }
    return $out;
  }
}
