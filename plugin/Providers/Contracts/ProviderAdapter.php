<?php

namespace SimpleRO\Providers\Contracts;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * ProviderAdapter — the strategy each provider type implements.
 *
 * Adding a new network is usually a new ro_providers row reusing an existing
 * adapter; a genuinely new integration shape is a new adapter class registered in
 * ProviderAdapterFactory.
 *
 * A "normalized offer" is an associative array in our own shape:
 *   [ providerId, providerOfferId, name, tasks, totalPayout, device, os,
 *     country, icons(array), link, source('api'|'static') ]
 */
interface ProviderAdapter
{
  /** The provider type this adapter handles: iframe|offerwall_api|static_api. */
  public function type(): string;

  /**
   * Build the outbound URL for a user (iframe src or an offer click target),
   * substituting macros. Returns '' if the adapter does not build URLs.
   *
   * @param object $provider ro_providers row
   * @param object $user     ro_users row (id, unique_user_hash, ...)
   * @param array  $context  extra macro sources (e.g. session_id, provider_offer_id)
   */
  public function buildUserUrl(object $provider, object $user, array $context = []): string;

  /**
   * Fetch live offers for a user (offerwall_api). Returns normalized offers.
   *
   * @return array<int,array<string,mixed>>
   */
  public function fetchOffers(object $provider, object $user): array;

  /**
   * Pull offers and upsert them into ro_offers (static_api). Returns the count
   * of offers seen this run.
   */
  public function ingest(object $provider): int;

  /**
   * Map one raw provider offer to our normalized shape.
   *
   * @param array<string,mixed> $raw
   * @return array<string,mixed>
   */
  public function normalizeOffer(array $raw, object $provider): array;
}
