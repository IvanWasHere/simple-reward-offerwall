<?php

namespace SimpleRewardOffer\Providers\Adapters;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Services\MacroBuilder;
use SimpleRewardOffer\Services\Settings;

/**
 * IframeAdapter — a web offerwall opened in an <iframe>. Builds the per-user URL
 * by substituting macros; it has no server-side offer feed.
 *
 * Admins write the provider URL with inline macro tokens that are replaced (URL-
 * encoded) per user when the wall opens:
 *   {user_id}     → the user's numeric id
 *   {user_hash}   → the user's opaque 32-char hash (recommended for postbacks —
 *                   not enumerable like the numeric id)
 *   {session_id}  → the per-open click nonce (correlates a postback to this open)
 *   {adslot_id}   → the provider's configured ad-slot id
 *   {external_id} → the composite id "<prefix>-<user_id>-<user_hash>", where the
 *                   prefix is an admin-defined site-level setting (Settings /
 *                   PUT /admin/settings). This is the recommended identifier to
 *                   hand to providers.
 * e.g.  https://wall.lootably.com/?placementID=ckhi…&sid={user_id}
 *       → https://wall.lootably.com/?placementID=ckhi…&sid=123
 *
 * The legacy {macro_*} + macros-map mechanism still works and is applied after
 * the canonical tokens, so existing providers are unaffected.
 */
class IframeAdapter extends AbstractAdapter
{
  public function type(): string
  {
    return 'iframe';
  }

  public function buildUserUrl(object $provider, object $user, array $context = []): string
  {
    $values = array_merge([
      'user_id'     => (int) $user->id,
      'user_hash'   => $user->unique_user_hash ?? '',
      'adslot_id'   => $provider->adslot_id ?? '',
      'session_id'  => '',
      'external_id' => Settings::buildExternalId((int) $user->id, (string) ($user->unique_user_hash ?? '')),
    ], $context);

    // Inline canonical tokens: {user_id}, {user_hash}, {session_id}, {adslot_id}.
    $url = MacroBuilder::substitute((string) $provider->url, $values);

    // Legacy {macro_*} → source-key map (optional, back-compat).
    return MacroBuilder::build($url, $this->macros($provider), $values);
  }
}
