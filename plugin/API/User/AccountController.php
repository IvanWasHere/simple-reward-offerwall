<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Services\LedgerService;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

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

    $r = $wpdb->prefix . 'simplerewardoffer_rewards';
    $c = $wpdb->prefix . 'simplerewardoffer_callbacks';
    $p = $wpdb->prefix . 'simplerewardoffer_providers';

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
    $t = $wpdb->prefix . 'simplerewardoffer_coin_ledger';

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
   * ONLY real offers from `simplerewardoffer_offers` are returned (INNER JOIN forces offer_id>0).
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

    $c = $wpdb->prefix . 'simplerewardoffer_clicked';
    $o = $wpdb->prefix . 'simplerewardoffer_offers';
    $p = $wpdb->prefix . 'simplerewardoffer_providers';

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

  /**
   * Record a device fingerprint for the signed-in user (captured by the SPA on
   * login). The client sends a `components` object of navigator/screen/timezone
   * signals; the server adds the request IP + user-agent and a visitor hash.
   */
  public function storeFingerprint()
  {
    global $wpdb;
    $user = Guard::user($this->request);

    $components = $this->request->get_param('components');
    if (!is_array($components)) {
      return $this->responseError('ro_invalid', __('Fingerprint components are required.', 'simple-reward-offerwall'), 422);
    }

    $get = static function (string $key) use ($components): string {
      return isset($components[$key]) && is_scalar($components[$key]) ? sanitize_text_field((string) $components[$key]) : '';
    };

    // Prefer the ThumbmarkJS hash sent by the client; otherwise derive a stable
    // fallback id from the summary signals.
    $visitorId = preg_replace('/[^a-f0-9]/i', '', (string) $this->request->get_param('visitorId'));
    if ($visitorId === '') {
      $visitorId = hash('sha256', implode('|', [
        $get('userAgent'), $get('platform'), $get('timezone'), $get('screen'), $get('vendor'),
      ]));
    }

    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_fingerprints',
      [
        'user_id'    => (int) $user->id,
        'visitor_id' => $visitorId,
        'ip'         => $this->clientIp(),
        'user_agent' => substr($get('userAgent') ?: (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'platform'   => substr($get('platform'), 0, 120),
        'language'   => substr($get('language'), 0, 60),
        'timezone'   => substr($get('timezone'), 0, 80),
        'screen'     => substr($get('screen'), 0, 40),
        'data'       => wp_json_encode($components),
        'created_at' => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    return $this->response(['ok' => true, 'visitorId' => $visitorId], 201);
  }

  /** Best-effort client IP from the request (honours a single proxy hop). */
  private function clientIp(): string
  {
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
      $first = trim(explode(',', $forwarded)[0]);
      if (filter_var($first, FILTER_VALIDATE_IP)) {
        return $first;
      }
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
  }

  /** Update the signed-in user's display name and/or email. */
  public function updateProfile()
  {
    global $wpdb;
    $user = Guard::user($this->request);
    $userId = (int) $user->id;
    $t = $wpdb->prefix . 'simplerewardoffer_users';

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
