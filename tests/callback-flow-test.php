<?php
/**
 * Callback-flow integration test (AyetStudios static_api).
 *
 * Run with WP-CLI:
 *   wp eval-file wp-content/plugins/simple-reward-offerwall/tests/callback-flow-test.php
 *
 * It is self-contained and re-runnable: it creates a marked ayetstudios
 * static_api provider, a few offers connected to it, and one callback carrying
 * every macro; fires that callback against one offer for three event types and
 * asserts each is processed correctly; then deletes everything it created.
 *
 *   installation  → logged only, no reward
 *   optional      → visible-but-unpaid task: logged only, no reward
 *   conversion    → paid task: logged + a PENDING reward for the exact coins
 *
 * It also proves our user is resolved from the verified external_identifier and
 * never from ayet's own {user_id}.
 *
 * Exits non-zero if any assertion fails.
 */

if (!defined('ABSPATH')) {
  exit(1);
}

global $wpdb;

use SimpleRO\Providers\Schemas\OfferSchemaRegistry;
use SimpleRO\Services\Settings;

const RO_TEST_PROVIDER = 'ZZ Test — Ayet Callback Flow';
const RO_TEST_EMAIL    = 'zz-callbackflow@example.test';
const RO_TEST_AYET_UID  = 999; // ayet's own {user_id} — must be ignored for us.

/* ---------------------------------------------------------------- helpers */

$results = [];
$check = function (string $label, bool $cond, string $detail = '') use (&$results) {
  $results[] = ['ok' => $cond, 'label' => $label, 'detail' => $detail];
};

/** Fire a postback over real HTTP through the REST stack (as ayet would). */
$fire = function (string $hash, array $params): array {
  $url = home_url('/wp-json/simple-ro/v1/callback/' . $hash) . '?' . http_build_query($params);
  $res = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
  if (is_wp_error($res)) {
    return ['code' => 0, 'body' => ['error' => $res->get_error_message()]];
  }
  return [
    'code' => (int) wp_remote_retrieve_response_code($res),
    'body' => json_decode((string) wp_remote_retrieve_body($res), true) ?: [],
  ];
};

/** Delete every row tied to this test's markers (safe to call before + after). */
$cleanup = function () use ($wpdb) {
  $p = $wpdb->prefix;
  $providerIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}ro_providers WHERE name = %s", RO_TEST_PROVIDER));
  $userIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}ro_users WHERE email = %s", RO_TEST_EMAIL));

  if ($userIds) {
    $in = implode(',', array_map('intval', $userIds));
    $wpdb->query("DELETE FROM {$p}ro_rewards WHERE user_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}ro_callbacks WHERE user_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}ro_users WHERE id IN ({$in})");
  }
  if ($providerIds) {
    $in = implode(',', array_map('intval', $providerIds));
    $wpdb->query("DELETE FROM {$p}ro_callbacks WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}ro_provider_callbacks WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}ro_offers WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}ro_providers WHERE id IN ({$in})");
  }
};

/* ------------------------------------------------------------------- test */

try {
  $cleanup(); // clear any residue from a prior interrupted run

  $schema = OfferSchemaRegistry::for('ayetstudios');
  if (!$schema) {
    throw new \RuntimeException('ayetstudios schema is not registered.');
  }
  $now = gmdate('Y-m-d H:i:s');

  // 1) Provider ------------------------------------------------------------
  $wpdb->insert($wpdb->prefix . 'ro_providers', [
    'unique_provider_hash' => bin2hex(random_bytes(16)),
    'name'         => RO_TEST_PROVIDER,
    'type'         => 'static_api',
    'url'          => 'https://example.test/offers?apiKey=x',
    'coin_rate'    => 1.0, // ayet: currency_amount is already the coin value.
    'offer_schema' => 'ayetstudios',
    'status'       => 'active',
    'created_at'   => $now,
    'updated_at'   => $now,
  ]);
  $providerId = (int) $wpdb->insert_id;

  // 2) A few offers connected to the provider (mapped via the real schema) --
  $rawOffers = [
    ['id' => 218592, 'name' => 'UNIVERSE', 'icon' => 'https://x/i1.png', 'platform' => 'android', 'devices' => ['phone'], 'countries' => ['DE'], 'currency_amount' => 91, 'payout_usd' => 0.091, 'tracking_link' => 'https://ayet/s2s/218592?external_identifier={external_identifier}'],
    ['id' => 218819, 'name' => 'Wish', 'icon' => 'https://x/i2.png', 'platform' => 'android', 'devices' => ['phone', 'tablet'], 'countries' => ['US'], 'currency_amount' => 246, 'payout_usd' => 0.246, 'tracking_link' => 'https://ayet/s2s/218819?external_identifier={external_identifier}'],
    ['id' => 218820, 'name' => 'Idle Barber Shop Tycoon', 'icon' => 'https://x/i3.png', 'platform' => 'android', 'devices' => [], 'countries' => [], 'currency_amount' => 506, 'payout_usd' => 0.506, 'tracking_link' => 'https://ayet/s2s/218820?external_identifier={external_identifier}', 'tasks' => [
      ['name' => 'Install the app', 'uuid' => 'dff19bdd-3667-31f1-ac97-7523455a215d', 'currency_amount' => 56],
      ['name' => 'Open your own salon', 'uuid' => 'dff19bdd-3667-31f1-ac97-7523455a218a', 'currency_amount' => 112],
    ]],
  ];
  foreach ($rawOffers as $raw) {
    $n = $schema->mapOffer($raw);
    $wpdb->insert($wpdb->prefix . 'ro_offers', [
      'provider_id'       => $providerId,
      'provider_offer_id' => $n['providerOfferId'],
      'name'              => $n['name'],
      'tasks'             => wp_json_encode($n['tasks']),
      'total_payout'      => (float) $n['totalPayout'],
      'device'            => $n['device'],
      'os'                => $n['os'],
      'country'           => $n['country'],
      'icons'             => wp_json_encode($n['icons']),
      'link'              => $n['link'],
      'raw_json'          => wp_json_encode($raw),
      'active'            => 1,
      'created_at'        => $now,
      'updated_at'        => $now,
    ]);
  }
  $offerCount = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ro_offers WHERE provider_id = %d",
    $providerId
  ));
  $check('offers created and connected to provider', $offerCount === count($rawOffers), "expected " . count($rawOffers) . ", got {$offerCount}");

  // 3) User (resolved via external_identifier) -----------------------------
  $userHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'ro_users', [
    'email'            => RO_TEST_EMAIL,
    'password_hash'    => 'x',
    'display_name'     => 'Callback Flow Test',
    'type'             => 'user',
    'status'           => 'active',
    'unique_user_hash' => $userHash,
    'referral_code'    => substr(bin2hex(random_bytes(6)), 0, 10),
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);
  $userId = (int) $wpdb->insert_id;
  $externalId = Settings::buildExternalId($userId, $userHash);

  // 4) Callback carrying every macro (param_map = the full schema map) ------
  $cbHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'ro_provider_callbacks', [
    'provider_id'      => $providerId,
    'name'             => 'All macros',
    'unique_hash'      => $cbHash,
    'param_map'        => wp_json_encode($schema->defaultParamMap()),
    'signature_param'  => '',
    'signature_algo'   => 'none', // ayet authenticates via external_identifier.
    'signature_source' => 'ordered_params',
    'secret'           => '',
    'ip_allowlist'     => '',
    'active'           => 1,
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);
  $macroCount = count($schema->defaultParamMap());
  $check('callback stores the full macro map', $macroCount >= 30, "{$macroCount} macros mapped");

  // Shared base params (ayet sends its own user_id=999 alongside external_identifier).
  $offerId = '218820';
  $base = [
    'external_identifier'      => $externalId,
    'user_id'                  => RO_TEST_AYET_UID,
    'offer_id'                 => $offerId,
    'offer_name'               => 'Idle Barber Shop Tycoon',
    'currency_identifier'      => 'Coins',
    'is_chargeback'            => 0,
    'ip'                       => '203.0.113.7',
    'device_make'              => 'Samsung',
    'custom_1'                 => 'unit-test',
  ];

  // Reader: the audit row + any reward for a given (txn, type).
  $lookup = function (string $txn, string $type) use ($wpdb, $userId) {
    $p = $wpdb->prefix;
    $cb = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$p}ro_callbacks WHERE transaction_id = %s AND callback_type = %s",
      $txn, $type
    ));
    $reward = $cb ? $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$p}ro_rewards WHERE callback_id = %d",
      (int) $cb->id
    )) : null;
    return [$cb, $reward];
  };

  // 5a) INSTALLATION — logged, no reward ----------------------------------
  $txn = 'flow-install';
  $r = $fire($cbHash, $base + ['transaction_id' => $txn, 'callback_type' => 'installation', 'currency_amount' => 0, 'payout_usd' => 0, 'task_uuid' => '', 'task_name' => '']);
  [$cb, $reward] = $lookup($txn, 'installation');
  $check('installation: HTTP 200 reward=none', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'none'), json_encode($r['body']));
  $check('installation: audit row logged', $cb !== null && (int) $cb->user_id === $userId, $cb ? "user_id={$cb->user_id}" : 'missing');
  $check('installation: NO reward created', $reward === null);

  // 5b) OPTIONAL (visible, non-paying task) — logged, no reward -----------
  $txn = 'flow-optional';
  $r = $fire($cbHash, $base + ['transaction_id' => $txn, 'callback_type' => 'optional', 'currency_amount' => 0, 'payout_usd' => 0, 'task_uuid' => 'dff19bdd-3667-31f1-ac97-7523455a215d', 'task_name' => 'Install the app']);
  [$cb, $reward] = $lookup($txn, 'optional');
  $check('optional: HTTP 200 reward=none', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'none'), json_encode($r['body']));
  $check('optional: audit row logged with task', $cb !== null && $cb->task_id === 'dff19bdd-3667-31f1-ac97-7523455a215d');
  $check('optional: NO reward created', $reward === null);

  // 5c) CONVERSION (paid task) — logged + PENDING reward -------------------
  $txn = 'flow-paid';
  $coins = 112;
  $r = $fire($cbHash, $base + ['transaction_id' => $txn, 'callback_type' => 'conversion', 'currency_amount' => $coins, 'payout_usd' => 0.112, 'task_uuid' => 'dff19bdd-3667-31f1-ac97-7523455a218a', 'task_name' => 'Open your own salon']);
  [$cb, $reward] = $lookup($txn, 'conversion');
  $check('conversion: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('conversion: response coins correct', (int) ($r['body']['coins'] ?? 0) === $coins, "got " . ($r['body']['coins'] ?? 'null'));
  $check('conversion: audit row logged', $cb !== null && (float) ($cb->amount ?? 0) === (float) $coins, $cb ? "amount={$cb->amount}" : 'missing');
  $check('conversion: PENDING reward for exact coins', $reward !== null && (int) $reward->coins_value === $coins && $reward->status === 'pending', $reward ? "coins={$reward->coins_value} status={$reward->status}" : 'missing');

  // Cross-cutting: our user resolved from external_identifier, never ayet's 999.
  $check('user resolved from external_identifier (not ayet user_id)', $cb !== null && (int) $cb->user_id === $userId && $userId !== RO_TEST_AYET_UID, $cb ? "resolved={$cb->user_id}" : 'missing');
  // Informational macros are captured in the audit payload.
  $mapped = $cb ? json_decode((string) $cb->mapped, true) : [];
  $check('informational macros captured (ip/provider_user_id/custom_1)',
    is_array($mapped) && ($mapped['ip'] ?? null) === '203.0.113.7' && (string) ($mapped['provider_user_id'] ?? '') === (string) RO_TEST_AYET_UID && ($mapped['custom_1'] ?? null) === 'unit-test',
    isset($mapped['ip']) ? "ip={$mapped['ip']} provider_user_id=" . ($mapped['provider_user_id'] ?? '') : 'no mapped payload');

  // Total rewards for this user must be exactly one (only the paid conversion).
  $rewardTotal = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ro_rewards WHERE user_id = %d",
    $userId
  ));
  $check('exactly one reward across all three events', $rewardTotal === 1, "reward rows = {$rewardTotal}");
} catch (\Throwable $e) {
  $results[] = ['ok' => false, 'label' => 'unexpected exception', 'detail' => $e->getMessage()];
}

$cleanup(); // always tidy up, pass or fail

/* ----------------------------------------------------------------- report */

$pass = 0;
$fail = 0;
echo "\nAyetStudios callback-flow test\n------------------------------\n";
foreach ($results as $r) {
  echo ($r['ok'] ? "  \033[32m✓\033[0m " : "  \033[31m✗\033[0m ") . $r['label'];
  if ($r['detail'] !== '') {
    echo "  ({$r['detail']})";
  }
  echo "\n";
  $r['ok'] ? $pass++ : $fail++;
}
echo "\n{$pass} passed, {$fail} failed\n";

if ($fail > 0 && class_exists('WP_CLI')) {
  WP_CLI::halt(1);
}
