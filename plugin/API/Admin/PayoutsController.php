<?php

namespace SimpleRewardOffer\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * PayoutsController (admin) — CRUD for the redeemable rewards catalog.
 * Guarded by Guard::role('admin').
 */
class PayoutsController extends RestController
{
  public function index()
  {
    global $wpdb;
    $t = $wpdb->prefix . 'simplerewardoffer_payouts';
    $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC");
    return $this->response(['payouts' => array_map([$this, 'present'], $rows ?: [])]);
  }

  public function store()
  {
    global $wpdb;
    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert($wpdb->prefix . 'simplerewardoffer_payouts', $data + ['created_at' => $now, 'updated_at' => $now]);

    return $this->response(['payout' => $this->find((int) $wpdb->insert_id)], 201);
  }

  public function update()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $t = $wpdb->prefix . 'simplerewardoffer_payouts';

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE id = %d", $id))) {
      return $this->responseError('ro_not_found', __('Payout not found.', 'simple-reward-offerwall'), 404);
    }

    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $data['updated_at'] = gmdate('Y-m-d H:i:s');
    $wpdb->update($t, $data, ['id' => $id], null, ['%d']);

    return $this->response(['payout' => $this->find($id)]);
  }

  public function destroy()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $deleted = $wpdb->delete($wpdb->prefix . 'simplerewardoffer_payouts', ['id' => $id], ['%d']);

    if (!$deleted) {
      return $this->responseError('ro_not_found', __('Payout not found.', 'simple-reward-offerwall'), 404);
    }

    return $this->response(['deleted' => true]);
  }

  /* ---------------------------------------------------------------- */

  /** @return array|\WP_Error */
  private function validated()
  {
    $name = sanitize_text_field((string) $this->request->get_param('name'));
    if ($name === '') {
      return $this->responseError('ro_invalid', __('Name is required.', 'simple-reward-offerwall'), 422);
    }

    $coins = (int) $this->request->get_param('value_coins');
    if ($coins <= 0) {
      return $this->responseError('ro_invalid', __('Coin price must be greater than zero.', 'simple-reward-offerwall'), 422);
    }

    $status = (string) $this->request->get_param('status');
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

    $stockParam = $this->request->get_param('stock');
    $stock = ($stockParam === null || $stockParam === '') ? -1 : (int) $stockParam;

    return [
      'name'         => $name,
      'value_money'  => (int) $this->request->get_param('value_money'),
      'value_coins'  => $coins,
      'currency'     => substr(sanitize_text_field((string) ($this->request->get_param('currency') ?: 'USD')), 0, 10),
      'small_icon'   => esc_url_raw((string) $this->request->get_param('small_icon')),
      'midsize_icon' => esc_url_raw((string) $this->request->get_param('midsize_icon')),
      'large_icon'   => esc_url_raw((string) $this->request->get_param('large_icon')),
      'stock'        => $stock,
      'status'       => $status,
    ];
  }

  private function find(int $id): array
  {
    global $wpdb;
    $t = $wpdb->prefix . 'simplerewardoffer_payouts';
    return $this->present($wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id)));
  }

  private function present($row): array
  {
    if (!$row) {
      return [];
    }
    return [
      'id'          => (int) $row->id,
      'name'        => $row->name,
      'valueMoney'  => (int) $row->value_money,
      'valueCoins'  => (int) $row->value_coins,
      'currency'    => $row->currency,
      'smallIcon'   => $row->small_icon,
      'midsizeIcon' => $row->midsize_icon,
      'largeIcon'   => $row->large_icon,
      'stock'       => (int) $row->stock,
      'status'      => $row->status,
      'createdAt'   => $row->created_at,
    ];
  }
}
