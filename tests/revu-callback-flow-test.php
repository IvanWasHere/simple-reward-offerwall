<?php
/**
 * Callback-flow integration test (RevU static_api).
 *
 * Run with WP-CLI:
 *   wp eval-file wp-content/plugins/simple-reward-offerwall/tests/revu-callback-flow-test.php
 *
 * Self-contained and re-runnable. Creates a marked revu static_api provider
 * (coin_rate = 1; the feed's total_user_reward is already coins), a singlestep +
 * a multi-event offer via the real RevUSchema::mapOffer, and one callback using
 * the schema's own param map. It fires GET postbacks and asserts each is processed
 * correctly:
 *
 *   paid conversion (amount > 0)        → logged + PENDING reward (= amount)
 *   reversal (status=reversal)          → logged + NEGATIVE reward
 *   zero amount                         → logged, no reward
 *   unknown sid2 (no such user)         → 200 ignored, nothing logged
 *
 * Exits non-zero if any assertion fails.
 */

if (!defined('ABSPATH')) {
  exit(1);
}

global $wpdb;

use SimpleRewardOffer\Providers\Schemas\OfferSchemaRegistry;

const RO_RU_PROVIDER = 'ZZ Test — RevU Callback Flow';
const RO_RU_EMAIL    = 'zz-revuflow@example.test';

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
  $providerIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}simplerewardoffer_providers WHERE name = %s", RO_RU_PROVIDER));
  $userIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}simplerewardoffer_users WHERE email = %s", RO_RU_EMAIL));
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

  $schema = OfferSchemaRegistry::for('revu');
  if (!$schema) {
    throw new \RuntimeException('revu schema is not registered.');
  }
  $now = gmdate('Y-m-d H:i:s');

  // 1) Provider (coin_rate = 1; feed reward is already coins) --------------
  $wpdb->insert($wpdb->prefix . 'simplerewardoffer_providers', [
    'unique_provider_hash' => bin2hex(random_bytes(16)),
    'name'         => RO_RU_PROVIDER,
    'type'         => 'static_api',
    'url'          => 'https://publishers.revenueuniverse.com/getoffers_api.php?wall=420&api_key=x',
    'coin_rate'    => 1,
    'offer_schema' => 'revu',
    'status'       => 'active',
    'created_at'   => $now,
    'updated_at'   => $now,
  ]);
  $providerId = (int) $wpdb->insert_id;

  // 2) Offers via the real schema (singlestep + multi-event) ---------------
  $rawOffers = [
    ['offer_id' => 77, 'headline' => 'Play & Earn', 'total_user_reward' => 500, 'countries' => ['US', 'GB'], 'platform' => ['android' => ['enabled' => true], 'ios' => ['enabled' => true]], 'click_url_base' => 'https://track.revenueuniverse.com/click?o=77&w=420&sid2=', 'creatives' => [['url' => 'https://img/play.png']]],
    ['offer_id' => 88, 'headline' => 'Idle Empire', 'total_user_reward' => 900, 'countries' => ['US'], 'platform' => ['android' => ['enabled' => true]], 'click_url_base' => 'https://track.revenueuniverse.com/click?o=88&w=420&sid2=', 'creatives' => [['url' => 'https://img/ie.png']], 'events' => [
      ['event_id' => 'e1', 'event_description' => 'Install', 'user_reward' => 300, 'payout' => 0.30],
      ['event_id' => 'e2', 'event_description' => 'Level 10', 'user_reward' => 600, 'payout' => 0.60],
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
  $link = (string) $wpdb->get_var($wpdb->prepare("SELECT link FROM {$wpdb->prefix}simplerewardoffer_offers WHERE provider_id = %d AND provider_offer_id = '77'", $providerId));
  $check('click link fills sid2={userID}', strpos($link, 'sid2={userID}') !== false, $link);

  // 3) User (id passed as sid2 → returns as sid2) --------------------------
  $userHash = bin2hex(random_bytes(16));
  $wpdb->insert($wpdb->prefix . 'simplerewardoffer_users', [
    'email'            => RO_RU_EMAIL,
    'password_hash'    => 'x',
    'display_name'     => 'RevU Flow Test',
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

  // Request-param keys come from the schema's default map (sid2, amount, …).
  $base = ['sid2' => $userId, 'offer_id' => '88', 'offer_name' => 'Idle Empire', 'ip' => '203.0.113.30', 'sid4' => 'e2'];

  $lookup = function (string $txn, string $type) use ($wpdb) {
    $p = $wpdb->prefix;
    $cb = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}simplerewardoffer_callbacks WHERE transaction_id = %s AND callback_type = %s", $txn, $type));
    $reward = $cb ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}simplerewardoffer_rewards WHERE callback_id = %d", (int) $cb->id)) : null;
    return [$cb, $reward];
  };

  // 5a) PAID CONVERSION (amount > 0) → PENDING reward = amount (coin_rate 1) -
  $r = $fire($cbHash, $base + ['transaction_id' => 'revu-paid', 'amount' => '600', 'payout' => '0.60']);
  [$cb, $reward] = $lookup('revu-paid', 'conversion');
  $check('conversion: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('conversion: coins = user reward', (int) ($r['body']['coins'] ?? 0) === 600, "got " . ($r['body']['coins'] ?? 'null'));
  $check('conversion: audit row for our user + event id', $cb !== null && (int) $cb->user_id === $userId && $cb->task_id === 'e2', $cb ? "user_id={$cb->user_id} task={$cb->task_id}" : 'missing');
  $check('conversion: PENDING reward stored', $reward !== null && (int) $reward->coins_value === 600 && $reward->status === 'pending', $reward ? "coins={$reward->coins_value}" : 'missing');

  // 5b) REVERSAL (status=reversal) → NEGATIVE reward -----------------------
  $r = $fire($cbHash, $base + ['transaction_id' => 'revu-rev', 'amount' => '600', 'status' => 'reversal']);
  [$cb, $reward] = $lookup('revu-rev', 'chargeback');
  $check('reversal: HTTP 200 reward=pending', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'pending'), json_encode($r['body']));
  $check('reversal: NEGATIVE reward', $reward !== null && (int) $reward->coins_value === -600, $reward ? "coins={$reward->coins_value}" : 'missing');

  // 5c) ZERO amount → logged, no reward -----------------------------------
  $r = $fire($cbHash, $base + ['transaction_id' => 'revu-zero', 'amount' => '0']);
  [$cb, $reward] = $lookup('revu-zero', 'conversion');
  $check('zero: HTTP 200 reward=none', ($r['code'] === 200 && ($r['body']['reward'] ?? '') === 'none'), json_encode($r['body']));
  $check('zero: audit row logged', $cb !== null);
  $check('zero: NO reward created', $reward === null);

  // 5d) UNKNOWN user (bad sid2) → ignored, nothing logged ------------------
  $r = $fire($cbHash, ['transaction_id' => 'revu-nouser', 'sid2' => '999999', 'amount' => '600', 'offer_id' => '88']);
  [$cb] = $lookup('revu-nouser', 'conversion');
  $check('unknown user: 200 ignored', ($r['code'] === 200 && ($r['body']['status'] ?? '') === 'ignored'), json_encode($r['body']));
  $check('unknown user: nothing logged', $cb === null);

  // Cross-cutting: informational macros captured; total rewards = 2.
  [$cbPaid] = $lookup('revu-paid', 'conversion');
  $mapped = $cbPaid ? json_decode((string) $cbPaid->mapped, true) : [];
  $check('informational macros captured (ip/payout)', is_array($mapped) && ($mapped['ip'] ?? null) === '203.0.113.30' && (string) ($mapped['payout'] ?? '') === '0.60', isset($mapped['ip']) ? "ip={$mapped['ip']} payout=" . ($mapped['payout'] ?? '') : 'no mapped payload');

  $rewardTotal = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}simplerewardoffer_rewards WHERE user_id = %d", $userId));
  $check('exactly two rewards (conversion + reversal)', $rewardTotal === 2, "reward rows = {$rewardTotal}");
} catch (\Throwable $e) {
  $results[] = ['ok' => false, 'label' => 'unexpected exception', 'detail' => $e->getMessage()];
}

$cleanup();

/* ----------------------------------------------------------------- report */

$pass = 0;
$fail = 0;
echo "\nRevU callback-flow test\n-----------------------\n";
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
