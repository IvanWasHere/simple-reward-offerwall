<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Providers\ProviderAdapterFactory;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

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
    $p = $wpdb->prefix . 'simplerewardoffer_providers';

    $rows = $wpdb->get_results(
      "SELECT id, name, type, unique_provider_hash, config
         FROM {$p}
        WHERE status = 'active' AND type IN ('iframe', 'offerwall_api')
        ORDER BY name ASC"
    );

    // wall_placement (admin setting, stored in provider config): where the
    // offerwall button surfaces on the user Earn page — 'hot', 'all', or 'none'
    // (hidden). Only 'iframe' providers open in an <iframe>; 'offerwall_api'
    // providers are JSON feeds and carry type so the client can tell them apart.
    $out = [];
    foreach ($rows ?: [] as $r) {
      $cfg = json_decode((string) $r->config, true);
      $placement = (is_array($cfg) && !empty($cfg['wall_placement'])) ? (string) $cfg['wall_placement'] : 'all';
      if ($placement === 'none') {
        continue;
      }
      $out[] = [
        'id'        => (int) $r->id,
        'name'      => $r->name,
        'type'      => $r->type,
        'hash'      => $r->unique_provider_hash,
        'placement' => in_array($placement, ['hot', 'all'], true) ? $placement : 'all',
      ];
    }

    return $this->response(['offerwalls' => $out]);
  }

  /**
   * Build the per-user URL for an iframe offerwall.
   *
   * Offerwall opens are NOT recorded as clicks — an offerwall leads to the
   * provider's own site (many offers), we don't offer support for it, and postback
   * correlation is by the user_id/user_hash/external_id URL macros, not a stored
   * click nonce. Only real offers (simplerewardoffer_offers, via ClicksController) are tracked.
   */
  public function url()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $id = (int) $this->request->get_param('id');

    $p = $wpdb->prefix . 'simplerewardoffer_providers';
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

    // A per-open nonce is still passed for the optional {session_id} macro, but is
    // no longer persisted (offerwall opens are untracked).
    $nonce = bin2hex(random_bytes(16));

    // Build the per-user iframe URL via the provider adapter (macro substitution).
    // The template is stored raw; after substitution (values urlencoded) it is a
    // valid URL, so escape it before handing it to the browser as an iframe src.
    $adapter = ProviderAdapterFactory::for($provider);
    $url = esc_url_raw($adapter->buildUserUrl($provider, $user, ['session_id' => $nonce]));

    return $this->response(['url' => $url]);
  }
}
