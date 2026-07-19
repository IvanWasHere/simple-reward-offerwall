<?php
/**
 * Callback-flow integration test (Lootably static_api).
 *
 * Run with WP-CLI:
 *   wp eval-file wp-content/plugins/simple-reward-offerwall/tests/lootably-callback-flow-test.php
 *
 * Self-contained and re-runnable. Creates a marked lootably static_api provider,
 * a singlestep + a multistep(goals) offer via the real LootablySchema::mapOffer,
 * and one all-macros callback signed with SHA256
 * (hash = sha256(userID + ip + revenue + currencyReward + secret)). It fires GET
 * postbacks and asserts each is processed correctly:
 *
 *   paid conversion (status=1, reward>0, valid hash) → logged + PENDING reward
 *   zero-reward goal (status=1, reward=0, valid hash) → logged, no reward
 *   chargeback      (status=0, valid hash)            → logged + NEGATIVE reward
 *   bad signature   (wrong hash)                      → 403, nothing logged
 *
 * Exits non-zero if any assertion fails.
 */

if (!defined('ABSPATH')) {
  exit(1);
}

global $wpdb;

use SimpleRO\Providers\Schemas\OfferSchemaRegistry;

const RO_LB_PROVIDER = 'ZZ Test — Lootably Callback Flow';
const RO_LB_EMAIL    = 'zz-lootablyflow@example.test';
const RO_LB_SECRET   = 'lootably-postback-secret-xyz';

/* ---------------------------------------------------------------- helpers */

$results = [];
$check = function (string $label, bool $cond, string $detail = '') use (&$results) {
  $results[] = ['ok' => $cond, 'label' => $label, 'detail' => $detail];
};

/** Lootably signature: sha256(userID + ip + revenue + currencyReward + secret). */
$sign = function (array $p): string {
  return hash('sha256', (string) $p['userID'] . (string) $p['ip'] . (string) $p['revenue'] . (string) $p['currencyReward'] . RO_LB_SECRET);
};

/** Fire a postback over real HTTP through the REST stack (GET, as Lootably does). */
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

$cleanup = function () use ($wpdb) {
  $p = $wpdb->prefix;
  $providerIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}ro_providers WHERE name = %s", RO_LB_PROVIDER));
  $userIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}ro_users WHERE email = %s", RO_LB_EMAIL));
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
  $cleanup();

  $schema = OfferSchemaRegistry::for('lootably');
  if (!$schema) {
    throw new \RuntimeException('lootably schema is not registered.');
  }
  $now = gmdate('Y-m-d H:i:s');

  // 1) Provider ------------------------------------------------------------
  $wpdb->insert($wpdb->prefix . 'ro_providers', [
    'unique_provider_hash' => bin2hex(random_bytes(16)),
    'name'         => RO_LB_PROVIDER,
    'type'         => 'static_api',
    'url'          => 'https://api.lootably.com/api/v2/offers/get',
    'api_key'      => 'test-api-key',
    'adslot_id'    => 'test-placement',
    'coin_rate'    => 1.0,
    'offer_schema' => 'lootably',
    'status'       => 'active',
    'created_at'   => $now,
    'updated_at'   => $now,
  ]);
  $providerId = (int) $wpdb->insert_id;

  // 2) Offers via the real schema (singlestep + multistep with goals) ------
  $rawOffers = [
    ['type' => 'singlestep', 'offerID' => 'lb-single-1', 'name' => 'Coin Master', 'image' => 'https://x/cm.png', 'countries' => ['US'], 'devices' => ['android', 'iphone'], 'link' => 'https://track.lootably.com/o/lb-single-1?u={userID}', 'currencyReward' => 300, 'revenue' => 1.2],
    ['type' => 'multistep', 'offerID' => 'lb-multi-1', 'name' => 'Idle Empire', 'image' => 'https://x/ie.png', 'countries' => ['US', 'CA'], 'devices' => ['android'], 'link' => 'https://track.lootably.com/o/lb-multi-1?u={userID}', 'goals' => [
      ['goalID' => 'g1', 'description' => 'Reach level 5', 'currencyReward' => 100, 'isOptional' => false],
      ['goalID' => 'g2', 'description' => 'Reach level 20', 'currencyReward' => 250, 'isOptional' => false],
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
  $offerCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ro_offers WHERE provider_id = %d", $providerId));
  $check('offers created and connected to provider', $offerCount === count($rawOffers), "expected " . count($rawOffers) . ", got {$offerCount}");
  $multiPayout = (float) $wpdb->get_var($wpdb->prepare("SELECT total_payout FROM {$wpdb->prefix}ro_offers WHERE provider_id = %d AND provider_offer_id = 'lb-multi-1'", $providerId));
  $check('multistep offer payout = sum of goals (350)', $multiPayout === 350.0, "got {$multiPayout}");

  // 3) User (userID sent to Lootably = our id, verified by the hash) --------
  $userHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'ro_users', [
    'email'            => RO_LB_EMAIL,
    'password_hash'    => 'x',
    'display_name'     => 'Lootably Flow Test',
    'type'             => 'user',
    'status'           => 'active',
    'unique_user_hash' => $userHash,
    'referral_code'    => substr(bin2hex(random_bytes(6)), 0, 10),
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);
  $userId = (int) $wpdb->insert_id;

  // 4) Callback carrying every macro, signed with SHA256 -------------------
  $cbHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'ro_provider_callbacks', [
    'provider_id'      => $providerId,
    'name'             => 'All macros',
    'unique_hash'      => $cbHash,
    'param_map'        => wp_json_encode($schema->defaultParamMap()),
    'signature_param'  => 'hash',
    'signature_algo'   => 'sha256_concat',
    'signature_source' => 'concat:userID,ip,revenue,currencyReward',
    'secret'           => RO_LB_SECRET,
    'ip_allowlist'     => '',
    'active'           => 1,
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);

  $base = [
    'userID'   => $userId,
    'offerID'  => 'lb-multi-1',
    'offerName' => 'Idle Empire',
    'ip'       => '203.0.113.9',
    'sid2'     => 'unit-test',
  ];

  $lookup = function (string $txn, string $type) use ($wpdb) {
    $p = $wpdb->prefix;
    $cb = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ro_callbacks WHERE transaction_id = %s AND callback_type = %s", $txn, $type));
    $reward = $cb ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ro_rewards WHERE callback_id = %d", (int) $cb->id)) : null;
    return [$cb, $reward];
  };

  // 5a) PAID CONVERSION (status=1, reward>0, valid hash) → PENDING reward ---
  $p = $base + ['transactionID' => 'lb-paid', 'status' => '1', 'currencyReward' => 300, 'revenue' => '1.2', 'goalID' => 'g2', 'goalName' => 'Reach level 20'];
  $p['hash'] = $sign($p);
  $r = $fire($cbHash, $p);
  [$cb, $reward] = $lookup('lb-paid', 'conversion');
  $check('conversion: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('conversion: response coins correct', (int) ($r['body']['coins'] ?? 0) === 300, "got " . ($r['body']['coins'] ?? 'null'));
  $check('conversion: audit row logged for our user', $cb !== null && (int) $cb->user_id === $userId, $cb ? "user_id={$cb->user_id}" : 'missing');
  $check('conversion: PENDING reward for exact coins', $reward !== null && (int) $reward->coins_value === 300 && $reward->status === 'pending', $reward ? "coins={$reward->coins_value} status={$reward->status}" : 'missing');

  // 5b) ZERO-REWARD goal (status=1, reward=0) → logged, no reward ----------
  $p = $base + ['transactionID' => 'lb-zero', 'status' => '1', 'currencyReward' => 0, 'revenue' => '0', 'goalID' => 'g1', 'goalName' => 'Reach level 5'];
  $p['hash'] = $sign($p);
  $r = $fire($cbHash, $p);
  [$cb, $reward] = $lookup('lb-zero', 'conversion');
  $check('zero-reward: HTTP 200 reward=none', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'none'), json_encode($r['body']));
  $check('zero-reward: audit row logged', $cb !== null && $cb->task_id === 'g1');
  $check('zero-reward: NO reward created', $reward === null);

  // 5c) CHARGEBACK (status=0) → NEGATIVE reward ----------------------------
  $p = $base + ['transactionID' => 'lb-cb', 'status' => '0', 'currencyReward' => 300, 'revenue' => '1.2'];
  $p['hash'] = $sign($p);
  $r = $fire($cbHash, $p);
  [$cb, $reward] = $lookup('lb-cb', 'chargeback');
  $check('chargeback: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('chargeback: NEGATIVE reward', $reward !== null && (int) $reward->coins_value === -300, $reward ? "coins={$reward->coins_value}" : 'missing');

  // 5d) BAD SIGNATURE → 403, nothing logged, no reward ---------------------
  $p = $base + ['transactionID' => 'lb-forged', 'status' => '1', 'currencyReward' => 300, 'revenue' => '1.2', 'hash' => 'deadbeefdeadbeef'];
  $r = $fire($cbHash, $p);
  [$cb, $reward] = $lookup('lb-forged', 'conversion');
  $check('bad signature: HTTP 403 rejected', $r['code'] === 403, "code={$r['code']}");
  $check('bad signature: nothing logged', $cb === null);

  // Cross-cutting: informational macros captured; total rewards = 2.
  [$cbPaid] = $lookup('lb-paid', 'conversion');
  $mapped = $cbPaid ? json_decode((string) $cbPaid->mapped, true) : [];
  $check('informational macros captured (ip/revenue/sid2)',
    is_array($mapped) && ($mapped['ip'] ?? null) === '203.0.113.9' && (string) ($mapped['revenue'] ?? '') === '1.2' && ($mapped['sid2'] ?? null) === 'unit-test',
    isset($mapped['ip']) ? "ip={$mapped['ip']} revenue=" . ($mapped['revenue'] ?? '') : 'no mapped payload');

  $rewardTotal = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ro_rewards WHERE user_id = %d", $userId));
  $check('exactly two rewards (paid conversion + chargeback)', $rewardTotal === 2, "reward rows = {$rewardTotal}");
} catch (\Throwable $e) {
  $results[] = ['ok' => false, 'label' => 'unexpected exception', 'detail' => $e->getMessage()];
}

$cleanup();

/* ----------------------------------------------------------------- report */

$pass = 0;
$fail = 0;
echo "\nLootably callback-flow test\n---------------------------\n";
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
