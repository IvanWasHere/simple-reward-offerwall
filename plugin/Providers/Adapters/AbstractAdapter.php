<?php

namespace SimpleRO\Providers\Adapters;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Contracts\ProviderAdapter;
use SimpleRO\Providers\Schemas\Contracts\OfferSchema;
use SimpleRO\Providers\Schemas\OfferSchemaRegistry;

/**
 * AbstractAdapter — shared config parsing, HTTP fetch and offer normalization.
 *
 * When a provider names a built-in schema (ro_providers.offer_schema), that schema
 * drives the HTTP method, offers_path and per-offer mapping. Otherwise we fall back
 * to the legacy `config` JSON:
 *   {
 *     "offers_path": "offers",          // dot-path to the offers array in the JSON
 *     "field_map": { our_field: source_key, ... },
 *     "auth": { "type": "query|header", "param": "api_key" }
 *   }
 */
abstract class AbstractAdapter implements ProviderAdapter
{
  protected const DEFAULT_FIELD_MAP = [
    'provider_offer_id' => 'offer_id',
    'name'              => 'name',
    'tasks'             => 'tasks',
    'total_payout'      => 'payout',
    'device'            => 'device',
    'os'               => 'os',
    'country'          => 'countries',
    'icons'            => 'icon',
    'link'             => 'link',
  ];

  public function buildUserUrl(object $provider, object $user, array $context = []): string
  {
    return '';
  }

  public function fetchOffers(object $provider, object $user): array
  {
    return [];
  }

  public function ingest(object $provider): int
  {
    return 0;
  }

  public function normalizeOffer(array $raw, object $provider): array
  {
    $map = $this->fieldMap($provider);

    $get = static function (string $key) use ($raw) {
      return $raw[$key] ?? null;
    };

    $icons = $get($map['icons']);
    if (!is_array($icons)) {
      $icons = $icons ? ['small' => $icons] : [];
    }

    return [
      'providerId'      => (int) $provider->id,
      'providerOfferId' => (string) $get($map['provider_offer_id']),
      'name'            => (string) $get($map['name']),
      'tasks'           => $get($map['tasks']),
      'totalPayout'     => (float) $get($map['total_payout']),
      'device'          => (string) ($get($map['device']) ?? ''),
      'os'              => (string) ($get($map['os']) ?? ''),
      'country'         => is_array($get($map['country'])) ? implode(',', $get($map['country'])) : (string) ($get($map['country']) ?? ''),
      'icons'           => $icons,
      'link'            => (string) ($get($map['link']) ?? ''),
    ];
  }

  /* ---------------------------------------------------------------- */

  /** The provider's built-in offer schema, or null (legacy field_map path). */
  protected function schema(object $provider): ?OfferSchema
  {
    return OfferSchemaRegistry::for($provider->offer_schema ?? '');
  }

  /**
   * Normalize one raw offer via the provider's schema when set, else the legacy
   * field_map. Injects providerId. Returns null to skip the offer.
   *
   * @param array<string,mixed> $raw
   * @return array<string,mixed>|null
   */
  protected function normalize(object $provider, array $raw): ?array
  {
    $schema = $this->schema($provider);
    if ($schema) {
      $n = $schema->mapOffer($raw);
      if ($n === null) {
        return null;
      }
      $n['providerId'] = (int) $provider->id;
      return $n;
    }
    return $this->normalizeOffer($raw, $provider);
  }

  protected function config(object $provider): array
  {
    $cfg = json_decode((string) ($provider->config ?? ''), true);
    return is_array($cfg) ? $cfg : [];
  }

  protected function macros(object $provider): array
  {
    $m = json_decode((string) ($provider->macros ?? ''), true);
    return is_array($m) ? $m : [];
  }

  protected function fieldMap(object $provider): array
  {
    $cfg = $this->config($provider);
    $map = isset($cfg['field_map']) && is_array($cfg['field_map']) ? $cfg['field_map'] : [];
    return array_merge(self::DEFAULT_FIELD_MAP, $map);
  }

  /**
   * Perform the JSON HTTP request for a provider and return the raw offers array
   * (before normalization). Returns [] on any failure.
   *
   * @param array<string,mixed> $context macro sources (user_id, adslot_id, ...)
   * @return array<int,array<string,mixed>>
   */
  protected function fetchRawOffers(object $provider, array $context): array
  {
    $cfg = $this->config($provider);
    $url = \SimpleRO\Services\MacroBuilder::build((string) $provider->url, $this->macros($provider), $context);

    $headers = ['Accept' => 'application/json'];
    $auth = $cfg['auth'] ?? [];
    if (($auth['type'] ?? '') === 'header' && ($auth['param'] ?? '') !== '' && $provider->api_key !== '') {
      $headers[$auth['param']] = $provider->api_key;
    } elseif (($auth['type'] ?? '') === 'query' && ($auth['param'] ?? '') !== '' && $provider->api_key !== '') {
      $url = add_query_arg([$auth['param'] => $provider->api_key], $url);
    }

    // SSL verification stays ON by default; a provider may opt out via
    // config.sslverify=false (e.g. a self-hosted feed with a private cert).
    $sslVerify = !array_key_exists('sslverify', $cfg) || (bool) $cfg['sslverify'];

    $schema = $this->schema($provider);
    $args = [
      'timeout'   => 10,
      'headers'   => $headers,
      'sslverify' => $sslVerify,
    ];
    if ($schema && strtoupper($schema->httpMethod()) === 'POST') {
      $body = $schema->requestBody($provider);
      if ($body) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = wp_json_encode($body);
      }
      $response = wp_remote_post($url, $args);
    } else {
      $response = wp_remote_get($url, $args);
    }
    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
      if (function_exists('logger')) {
        logger()->error('[simple-ro] provider fetch failed', ['provider' => $provider->id]);
      }
      return [];
    }

    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($json)) {
      return [];
    }

    $path = $schema ? $schema->offersPath() : ($cfg['offers_path'] ?? 'offers');
    $offers = $this->dataGet($json, (string) $path);
    if (!is_array($offers)) {
      // Maybe the response itself is the list.
      $offers = array_is_list($json) ? $json : [];
    }

    return array_values(array_filter($offers, 'is_array'));
  }

  /** Read a value from a nested array by "a.b.c" dot path. */
  protected function dataGet(array $array, string $path)
  {
    if ($path === '') {
      return $array;
    }
    $value = $array;
    foreach (explode('.', $path) as $segment) {
      if (is_array($value) && array_key_exists($segment, $value)) {
        $value = $value[$segment];
      } else {
        return null;
      }
    }
    return $value;
  }
}
