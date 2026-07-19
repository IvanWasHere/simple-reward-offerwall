<?php

namespace SimpleRewardOffer\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * OffersController (admin) — view ingested offers and enable/disable them.
 * Guarded by Guard::role('admin').
 */
class OffersController extends RestController
{
  public function index()
  {
    global $wpdb;
    $o = $wpdb->prefix . 'simplerewardoffer_offers';
    $p = $wpdb->prefix . 'simplerewardoffer_providers';

    $providerId = (int) $this->request->get_param('provider_id');

    $where = '';
    $args = [];
    if ($providerId > 0) {
      $where = 'WHERE o.provider_id = %d';
      $args[] = $providerId;
    }

    $sql = "SELECT o.id, o.provider_id, o.provider_offer_id, o.name, o.total_payout,
                   o.device, o.os, o.country, o.active, o.admin_disabled, o.updated_at, p.name AS provider_name
              FROM {$o} o
              INNER JOIN {$p} p ON p.id = o.provider_id
              {$where}
             ORDER BY o.id DESC
             LIMIT 500";

    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

    $offers = array_map(function ($r) {
      return [
        'id'              => (int) $r->id,
        'providerId'      => (int) $r->provider_id,
        'providerName'    => $r->provider_name,
        'providerOfferId' => $r->provider_offer_id,
        'name'            => $r->name,
        'totalPayout'     => (float) $r->total_payout,
        'device'          => $r->device,
        'os'              => $r->os,
        'country'         => $r->country,
        // 'available' = seen in the last ingest; 'enabled' = not hidden by an admin.
        'available'       => (int) $r->active === 1,
        'enabled'         => (int) $r->admin_disabled === 0,
        'updatedAt'       => $r->updated_at,
      ];
    }, $rows ?: []);

    return $this->response(['offers' => $offers]);
  }

  public function update()
  {
    global $wpdb;
    $id = (int) $this->request->get_param('id');
    $t = $wpdb->prefix . 'simplerewardoffer_offers';

    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE id = %d", $id))) {
      return $this->responseError('ro_not_found', __('Offer not found.', 'simple-reward-offerwall'), 404);
    }

    // Admins toggle visibility via `enabled`; ingestion owns `active`, so an admin
    // hide (admin_disabled) survives the next ingest.
    $enabled = $this->request->get_param('enabled');
    if ($enabled === null) {
      return $this->responseError('ro_invalid', __('Nothing to update.', 'simple-reward-offerwall'), 422);
    }

    $wpdb->update(
      $t,
      ['admin_disabled' => (bool) $enabled ? 0 : 1, 'updated_at' => gmdate('Y-m-d H:i:s')],
      ['id' => $id],
      ['%d', '%s'],
      ['%d']
    );

    return $this->response(['offer' => ['id' => $id, 'enabled' => (bool) $enabled]]);
  }
}
