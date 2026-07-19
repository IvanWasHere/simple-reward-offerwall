<?php

namespace SimpleRewardOffer\Providers;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Adapters\IframeAdapter;
use SimpleRewardOffer\Providers\Adapters\OfferwallApiAdapter;
use SimpleRewardOffer\Providers\Adapters\StaticApiAdapter;
use SimpleRewardOffer\Providers\Contracts\ProviderAdapter;

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
