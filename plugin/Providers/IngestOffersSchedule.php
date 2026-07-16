<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\OfferIngestionService;
use SimpleRO\WPBones\Foundation\WordPressScheduleServiceProvider as ServiceProvider;

/**
 * IngestOffersSchedule — hourly WP-Cron job that refreshes static_api offers.
 * The event is (un)scheduled automatically on activation/deactivation.
 */
class IngestOffersSchedule extends ServiceProvider
{
  protected $hook = 'simple_ro_ingest_offers';

  protected $recurrence = 'hourly';

  public function run()
  {
    $results = OfferIngestionService::ingestAll();

    if (function_exists('logger')) {
      logger()->info('[simple-ro] scheduled offer ingest', $results);
    }
  }
}
