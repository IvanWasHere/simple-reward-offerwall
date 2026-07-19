<?php

namespace SimpleRO\Providers\Schemas\Contracts;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * OfferSchema — a named, provider-specific mapping strategy.
 *
 * A provider row references a schema by key (ro_providers.offer_schema). The
 * schema encapsulates everything provider-specific about a network:
 *  - how its offers-API response is fetched (HTTP method) and where the offers
 *    array lives (offers_path), and how one raw offer maps to our ro_offers shape;
 *  - the set of postback macros it can send + a ready-to-paste postback URL
 *    template + the default incoming→canonical param map for its callbacks;
 *  - which callback events create a reward vs. are audit-only (rewardRule).
 *
 * Schemas are code (a built-in registry) rather than admin JSON because the real
 * shapes (nested tasks[], i18n, currency_amount vs payout_usd, tracking_link
 * macros) can't be expressed as a flat field map.
 */
interface OfferSchema
{
  /** Stable registry key stored on the provider, e.g. 'ayetstudios'. */
  public function key(): string;

  /** Human label for the admin dropdown, e.g. 'AyetStudios'. */
  public function label(): string;

  /** HTTP method for the offers request: 'GET' | 'POST'. */
  public function httpMethod(): string;

  /**
   * The JSON body to POST for the offers request (empty = none / GET feed). Lets
   * a schema send credentials in the body instead of the URL.
   *
   * @param object $provider ro_providers row
   * @return array<string,mixed>
   */
  public function requestBody(object $provider): array;

  /** Dot-path to the offers array inside the API JSON, e.g. 'offers'. */
  public function offersPath(): string;

  /**
   * Map one raw provider offer to our normalized shape (the caller injects
   * providerId). Return null to skip the offer.
   *
   * Shape: [ providerOfferId, name, tasks, totalPayout, device, os, country,
   *          icons(array), link ].
   *
   * @param array<string,mixed> $raw
   * @return array<string,mixed>|null
   */
  public function mapOffer(array $raw): ?array;

  /**
   * The postback macros this network can send, for the admin callback UI.
   *
   * @return array<int,array{token:string,label:string,description:string}>
   */
  public function callbackMacros(): array;

  /**
   * Every callback macro this schema supports, driving the admin callback form:
   * one row per value, each with the default request key + the postback macro
   * token to place in the provider's URL. `mapped` marks the values the
   * CallbackController acts on (vs. informational ones only kept in the raw
   * payload). This is the source of truth for defaultParamMap() and
   * postbackTemplate(), so every macro is available in the form and included in
   * the generated callback URL.
   *
   * @return array<int,array{field:string,key:string,macro:string,label:string,description:string,required:bool,mapped:bool}>
   */
  public function callbackFields(): array;

  /** A ready-to-paste postback URL query template (leading '?...'), macros inline. */
  public function postbackTemplate(): string;

  /**
   * Default incoming→canonical param map to pre-fill a new callback.
   * { our_canonical_field: incoming_request_key }.
   *
   * @return array<string,string>
   */
  public function defaultParamMap(): array;

  /** Default signature request key for a new callback ('' = unsigned). */
  public function defaultSignatureParam(): string;

  /** Default signature algorithm for a new callback (SignatureVerifier algos). */
  public function defaultSignatureAlgo(): string;

  /** Default signature source (canonical-string builder) for a new callback. */
  public function defaultSignatureSource(): string;

  /**
   * Whether callbacks may run with signature_algo='none' while active. True only
   * when the schema self-authenticates the caller another way (e.g. ayet's
   * verified external_identifier); false forces a real signature.
   */
  public function allowsUnsignedCallbacks(): bool;

  /**
   * Decide what a mapped callback event does.
   *
   * @param array<string,mixed> $mapped canonical fields resolved via param_map
   * @return array{type:string,createsReward:bool,sign:int} sign is +1 or -1
   */
  public function rewardRule(array $mapped): array;
}
