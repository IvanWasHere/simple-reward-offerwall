<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Providers\ProviderAdapterFactory;
use SimpleRewardOffer\Services\MacroBuilder;
use SimpleRewardOffer\Services\Settings;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

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
      "SELECT * FROM {$wpdb->prefix}simplerewardoffer_providers WHERE id = %d AND status = 'active' LIMIT 1",
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
      $wpdb->prefix . 'simplerewardoffer_clicked',
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
        "SELECT id, link FROM {$wpdb->prefix}simplerewardoffer_offers
          WHERE provider_id = %d AND provider_offer_id = %s AND active = 1 AND admin_disabled = 0 LIMIT 1",
        (int) $provider->id,
        $providerOfferId
      ));
      return $row ? [$this->personalize((string) $row->link, $user), (int) $row->id] : ['', 0];
    }

    if ($provider->type === 'offerwall_api') {
      $adapter = ProviderAdapterFactory::for($provider);
      foreach ($adapter->fetchOffers($provider, $user) as $offer) {
        if ((string) ($offer['providerOfferId'] ?? '') === $providerOfferId) {
          return [$this->personalize((string) ($offer['link'] ?? ''), $user), 0];
        }
      }
    }

    return ['', 0];
  }

  /**
   * Substitute the per-user macros a provider tracking link carries so the S2S
   * postback can identify this user. Ayet uses {external_identifier}; the
   * composite external id embeds the user's id + hash. Unknown tokens survive.
   */
  private function personalize(string $link, object $user): string
  {
    if ($link === '') {
      return '';
    }
    $hash = (string) ($user->unique_user_hash ?? '');
    return MacroBuilder::substitute($link, [
      'external_identifier' => Settings::buildExternalId((int) $user->id, $hash),
      'external_id'         => Settings::buildExternalId((int) $user->id, $hash),
      'user_id'             => (int) $user->id,
      // Lootably link macro; the SHA256 postback signature authenticates it.
      'userID'              => (int) $user->id,
      'user_hash'           => $hash,
    ]);
  }
}
