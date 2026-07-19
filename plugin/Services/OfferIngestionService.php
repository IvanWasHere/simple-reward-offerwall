<?php

namespace SimpleRewardOffer\Services;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\ProviderAdapterFactory;
use Throwable;

/**
 * OfferIngestionService — pulls offers for static_api providers into simplerewardoffer_offers.
 * Reused by the hourly schedule (IngestOffersSchedule) and the admin manual
 * trigger. Each provider is isolated so one failing feed doesn't abort the rest.
 */
class OfferIngestionService
{
  /**
   * Ingest all active static_api providers.
   *
   * @return array<int,int> provider_id => offers seen (or -1 on error)
   */
  public static function ingestAll(): array
  {
    global $wpdb;

    $providers = $wpdb->get_results(
      "SELECT * FROM {$wpdb->prefix}simplerewardoffer_providers WHERE status = 'active' AND type = 'static_api'"
    );

    $results = [];
    foreach ($providers ?: [] as $provider) {
      $results[(int) $provider->id] = self::safeIngest($provider);
    }

    return $results;
  }

  /** Ingest a single provider. Returns the number of offers seen, or -1 on error. */
  public static function safeIngest(object $provider): int
  {
    try {
      return ProviderAdapterFactory::for($provider)->ingest($provider);
    } catch (Throwable $e) {
      if (function_exists('logger')) {
        logger()->error('[simplerewardoffer] ingest failed', ['provider' => $provider->id, 'error' => $e->getMessage()]);
      }
      return -1;
    }
  }
}
