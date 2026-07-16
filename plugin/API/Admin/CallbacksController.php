<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * CallbacksController (admin) — read-only audit log of received S2S postbacks.
 */
class CallbacksController extends RestController
{
  public function index()
  {
    global $wpdb;
    $c = $wpdb->prefix . 'ro_callbacks';
    $pv = $wpdb->prefix . 'ro_providers';
    $u = $wpdb->prefix . 'ro_users';

    $conds = [];
    $args = [];

    $providerId = (int) $this->request->get_param('provider_id');
    if ($providerId > 0) {
      $conds[] = 'c.provider_id = %d';
      $args[] = $providerId;
    }

    $status = (string) $this->request->get_param('status');
    if (in_array($status, ['received', 'rejected', 'processed'], true)) {
      $conds[] = 'c.status = %s';
      $args[] = $status;
    }

    $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
    $sql = "SELECT c.id, c.provider_id, c.transaction_id, c.user_id, c.provider_offer_id,
                   c.amount, c.currency, c.status, c.signature_ok, c.ip, c.created_at,
                   p.name AS provider_name, u.email AS user_email
              FROM {$c} c
              LEFT JOIN {$pv} p ON p.id = c.provider_id
              LEFT JOIN {$u} u ON u.id = c.user_id
              {$where}
             ORDER BY c.id DESC
             LIMIT 500";

    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

    return $this->response(['callbacks' => $rows ?: []]);
  }
}
