<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\LedgerService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * AccountController — the signed-in user's coins, ledger and rewards.
 * All routes are guarded by Guard::role('user'); the session is already resolved.
 */
class AccountController extends RestController
{
  public function balance()
  {
    $user = Guard::user($this->request);
    return $this->response(['balance' => LedgerService::balance((int) $user->id)]);
  }

  public function rewards()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $r = $wpdb->prefix . 'ro_rewards';
    $c = $wpdb->prefix . 'ro_callbacks';
    $p = $wpdb->prefix . 'ro_providers';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT rw.id, rw.coins_value, rw.status, rw.created_at,
              cb.amount, cb.currency, cb.transaction_id, p.name AS provider_name
         FROM {$r} rw
         LEFT JOIN {$c} cb ON cb.id = rw.callback_id
         LEFT JOIN {$p} p ON p.id = cb.provider_id
        WHERE rw.user_id = %d
        ORDER BY rw.id DESC
        LIMIT 200",
      (int) $user->id
    ));

    return $this->response(['rewards' => $rows]);
  }

  public function ledger()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $t = $wpdb->prefix . 'ro_coin_ledger';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT id, delta, reason, ref_type, ref_id, created_at
         FROM {$t}
        WHERE user_id = %d
        ORDER BY id DESC
        LIMIT 200",
      (int) $user->id
    ));

    return $this->response([
      'balance' => LedgerService::balance((int) $user->id),
      'entries' => $rows,
    ]);
  }

  /**
   * Offers the user clicked in the last N days (default 30), deduped to the most
   * recent click per offer — used to attach context to a support ticket.
   *
   * ONLY real offers from `ro_offers` are returned (INNER JOIN forces offer_id>0).
   * Offerwall opens (offer_id=0, provider's own site) and survey providers are
   * excluded — we don't offer support for those.
   */
  public function clicks()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $days = (int) $this->request->get_param('days');
    $days = ($days >= 1 && $days <= 90) ? $days : 30;
    $since = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

    $c = $wpdb->prefix . 'ro_clicked';
    $o = $wpdb->prefix . 'ro_offers';
    $p = $wpdb->prefix . 'ro_providers';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT cl.id, cl.provider_id, cl.offer_id, cl.provider_offer_id, cl.created_at,
              o.name AS offer_name, p.name AS provider_name, p.config AS provider_config
         FROM {$c} cl
         INNER JOIN {$o} o ON o.id = cl.offer_id
         INNER JOIN {$p} p ON p.id = cl.provider_id
        WHERE cl.user_id = %d AND cl.created_at > %s AND cl.offer_id > 0
        ORDER BY cl.created_at DESC
        LIMIT 300",
      (int) $user->id,
      $since
    ));

    // Dedupe to the latest click per offer (rows are newest-first); skip surveys.
    $seen = [];
    $clicks = [];
    foreach ($rows ?: [] as $r) {
      $cfg = json_decode((string) $r->provider_config, true);
      if (is_array($cfg) && !empty($cfg['survey'])) {
        continue; // no support for surveys
      }

      $key = 'o:' . (int) $r->offer_id;
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = true;

      $clicks[] = [
        'clickId'         => (int) $r->id,
        'providerId'      => (int) $r->provider_id,
        'providerName'    => $r->provider_name,
        'providerOfferId' => $r->provider_offer_id,
        'offerName'       => $r->offer_name,
        'clickedAt'       => $r->created_at,
      ];
    }

    return $this->response(['clicks' => $clicks]);
  }

  /** Update the signed-in user's display name and/or email. */
  public function updateProfile()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $t = $wpdb->prefix . 'ro_users';

    $update = [];
    $formats = [];

    if ($this->request->get_param('display_name') !== null) {
      $displayName = sanitize_text_field((string) $this->request->get_param('display_name'));
      if ($displayName === '') {
        return $this->responseError('ro_invalid', __('Name cannot be empty.', 'simple-reward-offerwall'), 422);
      }
      $update['display_name'] = $displayName;
      $formats[] = '%s';
    }

    if ($this->request->get_param('email') !== null) {
      $email = sanitize_email((string) $this->request->get_param('email'));
      if (!is_email($email)) {
        return $this->responseError('ro_invalid_email', __('A valid email address is required.', 'simple-reward-offerwall'), 422);
      }
      $taken = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE email = %s AND id <> %d LIMIT 1", $email, $userId));
      if ($taken) {
        return $this->responseError('ro_email_taken', __('That email is already in use.', 'simple-reward-offerwall'), 409);
      }
      $update['email'] = $email;
      $formats[] = '%s';
    }

    if (empty($update)) {
      return $this->responseError('ro_invalid', __('Nothing to update.', 'simple-reward-offerwall'), 422);
    }

    $update['updated_at'] = gmdate('Y-m-d H:i:s');
    $formats[] = '%s';

    $wpdb->update($t, $update, ['id' => $userId], $formats, ['%d']);

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, email, type, status, display_name, unique_user_hash FROM {$t} WHERE id = %d LIMIT 1",
      $userId
    ));

    return $this->response([
      'user' => [
        'id'          => (int) $row->id,
        'email'       => $row->email,
        'type'        => $row->type,
        'status'      => $row->status,
        'displayName' => $row->display_name,
        'hash'        => $row->unique_user_hash,
      ],
    ]);
  }
}
