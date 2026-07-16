<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Providers\ProviderAdapterFactory;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * ClicksController (user) — records a click on an offer and returns the outbound
 * URL. The link is resolved server-side (stored offer row for static_api, or a
 * cached live fetch for offerwall_api) so the client can't tamper with it.
 */
class ClicksController extends RestController
{
  public function store()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $providerId = (int) $this->request->get_param('provider_id');
    $providerOfferId = trim((string) $this->request->get_param('provider_offer_id'));

    if ($providerId <= 0 || $providerOfferId === '') {
      return $this->responseError('ro_invalid', __('Provider and offer are required.', 'simple-reward-offerwall'), 422);
    }

    $provider = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}ro_providers WHERE id = %d AND status = 'active' LIMIT 1",
      $providerId
    ));
    if (!$provider) {
      return $this->responseError('ro_not_found', __('Provider not found.', 'simple-reward-offerwall'), 404);
    }

    [$link, $offerRowId] = $this->resolveLink($provider, $providerOfferId, $user);

    if ($link === '') {
      return $this->responseError('ro_not_found', __('Offer not available.', 'simple-reward-offerwall'), 404);
    }

    $wpdb->insert(
      $wpdb->prefix . 'ro_clicked',
      [
        'provider_id'       => $providerId,
        'offer_id'          => $offerRowId,
        'provider_offer_id' => $providerOfferId,
        'user_id'           => (int) $user->id,
        'session_nonce'     => bin2hex(random_bytes(16)),
        'target_url'        => $link,
        'created_at'        => gmdate('Y-m-d H:i:s'),
        'updated_at'        => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
    );

    return $this->response(['url' => esc_url_raw($link)]);
  }

  /**
   * @return array{0:string,1:int} [link, offerRowId]
   */
  private function resolveLink(object $provider, string $providerOfferId, object $user): array
  {
    global $wpdb;

    if ($provider->type === 'static_api') {
      $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, link FROM {$wpdb->prefix}ro_offers
          WHERE provider_id = %d AND provider_offer_id = %s AND active = 1 LIMIT 1",
        (int) $provider->id,
        $providerOfferId
      ));
      return $row ? [(string) $row->link, (int) $row->id] : ['', 0];
    }

    if ($provider->type === 'offerwall_api') {
      $adapter = ProviderAdapterFactory::for($provider);
      foreach ($adapter->fetchOffers($provider, $user) as $offer) {
        if ((string) ($offer['providerOfferId'] ?? '') === $providerOfferId) {
          return [(string) ($offer['link'] ?? ''), 0];
        }
      }
    }

    return ['', 0];
  }
}
