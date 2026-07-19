<?php

namespace SimpleRO\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Schemas\OfferSchemaRegistry;
use SimpleRO\Services\OfferIngestionService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * ProvidersController (admin) — CRUD for offerwall providers.
 * All routes guarded by Guard::role('admin').
 */
class ProvidersController extends RestController
{
  private const TYPES = ['iframe', 'offerwall_api', 'static_api'];

  /**
   * POST /admin/providers/{id}/ingest — manually pull offers for a static_api
   * provider now (same path the hourly schedule uses).
   */
  public function ingest()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');

    $provider = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}ro_providers WHERE id = %d LIMIT 1",
      $id
    ));
    if (!$provider) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }
    if ($provider->type !== 'static_api') {
      return $this->responseError('ro_unsupported', __('Only static_api providers ingest offers.', 'simple-reward-offerwall'), 400);
    }

    $count = OfferIngestionService::safeIngest($provider);

    if ($count < 0) {
      return $this->responseError('ro_ingest_failed', __('Ingestion failed — check the provider configuration.', 'simple-reward-offerwall'), 502);
    }

    return $this->response(['ingested' => $count]);
  }

  public function index()
  {
    global $wpdb;
    $p = $wpdb->prefix . 'ro_providers';
    $cb = $wpdb->prefix . 'ro_provider_callbacks';

    $rows = $wpdb->get_results(
      "SELECT p.*, (SELECT COUNT(*) FROM {$cb} c WHERE c.provider_id = p.id) AS callback_count
         FROM {$p} p
        ORDER BY p.id DESC"
    );

    return $this->response(['providers' => array_map([$this, 'present'], $rows ?: [])]);
  }

  public function show()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $p = $wpdb->prefix . 'ro_providers';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p} WHERE id = %d LIMIT 1", $id));

    if (!$row) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }

    return $this->response(['provider' => $this->present($row)]);
  }

  public function store()
  {
    global $wpdb;

    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $now = gmdate('Y-m-d H:i:s');
    // All columns bind safely as strings (%s); coin_rate is a DECIMAL and accepts
    // a numeric string. Letting $wpdb default to %s avoids format/column ordering bugs.
    $wpdb->insert(
      $wpdb->prefix . 'ro_providers',
      $data + ['unique_provider_hash' => bin2hex(random_bytes(16)), 'created_at' => $now, 'updated_at' => $now]
    );

    return $this->response(['provider' => $this->find((int) $wpdb->insert_id)], 201);
  }

  public function update()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $p = $wpdb->prefix . 'ro_providers';

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p} WHERE id = %d", $id))) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }

    $data = $this->validated();
    if ($data instanceof \WP_Error) {
      return $data;
    }

    $data['updated_at'] = gmdate('Y-m-d H:i:s');
    $wpdb->update($p, $data, ['id' => $id], null, ['%d']);

    return $this->response(['provider' => $this->find($id)]);
  }

  public function destroy()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');

    $wpdb->delete($wpdb->prefix . 'ro_provider_callbacks', ['provider_id' => $id], ['%d']);
    $deleted = $wpdb->delete($wpdb->prefix . 'ro_providers', ['id' => $id], ['%d']);

    if (!$deleted) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }

    return $this->response(['deleted' => true]);
  }

  /* ---------------------------------------------------------------- */

  /**
   * Validate + normalize the request body into a column=>value array.
   *
   * @return array|\WP_Error
   */
  private function validated()
  {
    $name = sanitize_text_field((string) $this->request->get_param('name'));
    $type = (string) $this->request->get_param('type');

    if ($name === '') {
      return $this->responseError('ro_invalid', __('Name is required.', 'simple-reward-offerwall'), 422);
    }
    if (!in_array($type, self::TYPES, true)) {
      return $this->responseError('ro_invalid', __('Invalid provider type.', 'simple-reward-offerwall'), 422);
    }

    $status = (string) $this->request->get_param('status');
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

    // Optional built-in offer schema; '' = legacy config.field_map mapping.
    $offerSchema = (string) $this->request->get_param('offer_schema');
    if ($offerSchema !== '' && !OfferSchemaRegistry::exists($offerSchema)) {
      return $this->responseError('ro_invalid', __('Unknown offer schema.', 'simple-reward-offerwall'), 422);
    }

    // The URL is a TEMPLATE containing {macro_*} tokens, so it is not a valid URL
    // yet — esc_url_raw() would strip the braces. Store it raw (tags removed), only
    // validating the scheme; the fully-substituted URL is escaped at output time.
    $url = trim(strip_tags((string) $this->request->get_param('url')));
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
      return $this->responseError('ro_invalid', __('URL must start with http:// or https://', 'simple-reward-offerwall'), 422);
    }
    if ($type === 'iframe' && $url === '') {
      return $this->responseError('ro_invalid', __('An iframe provider needs a URL.', 'simple-reward-offerwall'), 422);
    }

    // wall_placement is a first-class admin setting persisted inside config:
    // 'hot' (Hot Walls + All Walls), 'all' (All Walls only), 'none' (hidden).
    $config = $this->asObject($this->request->get_param('config'));
    $placement = (string) $this->request->get_param('wall_placement');
    if (in_array($placement, ['hot', 'all', 'none'], true)) {
      $config['wall_placement'] = $placement;
    } elseif (!isset($config['wall_placement'])) {
      $config['wall_placement'] = 'all';
    }

    return [
      'name'       => $name,
      'type'       => $type,
      'url'        => $url,
      'macros'     => wp_json_encode($this->asObject($this->request->get_param('macros'))),
      'adslot_id'  => sanitize_text_field((string) $this->request->get_param('adslot_id')),
      'api_key'    => sanitize_text_field((string) $this->request->get_param('api_key')),
      'api_secret' => sanitize_text_field((string) $this->request->get_param('api_secret')),
      'coin_rate'  => max(0, (float) $this->request->get_param('coin_rate')),
      'offer_schema' => $offerSchema,
      'config'     => wp_json_encode($config),
      'status'     => $status,
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
    $p = $wpdb->prefix . 'ro_providers';
    return $this->present($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p} WHERE id = %d", $id)));
  }

  /** Shape a provider row for output; never leak the api secret. */
  private function present($row): array
  {
    if (!$row) {
      return [];
    }
    $config = json_decode((string) $row->config, true);
    return [
      'id'             => (int) $row->id,
      'hash'           => $row->unique_provider_hash,
      'name'           => $row->name,
      'type'           => $row->type,
      'url'            => $row->url,
      'macros'         => json_decode((string) $row->macros, true) ?: (object) [],
      'adslotId'       => $row->adslot_id,
      'apiKey'         => $row->api_key,
      'hasApiSecret'   => $row->api_secret !== '',
      'coinRate'       => (float) $row->coin_rate,
      'offerSchema'    => $row->offer_schema ?? '',
      'config'         => is_array($config) ? $config : (object) [],
      'wallPlacement'  => (is_array($config) && !empty($config['wall_placement'])) ? $config['wall_placement'] : 'all',
      'status'         => $row->status,
      'callbackCount'  => isset($row->callback_count) ? (int) $row->callback_count : null,
      'createdAt'      => $row->created_at,
    ];
  }
}
