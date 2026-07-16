<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Providers\ProviderAdapterFactory;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * OffersController (user) — the normalized offers feed rendered with our design.
 * Merges stored static_api offers (ro_offers) with live offerwall_api offers
 * (fetched + cached per request via the adapters).
 */
class OffersController extends RestController
{
  public function index()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $offers = array_merge($this->staticOffers(), $this->liveOffers($user));

    return $this->response(['offers' => $offers]);
  }

  private function staticOffers(): array
  {
    global $wpdb;
    $o = $wpdb->prefix . 'ro_offers';
    $p = $wpdb->prefix . 'ro_providers';

    $rows = $wpdb->get_results(
      "SELECT o.*, p.name AS provider_name
         FROM {$o} o
         INNER JOIN {$p} p ON p.id = o.provider_id
        WHERE o.active = 1 AND p.status = 'active'
        ORDER BY o.total_payout DESC
        LIMIT 200"
    );

    return array_map(function ($r) {
      return [
        'providerId'      => (int) $r->provider_id,
        'providerName'    => $r->provider_name,
        'providerOfferId' => $r->provider_offer_id,
        'name'            => $r->name,
        'tasks'           => json_decode((string) $r->tasks, true),
        'totalPayout'     => (float) $r->total_payout,
        'device'          => $r->device,
        'os'              => $r->os,
        'country'         => $r->country,
        'icons'           => json_decode((string) $r->icons, true) ?: [],
        'source'          => 'static',
      ];
    }, $rows ?: []);
  }

  private function liveOffers(object $user): array
  {
    global $wpdb;
    $p = $wpdb->prefix . 'ro_providers';

    $providers = $wpdb->get_results(
      "SELECT * FROM {$p} WHERE status = 'active' AND type = 'offerwall_api'"
    );

    $out = [];
    foreach ($providers ?: [] as $provider) {
      $adapter = ProviderAdapterFactory::for($provider);
      foreach ($adapter->fetchOffers($provider, $user) as $offer) {
        // Do not expose the raw outbound link in the list; /clicks resolves it.
        unset($offer['link']);
        $offer['providerName'] = $provider->name;
        $out[] = $offer;
      }
    }

    return $out;
  }
}
