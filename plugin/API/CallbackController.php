<?php

namespace SimpleRewardOffer\API;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Schemas\OfferSchemaRegistry;
use SimpleRewardOffer\Services\Settings;
use SimpleRewardOffer\Services\SignatureVerifier;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * CallbackController — public server-to-server postback ingest.
 *
 *   GET|POST /wp-json/simplerewardoffer/v1/callback/{unique_hash}
 *
 * The provider is configured with this exact URL. We select the callback config
 * by hash, verify the signature, map the incoming params to our fields, log the
 * postback idempotently (UNIQUE provider_id+transaction_id) and create a PENDING
 * reward. Coins are only credited later, on admin approval.
 */
class CallbackController extends RestController
{
  public function handle()
  {
    global $wpdb;

    // Read the ROUTE hash specifically — a provider may also send a query param
    // named 'hash' (e.g. Lootably's signature), and query params outrank URL
    // params in WP_REST_Request::get_param(), which would shadow the selector.
    $urlParams = $this->request->get_url_params();
    $hash = (string) ($urlParams['hash'] ?? $this->request->get_param('hash'));
    if (strlen($hash) !== 32 || !ctype_alnum($hash)) {
      return $this->responseError('ro_not_found', __('Unknown callback.', 'simple-reward-offerwall'), 404);
    }

    $cbTable = $wpdb->prefix . 'simplerewardoffer_provider_callbacks';
    $pTable = $wpdb->prefix . 'simplerewardoffer_providers';
    $callback = $wpdb->get_row($wpdb->prepare(
      "SELECT c.*, p.coin_rate, p.offer_schema, p.status AS provider_status
         FROM {$cbTable} c
         INNER JOIN {$pTable} p ON p.id = c.provider_id
        WHERE c.unique_hash = %s
        LIMIT 1",
      $hash
    ));

    if (!$callback || (int) $callback->active !== 1 || $callback->provider_status !== 'active') {
      return $this->responseError('ro_not_found', __('Unknown callback.', 'simple-reward-offerwall'), 404);
    }

    // Params the provider actually sent (query + body), excluding route params.
    $params = array_merge(
      (array) $this->request->get_query_params(),
      (array) $this->request->get_body_params()
    );

    $ip = $this->ip();

    // Optional IP allowlist.
    $allow = array_filter(array_map('trim', explode(',', (string) $callback->ip_allowlist)));
    if ($allow && !in_array($ip, $allow, true)) {
      $this->logReject($callback->id, 'ip_not_allowed', $ip);
      return $this->responseError('ro_forbidden', __('Not allowed.', 'simple-reward-offerwall'), 403);
    }

    // Signature.
    if (!SignatureVerifier::verify($callback, $params)) {
      $this->logReject($callback->id, 'bad_signature', $ip);
      return $this->responseError('ro_bad_signature', __('Invalid signature.', 'simple-reward-offerwall'), 403);
    }

    // Map incoming params -> our fields. param_map = { our_field: incoming_key }.
    $map = json_decode((string) $callback->param_map, true) ?: [];
    $mapped = [];
    foreach ($map as $ourField => $incomingKey) {
      $mapped[$ourField] = $params[$incomingKey] ?? null;
    }

    $txn = trim((string) ($mapped['transaction_id'] ?? ''));
    $amount = (float) ($mapped['amount'] ?? 0);

    if ($txn === '') {
      $this->logReject($callback->id, 'missing_txn', $ip);
      return $this->responseError('ro_bad_request', __('Missing transaction.', 'simple-reward-offerwall'), 400);
    }

    // Resolve our user: a directly-mapped user_id (legacy), or a verified
    // external_identifier (<prefix>-<user_id>-<user_hash>). The 128-bit user_hash
    // is the callback's shared secret, so a forged id can't be minted.
    $users = $wpdb->prefix . 'simplerewardoffer_users';
    $userId = (int) ($mapped['user_id'] ?? 0);
    $ext = trim((string) ($mapped['external_identifier'] ?? ''));
    if ($userId <= 0 && $ext !== '') {
      $userId = $this->resolveUserFromExternalId($ext);
      if ($userId <= 0) {
        // A present-but-unverifiable id is a forged/stale postback: 200 no-op.
        $this->logReject($callback->id, 'bad_external_identifier', $ip);
        return $this->response(['status' => 'ignored'], 200);
      }
    }

    if ($userId <= 0) {
      $this->logReject($callback->id, 'missing_user', $ip);
      return $this->responseError('ro_bad_request', __('Missing user.', 'simple-reward-offerwall'), 400);
    }

    // User must exist and be active. Unknown user => 200 no-op (stop retries).
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$users} WHERE id = %d LIMIT 1", $userId));
    if ($status === null || $status === 'blocked') {
      $this->logReject($callback->id, 'unknown_or_blocked_user', $ip);
      return $this->response(['status' => 'ignored'], 200);
    }

    // Decide what this event does. A schema classifies the callback (paid
    // conversion / chargeback → reward; installation / optional / iap / iaa →
    // audit only). Without a schema, keep the legacy "always a pending reward".
    $schema = OfferSchemaRegistry::for($callback->offer_schema ?? '');
    if ($schema) {
      $rule = $schema->rewardRule($mapped);
      $createsReward = (bool) $rule['createsReward'];
      $coins = (int) round(abs($amount) * (float) $callback->coin_rate) * (int) $rule['sign'];
    } else {
      $createsReward = true;
      $coins = (int) round($amount * (float) $callback->coin_rate);
    }
    $callbackType = substr((string) ($mapped['callback_type'] ?? ($schema ? $rule['type'] : '')), 0, 30);

    // Idempotent audit insert (UNIQUE provider_id, transaction_id, callback_type).
    // Every callback is logged, including audit-only types.
    $suppress = $wpdb->suppress_errors(true);
    $inserted = $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_callbacks',
      [
        'provider_id'          => (int) $callback->provider_id,
        'provider_callback_id' => (int) $callback->id,
        'transaction_id'       => $txn,
        'callback_type'        => $callbackType,
        'user_id'              => $userId,
        'provider_offer_id'    => (string) ($mapped['provider_offer_id'] ?? ''),
        'task_id'              => (string) ($mapped['task_id'] ?? ''),
        'amount'               => $amount,
        'currency'             => substr((string) ($mapped['currency'] ?? ''), 0, 10),
        'raw_payload'          => wp_json_encode($params),
        'mapped'               => wp_json_encode($mapped),
        'signature_ok'         => 1,
        'status'               => 'processed',
        'ip'                   => $ip,
        'created_at'           => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    $wpdb->suppress_errors($suppress);

    if (!$inserted) {
      // Duplicate transaction — idempotent no-op so the network stops retrying.
      return $this->response(['status' => 'duplicate'], 200);
    }

    if (!$createsReward) {
      // Logged for the audit trail, but no reward (installation/optional/iap/iaa).
      return $this->response(['status' => 'ok', 'reward' => 'none'], 200);
    }

    $callbackId = (int) $wpdb->insert_id;

    // Create the pending reward. coins may be negative for chargebacks/reversals.
    // Coins are only credited later, on admin approval.
    $wpdb->insert(
      $wpdb->prefix . 'simplerewardoffer_rewards',
      [
        'user_id'     => $userId,
        'callback_id' => $callbackId,
        'coins_value' => $coins,
        'status'      => 'pending',
        'created_at'  => gmdate('Y-m-d H:i:s'),
        'updated_at'  => gmdate('Y-m-d H:i:s'),
      ],
      ['%d', '%d', '%d', '%s', '%s', '%s']
    );

    return $this->response(['status' => 'ok', 'reward' => 'pending', 'coins' => $coins], 200);
  }

  /**
   * Resolve + verify our user from an incoming external_identifier. Returns the
   * user id only when the embedded hash matches simplerewardoffer_users.unique_user_hash
   * (constant-time), else 0.
   */
  private function resolveUserFromExternalId(string $externalId): int
  {
    global $wpdb;

    [$userId, $hash] = Settings::parseExternalId($externalId);
    if ($userId <= 0 || $hash === '') {
      return 0;
    }

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT id, unique_user_hash FROM {$wpdb->prefix}simplerewardoffer_users WHERE id = %d LIMIT 1",
      $userId
    ));
    if (!$row || !hash_equals((string) $row->unique_user_hash, $hash)) {
      return 0;
    }

    return (int) $row->id;
  }

  private function ip(): string
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
  }

  private function logReject(int $callbackConfigId, string $reason, string $ip): void
  {
    if (function_exists('logger')) {
      logger()->error("[simplerewardoffer] callback rejected", ['callback' => $callbackConfigId, 'reason' => $reason, 'ip' => $ip]);
    }
  }
}
