<?php
/**
 * Callback-flow integration test (AdscendMedia static_api).
 *
 * Run with WP-CLI:
 *   wp eval-file wp-content/plugins/simple-reward-offerwall/tests/adscendmedia-callback-flow-test.php
 *
 * Self-contained and re-runnable. Creates a marked adscendmedia static_api
 * provider (coin_rate = coins-per-USD), a singlestep + a multi-event offer via the
 * real AdscendMediaSchema::mapOffer, and one callback using the schema's own param
 * map. It fires GET postbacks and asserts each is processed correctly:
 *
 *   paid conversion (payout > 0)   → logged + PENDING reward (payout × coin_rate)
 *   reversal        (payout < 0)   → logged + NEGATIVE reward
 *   zero payout     (payout = 0)   → logged, no reward
 *   unknown sub1 (no such user)    → 200 ignored, nothing logged
 *
 * Exits non-zero if any assertion fails.
 */

if (!defined('ABSPATH')) {
  exit(1);
}

global $wpdb;

use SimpleRewardOffer\Providers\Schemas\OfferSchemaRegistry;

const RO_AM_PROVIDER = 'ZZ Test — AdscendMedia Callback Flow';
const RO_AM_EMAIL    = 'zz-adscendflow@example.test';
const RO_AM_RATE     = 1000; // coins per 1.00 USD payout.

/* ---------------------------------------------------------------- helpers */

$results = [];
$check = function (string $label, bool $cond, string $detail = '') use (&$results) {
  $results[] = ['ok' => $cond, 'label' => $label, 'detail' => $detail];
};

$fire = function (string $hash, array $params): array {
  $url = home_url('/wp-json/simplerewardoffer/v1/callback/' . $hash) . '?' . http_build_query($params);
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
  $providerIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}simplerewardoffer_providers WHERE name = %s", RO_AM_PROVIDER));
  $userIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}simplerewardoffer_users WHERE email = %s", RO_AM_EMAIL));
  if ($userIds) {
    $in = implode(',', array_map('intval', $userIds));
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_rewards WHERE user_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_callbacks WHERE user_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_users WHERE id IN ({$in})");
  }
  if ($providerIds) {
    $in = implode(',', array_map('intval', $providerIds));
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_callbacks WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_provider_callbacks WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_offers WHERE provider_id IN ({$in})");
    $wpdb->query("DELETE FROM {$p}simplerewardoffer_providers WHERE id IN ({$in})");
  }
};

/* ------------------------------------------------------------------- test */

try {
  $cleanup();

  $schema = OfferSchemaRegistry::for('adscendmedia');
  if (!$schema) {
    throw new \RuntimeException('adscendmedia schema is not registered.');
  }
  $now = gmdate('Y-m-d H:i:s');

  // 1) Provider (coin_rate = coins-per-USD) --------------------------------
  $wpdb->insert($wpdb->prefix . 'simplerewardoffer_providers', [
    'unique_provider_hash' => bin2hex(random_bytes(16)),
    'name'         => RO_AM_PROVIDER,
    'type'         => 'static_api',
    'url'          => 'https://api.adscendmedia.com/v1/publisher/12345/offers.json?api_key=x',
    'coin_rate'    => RO_AM_RATE,
    'offer_schema' => 'adscendmedia',
    'status'       => 'active',
    'created_at'   => $now,
    'updated_at'   => $now,
  ]);
  $providerId = (int) $wpdb->insert_id;

  // 2) Offers via the real schema (singlestep + multi-event) ---------------
  $rawOffers = [
    ['offer_id' => 225, 'adwall_name' => 'Coin Game', 'payout' => 0.50, 'countries' => ['US', 'CA'], 'click_url' => 'https://asmclk.com/click.php?aff=1&camp=225&prod=9', 'creatives' => [['type' => 1, 'url' => 'https://img/coin.png']]],
    ['offer_id' => 300, 'adwall_name' => 'Idle Quest', 'payout' => 0.90, 'countries' => ['US'], 'click_url' => 'https://asmclk.com/click.php?aff=1&camp=300&prod=9', 'creatives' => [['type' => 1, 'url' => 'https://img/iq.png']], 'events' => [
      ['event_id' => 1, 'event_name' => 'Install', 'event_revenue' => 0.30],
      ['event_id' => 2, 'event_name' => 'Reach_level_10', 'event_revenue' => 0.60],
    ]],
  ];
  foreach ($rawOffers as $raw) {
    $n = $schema->mapOffer($raw);
    $wpdb->insert($wpdb->prefix . 'simplerewardoffer_offers', [
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
  $offerCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}simplerewardoffer_offers WHERE provider_id = %d", $providerId));
  $check('offers created and connected to provider', $offerCount === count($rawOffers), "expected " . count($rawOffers) . ", got {$offerCount}");
  $link = (string) $wpdb->get_var($wpdb->prepare("SELECT link FROM {$wpdb->prefix}simplerewardoffer_offers WHERE provider_id = %d AND provider_offer_id = '225'", $providerId));
  $check('click link carries sub1={userID} macro', strpos($link, 'sub1={userID}') !== false, $link);

  // 3) User (we pass its id as sub1 → comes back as subid1) ----------------
  $userHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'simplerewardoffer_users', [
    'email'            => RO_AM_EMAIL,
    'password_hash'    => 'x',
    'display_name'     => 'Adscend Flow Test',
    'type'             => 'user',
    'status'           => 'active',
    'unique_user_hash' => $userHash,
    'referral_code'    => substr(bin2hex(random_bytes(6)), 0, 10),
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);
  $userId = (int) $wpdb->insert_id;

  // 4) Callback using the schema's own param map (unsigned; IP allowlist ---
  //    would be set in production — omitted here so the test can fire freely).
  $cbHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'simplerewardoffer_provider_callbacks', [
    'provider_id'      => $providerId,
    'name'             => 'Postback',
    'unique_hash'      => $cbHash,
    'param_map'        => wp_json_encode($schema->defaultParamMap()),
    'signature_param'  => '',
    'signature_algo'   => 'none',
    'signature_source' => 'ordered_params',
    'secret'           => '',
    'ip_allowlist'     => '',
    'active'           => 1,
    'created_at'       => $now,
    'updated_at'       => $now,
  ]);

  // Request-param keys come from the schema's default map (subid1, payout, …).
  $base = ['subid1' => $userId, 'offer_id' => '300', 'offer_name' => 'Idle Quest', 'ip' => '203.0.113.20'];

  $lookup = function (string $txn, string $type) use ($wpdb) {
    $p = $wpdb->prefix;
    $cb = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}simplerewardoffer_callbacks WHERE transaction_id = %s AND callback_type = %s", $txn, $type));
    $reward = $cb ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}simplerewardoffer_rewards WHERE callback_id = %d", (int) $cb->id)) : null;
    return [$cb, $reward];
  };

  // 5a) PAID CONVERSION (payout > 0) → PENDING reward = payout × coin_rate --
  $r = $fire($cbHash, $base + ['transaction_id' => 'adsc-paid', 'payout' => '0.50', 'event_id' => '2', 'event_name' => 'Reach_level_10']);
  [$cb, $reward] = $lookup('adsc-paid', 'conversion');
  $expectCoins = (int) round(0.50 * RO_AM_RATE); // 500
  $check('conversion: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('conversion: coins = payout × coin_rate', (int) ($r['body']['coins'] ?? 0) === $expectCoins, "expected {$expectCoins}, got " . ($r['body']['coins'] ?? 'null'));
  $check('conversion: audit row for our user', $cb !== null && (int) $cb->user_id === $userId, $cb ? "user_id={$cb->user_id}" : 'missing');
  $check('conversion: PENDING reward stored', $reward !== null && (int) $reward->coins_value === $expectCoins && $reward->status === 'pending', $reward ? "coins={$reward->coins_value}" : 'missing');

  // 5b) REVERSAL (payout < 0) → NEGATIVE reward ----------------------------
  $r = $fire($cbHash, $base + ['transaction_id' => 'adsc-rev', 'payout' => '-0.50']);
  [$cb, $reward] = $lookup('adsc-rev', 'chargeback');
  $check('reversal: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('reversal: NEGATIVE reward', $reward !== null && (int) $reward->coins_value === -$expectCoins, $reward ? "coins={$reward->coins_value}" : 'missing');

  // 5c) ZERO payout → logged, no reward -----------------------------------
  $r = $fire($cbHash, $base + ['transaction_id' => 'adsc-zero', 'payout' => '0']);
  [$cb, $reward] = $lookup('adsc-zero', 'conversion');
  $check('zero: HTTP 200 reward=none', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'none'), json_encode($r['body']));
  $check('zero: audit row logged', $cb !== null);
  $check('zero: NO reward created', $reward === null);

  // 5d) UNKNOWN user (bad sub1) → ignored, nothing logged ------------------
  $r = $fire($cbHash, ['transaction_id' => 'adsc-nouser', 'subid1' => '999999', 'payout' => '0.50', 'offer_id' => '300']);
  [$cb] = $lookup('adsc-nouser', 'conversion');
  $check('unknown user: 200 ignored', ($r['code'] === 200 && ($r['body']['status'] ?? '') === 'ignored'), json_encode($r['body']));
  $check('unknown user: nothing logged', $cb === null);

  // Cross-cutting: informational macros captured; total rewards = 2.
  [$cbPaid] = $lookup('adsc-paid', 'conversion');
  $mapped = $cbPaid ? json_decode((string) $cbPaid->mapped, true) : [];
  $check('informational macros captured (ip)', is_array($mapped) && ($mapped['ip'] ?? null) === '203.0.113.20', isset($mapped['ip']) ? "ip={$mapped['ip']}" : 'no mapped payload');

  $rewardTotal = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}simplerewardoffer_rewards WHERE user_id = %d", $userId));
  $check('exactly two rewards (conversion + reversal)', $rewardTotal === 2, "reward rows = {$rewardTotal}");
} catch (\Throwable $e) {
  $results[] = ['ok' => false, 'label' => 'unexpected exception', 'detail' => $e->getMessage()];
}

$cleanup();

/* ----------------------------------------------------------------- report */

$pass = 0;
$fail = 0;
echo "\nAdscendMedia callback-flow test\n-------------------------------\n";
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
