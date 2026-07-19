<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Schemas\OfferSchemaRegistry;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * ProviderCallbacksController (admin) — CRUD for a provider's S2S callback configs.
 * Routes: /admin/providers/{id}/callbacks[/{cbId}]. Guarded by Guard::role('admin').
 */
class ProviderCallbacksController extends RestController
{
  private const ALGOS = ['hmac_sha256', 'sha256_concat', 'md5_concat', 'none'];

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

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $cbId));
    if (!$existing) {
      return $this->responseError('ro_not_found', __('Callback not found.', 'simple-reward-offerwall'), 404);
    }

    $data = $this->validated($existing);
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

  /**
   * @param object|null $existing the current row on update (null on create); lets
   *                              a blank secret keep the stored one.
   * @return array|\WP_Error
   */
  private function validated(?object $existing = null)
  {
    global $wpdb;

    $name = sanitize_text_field((string) $this->request->get_param('name'));
    if ($name === '') {
      return $this->responseError('ro_invalid', __('Name is required.', 'simple-reward-offerwall'), 422);
    }

    // The provider may name a built-in schema that carries its own signature
    // defaults, and may self-authenticate the caller (ayet's external_identifier),
    // which decides whether 'none' is safe on a live callback.
    $providerId = (int) $this->request->get_param('id');
    $schema = OfferSchemaRegistry::for(
      (string) $wpdb->get_var($wpdb->prepare(
        "SELECT offer_schema FROM {$wpdb->prefix}ro_providers WHERE id = %d",
        $providerId
      ))
    );
    $schemaAlgo = $schema ? $schema->defaultSignatureAlgo() : 'hmac_sha256';

    $algo = (string) $this->request->get_param('signature_algo');
    $algo = in_array($algo, self::ALGOS, true) ? $algo : $schemaAlgo;

    $active = $this->request->get_param('active');
    $active = ($active === null) ? 1 : (int) (bool) $active;

    $secret = (string) $this->request->get_param('secret');
    // On update a blank secret means "keep the stored one" (never surfaced to the
    // client), so it doesn't have to be re-entered to edit other fields.
    $keepSecret = $existing && $secret === '' && (string) $existing->secret !== '';

    // A signed algorithm with an empty secret makes the HMAC forgeable by anyone
    // holding the callback URL — refuse it.
    if ($algo !== 'none' && !$keepSecret && strlen($secret) < 8) {
      return $this->responseError('ro_weak_secret', __('A signing secret of at least 8 characters is required.', 'simple-reward-offerwall'), 422);
    }

    // 'none' disables signature checking. It's only allowed live when the schema
    // self-authenticates the caller another way (ayet's verified
    // external_identifier); otherwise it's testing-only, permitted just on an
    // inactive callback.
    $allowsUnsigned = $schema && $schema->allowsUnsignedCallbacks();
    if ($algo === 'none' && $active === 1 && !$allowsUnsigned) {
      return $this->responseError('ro_unsafe_algo', __('Signature "none" is only allowed on an inactive callback.', 'simple-reward-offerwall'), 422);
    }

    // Pre-fill the param map / signature param from the schema when the admin left
    // them blank, so a schema provider's callbacks work out of the box.
    $paramMap = $this->asObject($this->request->get_param('param_map'));
    if (!$paramMap && $schema) {
      $paramMap = $schema->defaultParamMap();
    }
    $signatureParam = sanitize_text_field((string) $this->request->get_param('signature_param'));
    if ($signatureParam === '' && $schema) {
      $signatureParam = $schema->defaultSignatureParam();
    }
    $signatureSource = sanitize_text_field((string) $this->request->get_param('signature_source'));
    if ($signatureSource === '') {
      $signatureSource = $schema ? $schema->defaultSignatureSource() : 'ordered_params';
    }

    $data = [
      'name'             => $name,
      'param_map'        => wp_json_encode($paramMap),
      'signature_param'  => $signatureParam,
      'signature_algo'   => $algo,
      'signature_source' => $signatureSource,
      'ip_allowlist'     => sanitize_text_field((string) $this->request->get_param('ip_allowlist')),
      'active'           => $active,
    ];
    // Omit secret when keeping the existing one so $wpdb->update leaves it intact.
    if (!$keepSecret) {
      $data['secret'] = $secret;
    }

    return $data;
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
