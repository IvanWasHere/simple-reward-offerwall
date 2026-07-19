<?php

namespace SimpleRewardOffer\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Services\OfferIngestionService;
use SimpleRewardOffer\WPBones\Foundation\WordPressScheduleServiceProvider as ServiceProvider;

/**
 * IngestOffersSchedule — hourly WP-Cron job that refreshes static_api offers.
 * The event is (un)scheduled automatically on activation/deactivation.
 */
class IngestOffersSchedule extends ServiceProvider
{
  protected $hook = 'simplerewardoffer_ingest_offers';

  protected $recurrence = 'hourly';

  public function run()
  {
    $results = OfferIngestionService::ingestAll();

    if (function_exists('logger')) {
      logger()->info('[simplerewardoffer] scheduled offer ingest', $results);
    }
  }
}
