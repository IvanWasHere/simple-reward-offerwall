<?php

namespace SimpleRO\Providers\Adapters;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\MacroBuilder;

/**
 * IframeAdapter — a web offerwall opened in an <iframe>. Builds the per-user URL
 * by substituting macros; it has no server-side offer feed.
 */
class IframeAdapter extends AbstractAdapter
{
  public function type(): string
  {
    return 'iframe';
  }

  public function buildUserUrl(object $provider, object $user, array $context = []): string
  {
    $base = [
      'user_id'   => (int) $user->id,
      'user_hash' => $user->unique_user_hash ?? '',
      'adslot_id' => $provider->adslot_id ?? '',
    ];

    return MacroBuilder::build((string) $provider->url, $this->macros($provider), array_merge($base, $context));
  }
}
