<?php

namespace SimpleRO\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Adapters\IframeAdapter;
use SimpleRO\Providers\Adapters\OfferwallApiAdapter;
use SimpleRO\Providers\Adapters\StaticApiAdapter;
use SimpleRO\Providers\Contracts\ProviderAdapter;

/**
 * Resolves the ProviderAdapter for a provider row (by its type).
 */
class ProviderAdapterFactory
{
  public static function for(object $provider): ProviderAdapter
  {
    switch ($provider->type) {
      case 'offerwall_api':
        return new OfferwallApiAdapter();
      case 'static_api':
        return new StaticApiAdapter();
      case 'iframe':
      default:
        return new IframeAdapter();
    }
  }
}
