<?php

namespace SimpleRO\API;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Services\SignatureVerifier;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * CallbackController — public server-to-server postback ingest.
 *
 *   GET|POST /wp-json/simple-ro/v1/callback/{unique_hash}
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

    $hash = (string) $this->request->get_param('hash');
    if (strlen($hash) !== 32 || !ctype_alnum($hash)) {
      return $this->responseError('ro_not_found', __('Unknown callback.', 'simple-reward-offerwall'), 404);
    }

    $cbTable = $wpdb->prefix . 'ro_provider_callbacks';
    $pTable = $wpdb->prefix . 'ro_providers';
    $callback = $wpdb->get_row($wpdb->prepare(
      "SELECT c.*, p.coin_rate, p.status AS provider_status
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
    $userId = (int) ($mapped['user_id'] ?? 0);
    $amount = (float) ($mapped['amount'] ?? 0);

    if ($txn === '' || $userId <= 0) {
      $this->logReject($callback->id, 'missing_txn_or_user', $ip);
      return $this->responseError('ro_bad_request', __('Missing transaction or user.', 'simple-reward-offerwall'), 400);
    }

    // User must exist and be active. Unknown user => 200 no-op (stop retries).
    $users = $wpdb->prefix . 'ro_users';
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$users} WHERE id = %d LIMIT 1", $userId));
    if ($status === null || $status === 'blocked') {
      $this->logReject($callback->id, 'unknown_or_blocked_user', $ip);
      return $this->response(['status' => 'ignored'], 200);
    }

    // Idempotent insert of the callback (UNIQUE provider_id, transaction_id).
    $suppress = $wpdb->suppress_errors(true);
    $inserted = $wpdb->insert(
      $wpdb->prefix . 'ro_callbacks',
      [
        'provider_id'          => (int) $callback->provider_id,
        'provider_callback_id' => (int) $callback->id,
        'transaction_id'       => $txn,
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
      ['%d', '%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    $wpdb->suppress_errors($suppress);

    if (!$inserted) {
      // Duplicate transaction — idempotent no-op so the network stops retrying.
      return $this->response(['status' => 'duplicate'], 200);
    }

    $callbackId = (int) $wpdb->insert_id;

    // Create the pending reward. coins = round(amount * provider coin_rate); may be
    // negative for chargebacks/reversals.
    $coins = (int) round($amount * (float) $callback->coin_rate);
    $wpdb->insert(
      $wpdb->prefix . 'ro_rewards',
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

  private function ip(): string
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
  }

  private function logReject(int $callbackConfigId, string $reason, string $ip): void
  {
    if (function_exists('logger')) {
      logger()->error("[simple-ro] callback rejected", ['callback' => $callbackConfigId, 'reason' => $reason, 'ip' => $ip]);
    }
  }
}
