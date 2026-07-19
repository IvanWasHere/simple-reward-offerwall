<?php

namespace SimpleRewardOffer\Providers\Adapters;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * OfferwallApiAdapter — calls the network's publisher offers API server-side with
 * the ad slot + our user id, normalizes the JSON to our shape, and caches the
 * result briefly per (provider, user) to avoid hammering the network.
 */
class OfferwallApiAdapter extends AbstractAdapter
{
  private const CACHE_TTL = 60; // seconds

  public function type(): string
  {
    return 'offerwall_api';
  }

  public function fetchOffers(object $provider, object $user): array
  {
    $cacheKey = 'simplerewardoffer_offers_' . (int) $provider->id . '_' . (int) $user->id;
    $cached = get_transient($cacheKey);
    if (is_array($cached)) {
      return $cached;
    }

    $context = [
      'user_id'   => (int) $user->id,
      'user_hash' => $user->unique_user_hash ?? '',
      'adslot_id' => $provider->adslot_id ?? '',
    ];

    $raw = $this->fetchRawOffers($provider, $context);
    $offers = [];
    foreach ($raw as $o) {
      $n = $this->normalize($provider, $o);
      if ($n !== null) {
        $offers[] = $n + ['source' => 'api'];
      }
    }

    set_transient($cacheKey, $offers, self::CACHE_TTL);

    return $offers;
  }
}
