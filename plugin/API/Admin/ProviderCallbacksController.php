<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * ProviderCallbacksController (admin) — CRUD for a provider's S2S callback configs.
 * Routes: /admin/providers/{id}/callbacks[/{cbId}]. Guarded by Guard::role('admin').
 */
class ProviderCallbacksController extends RestController
{
  private const ALGOS = ['hmac_sha256', 'md5_concat', 'none'];

  public function index()
  {
    global $wpdb;
    $providerId = (int) $this->request->get_param('id');
    $t = $wpdb->prefix . 'ro_provider_callbacks';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$t} WHERE provider_id = %d ORDER BY id DESC",
      $providerId
    ));

    return $this->response(['callbacks' => array_map([$this, 'present'], $rows ?: [])]);
  }

  public function store()
  {
    global $wpdb;
    $providerId = (int) $this->request->get_param('id');

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ro_providers WHERE id = %d", $providerId))) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }

    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
      $wpdb->prefix . 'ro_provider_callbacks',
      $data + [
        'provider_id' => $providerId,
        'unique_hash' => bin2hex(random_bytes(16)),
        'created_at'  => $now,
        'updated_at'  => $now,
      ]
    );

    return $this->response(['callback' => $this->find((int) $wpdb->insert_id)], 201);
  }

  public function update()
  {
    global $wpdb;
    $cbId = (int) $this->request->get_param('cbId');
    $t = $wpdb->prefix . 'ro_provider_callbacks';

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE id = %d", $cbId))) {
      return $this->responseError('ro_not_found', __('Callback not found.', 'simple-reward-offerwall'), 404);
    }

    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $data['updated_at'] = gmdate('Y-m-d H:i:s');
    $wpdb->update($t, $data, ['id' => $cbId], null, ['%d']);

    return $this->response(['callback' => $this->find($cbId)]);
  }

  public function destroy()
  {
    global $wpdb;
    $cbId = (int) $this->request->get_param('cbId');
    $deleted = $wpdb->delete($wpdb->prefix . 'ro_provider_callbacks', ['id' => $cbId], ['%d']);

    if (!$deleted) {
      return $this->responseError('ro_not_found', __('Callback not found.', 'simple-reward-offerwall'), 404);
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

    $algo = (string) $this->request->get_param('signature_algo');
    $algo = in_array($algo, self::ALGOS, true) ? $algo : 'hmac_sha256';

    $active = $this->request->get_param('active');
    $active = ($active === null) ? 1 : (int) (bool) $active;

    return [
      'name'             => $name,
      'param_map'        => wp_json_encode($this->asObject($this->request->get_param('param_map'))),
      'signature_param'  => sanitize_text_field((string) $this->request->get_param('signature_param')),
      'signature_algo'   => $algo,
      'signature_source' => sanitize_text_field((string) ($this->request->get_param('signature_source') ?: 'ordered_params')),
      'secret'           => (string) $this->request->get_param('secret'),
      'ip_allowlist'     => sanitize_text_field((string) $this->request->get_param('ip_allowlist')),
      'active'           => $active,
    ];
  }

  private function asObject($value): array
  {
    if (is_array($value)) {
      return $value;
    }
    if (is_string($value) && $value !== '') {
      $decoded = json_decode($value, true);
      return is_array($decoded) ? $decoded : [];
    }
    return [];
  }

  private function find(int $id): array
  {
    global $wpdb;
    $t = $wpdb->prefix . 'ro_provider_callbacks';
    return $this->present($wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id)));
  }

  private function present($row): array
  {
    if (!$row) {
      return [];
    }
    return [
      'id'              => (int) $row->id,
      'providerId'      => (int) $row->provider_id,
      'name'            => $row->name,
      'uniqueHash'      => $row->unique_hash,
      'callbackUrl'     => esc_url_raw(rest_url('simple-ro/v1/callback/' . $row->unique_hash)),
      'paramMap'        => json_decode((string) $row->param_map, true) ?: (object) [],
      'signatureParam'  => $row->signature_param,
      'signatureAlgo'   => $row->signature_algo,
      'signatureSource' => $row->signature_source,
      'hasSecret'       => $row->secret !== '',
      'ipAllowlist'     => $row->ip_allowlist,
      'active'          => (int) $row->active === 1,
      'createdAt'       => $row->created_at,
    ];
  }
}
