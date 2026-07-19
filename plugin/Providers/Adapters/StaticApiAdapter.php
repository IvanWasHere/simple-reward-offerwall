<?php

namespace SimpleRO\Providers\Adapters;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * StaticApiAdapter — offers pulled server-side and stored in ro_offers, deduped by
 * (provider_id, provider_offer_id). Offers not seen in a run are soft-inactivated.
 * The scheduled runner and admin manual trigger call ingest() (Phase 5).
 */
class StaticApiAdapter extends AbstractAdapter
{
  public function type(): string
  {
    return 'static_api';
  }

  public function ingest(object $provider): int
  {
    global $wpdb;

    $raw = $this->fetchRawOffers($provider, ['adslot_id' => $provider->adslot_id ?? '']);
    if (!$raw) {
      // A transient failure shouldn't wipe the catalog — do nothing.
      return 0;
    }

    $table = $wpdb->prefix . 'ro_offers';
    $now = gmdate('Y-m-d H:i:s');
    $seen = [];

    foreach ($raw as $rawOffer) {
      $n = $this->normalize($provider, $rawOffer);
      if ($n === null) {
        continue;
      }
      $providerOfferId = $n['providerOfferId'];
      if ($providerOfferId === '') {
        continue;
      }
      $seen[] = $providerOfferId;

      // Upsert on the UNIQUE(provider_id, provider_offer_id) key.
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table}
           (provider_id, provider_offer_id, name, tasks, total_payout, device, os, country, icons, link, raw_json, active, created_at, updated_at)
         VALUES (%d, %s, %s, %s, %f, %s, %s, %s, %s, %s, %s, 1, %s, %s)
         ON DUPLICATE KEY UPDATE
           name = VALUES(name), tasks = VALUES(tasks), total_payout = VALUES(total_payout),
           device = VALUES(device), os = VALUES(os), country = VALUES(country),
           icons = VALUES(icons), link = VALUES(link), raw_json = VALUES(raw_json),
           active = 1, updated_at = VALUES(updated_at)",
        (int) $provider->id,
        $providerOfferId,
        $n['name'],
        wp_json_encode($n['tasks']),
        (float) $n['totalPayout'],
        $n['device'],
        $n['os'],
        $n['country'],
        wp_json_encode($n['icons']),
        $n['link'],
        wp_json_encode($rawOffer),
        $now,
        $now
      ));
    }

    // Soft-inactivate offers from this provider that were not seen this run.
    if ($seen) {
      $placeholders = implode(',', array_fill(0, count($seen), '%s'));
      $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET active = 0, updated_at = %s
          WHERE provider_id = %d AND provider_offer_id NOT IN ({$placeholders})",
        array_merge([$now, (int) $provider->id], $seen)
      ));
    }

    return count($seen);
  }
}
