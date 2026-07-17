<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\WPBones\Routing\API\RestController;

/**
 * SurveysController — offers from providers flagged as survey walls
 * (provider.config JSON has {"survey": true}). Returns the same normalized offer
 * shape as OffersController so the SPA reuses its offer normalizer. A dedicated
 * survey-provider adapter (ETA, question count) is a later enhancement.
 */
class SurveysController extends RestController
{
  public function index()
  {
    global $wpdb;

    $o = $wpdb->prefix . 'ro_offers';
    $p = $wpdb->prefix . 'ro_providers';

    $providers = $wpdb->get_results("SELECT id, name, coin_rate, config FROM {$p} WHERE status = 'active'");

    $surveyIds = [];
    $meta = [];
    foreach ($providers ?: [] as $prov) {
      $cfg = json_decode((string) $prov->config, true);
      if (is_array($cfg) && !empty($cfg['survey'])) {
        $surveyIds[] = (int) $prov->id;
        $meta[(int) $prov->id] = $prov;
      }
    }

    if (empty($surveyIds)) {
      return $this->response(['surveys' => []]);
    }

    $placeholders = implode(',', array_fill(0, count($surveyIds), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$o}
        WHERE active = 1 AND admin_disabled = 0 AND provider_id IN ({$placeholders})
        ORDER BY total_payout DESC
        LIMIT 200",
      ...$surveyIds
    ));

    $surveys = array_map(function ($r) use ($meta) {
      $prov = $meta[(int) $r->provider_id] ?? null;
      $rate = $prov ? (float) $prov->coin_rate : 0.0;
      return [
        'providerId'      => (int) $r->provider_id,
        'providerName'    => $prov ? $prov->name : '',
        'providerOfferId' => $r->provider_offer_id,
        'name'            => $r->name,
        'tasks'           => json_decode((string) $r->tasks, true),
        'totalPayout'     => (float) $r->total_payout,
        'coins'           => (int) round((float) $r->total_payout * $rate),
        'device'          => $r->device,
        'os'              => $r->os,
        'country'         => $r->country,
        'icons'           => json_decode((string) $r->icons, true) ?: [],
        'source'          => 'survey',
      ];
    }, $rows ?: []);

    return $this->response(['surveys' => $surveys]);
  }
}
