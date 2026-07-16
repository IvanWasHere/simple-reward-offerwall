<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Providers\ProviderAdapterFactory;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * OfferwallsController — the offerwalls a signed-in user can open.
 * Phase 2 handles the 'iframe' type (a provider page opened in an <iframe> with
 * the user's identifier substituted via macros).
 */
class OfferwallsController extends RestController
{
  public function index()
  {
    global $wpdb;
    $p = $wpdb->prefix . 'ro_providers';

    $rows = $wpdb->get_results(
      "SELECT id, name, type, unique_provider_hash
         FROM {$p}
        WHERE status = 'active' AND type IN ('iframe', 'offerwall_api')
        ORDER BY name ASC"
    );

    return $this->response(['offerwalls' => $rows]);
  }

  /**
   * Build the per-user URL for an iframe offerwall and record the click so an
   * incoming postback can be correlated (session_id macro).
   */
  public function url()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $id = (int) $this->request->get_param('id');

    $p = $wpdb->prefix . 'ro_providers';
    $provider = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$p} WHERE id = %d AND status = 'active' LIMIT 1",
      $id
    ));

    if (!$provider) {
      return $this->responseError('ro_not_found', __('Offerwall not found.', 'simple-reward-offerwall'), 404);
    }
    if ($provider->type !== 'iframe') {
      return $this->responseError('ro_unsupported', __('This offerwall type is not opened by URL.', 'simple-reward-offerwall'), 400);
    }

    $nonce = bin2hex(random_bytes(16));
    $wpdb->insert(
      $wpdb->prefix . 'ro_clicked',
      [
        'provider_id'   => (int) $provider->id,
        'offer_id'      => 0,
        'user_id'       => (int) $user->id,
        'session_nonce' => $nonce,
        'created_at'    => gmdate('Y-m-d H:i:s'),
        'updated_at'    => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%d', '%d', '%s', '%s', '%s']
    );

    // Build the per-user iframe URL via the provider adapter (macro substitution).
    // The template is stored raw; after substitution (values urlencoded) it is a
    // valid URL, so escape it before handing it to the browser as an iframe src.
    $adapter = ProviderAdapterFactory::for($provider);
    $url = esc_url_raw($adapter->buildUserUrl($provider, $user, ['session_id' => $nonce]));

    return $this->response(['url' => $url]);
  }
}
