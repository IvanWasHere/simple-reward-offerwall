<?php

namespace SimpleRewardOffer\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * GeoIp — resolve a request IP to a 2-letter ISO country code. Best-effort:
 * returns '' when it can't be determined (e.g. local/private IPs in dev).
 *
 * Resolution order:
 *   1. A geo-aware CDN/proxy header if the site sits behind one (Cloudflare
 *      `CF-IPCountry`, CloudFront `CloudFront-Viewer-Country`, or `X-Country`) —
 *      zero-cost and authoritative.
 *   2. A cached free lookup (ip-api.com) for public IPs. Cached per-IP for a day
 *      so it adds at most one short HTTP call per new IP and never blocks logins
 *      for long (2s timeout, failures resolve to '').
 *
 * To disable the external lookup, keep only step 1 (or point a filter at your own
 * MaxMind/GeoIP2 database).
 */
class GeoIp
{
  public static function country(string $ip): string
  {
    $ip = trim($ip);
    if ($ip === '') {
      return '';
    }

    // 1) Trust a geo-aware proxy/CDN header when present.
    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY'] as $header) {
      $code = strtoupper(substr((string) ($_SERVER[$header] ?? ''), 0, 2));
      if (preg_match('/^[A-Z]{2}$/', $code) && $code !== 'XX') {
        return $code;
      }
    }

    // 2) No geolocation for private/reserved IPs (local dev, LAN).
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
      return '';
    }

    $cacheKey = 'simplerewardoffer_geo_' . md5($ip);
    $cached = get_transient($cacheKey);
    if ($cached !== false) {
      return (string) $cached;
    }

    $country = '';
    $response = wp_remote_get(
      'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode',
      ['timeout' => 2]
    );
    if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
      $json = json_decode((string) wp_remote_retrieve_body($response), true);
      if (is_array($json) && ($json['status'] ?? '') === 'success') {
        $code = strtoupper((string) ($json['countryCode'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $code)) {
          $country = $code;
        }
      }
    }

    // Cache the resolved code (including '' when unknown) to avoid re-hitting the
    // API on every login from the same IP.
    set_transient($cacheKey, $country, DAY_IN_SECONDS);
    return $country;
  }
}
