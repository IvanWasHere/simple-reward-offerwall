<?php

namespace SimpleRO\Providers\Schemas;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Schemas\Contracts\OfferSchema;

/**
 * LootablySchema — mapping for the Lootably Offers API + postbacks.
 *
 * Offers: POST https://api.lootably.com/api/v2/offers/get with a JSON body
 * {apiKey, placementID}; offers live under `data.offers`. We omit `userData` so
 * each offer's `link` keeps the `{userID}` macro, which ClicksController fills in
 * per user. Coins come straight from `currencyReward`, so Lootably providers run
 * with coin_rate = 1.
 *
 * Postbacks: GET, signed with SHA256 —
 *   hash = sha256(userID + ip + revenue + currencyReward + <postback secret>)
 * and `status` = "1" (conversion) / "0" (chargeback). Unlike ayet, Lootably has
 * no self-authenticating identifier, so its callbacks MUST be signed.
 */
class LootablySchema implements OfferSchema
{
  public function key(): string
  {
    return 'lootably';
  }

  public function label(): string
  {
    return 'Lootably';
  }

  public function httpMethod(): string
  {
    return 'POST';
  }

  public function requestBody(object $provider): array
  {
    // Catalogue request (no userData → links keep the {userID} macro). apiKey =
    // provider api_key, placementID = provider adslot_id.
    return [
      'apiKey'      => (string) ($provider->api_key ?? ''),
      'placementID' => (string) ($provider->adslot_id ?? ''),
    ];
  }

  public function offersPath(): string
  {
    return 'data.offers';
  }

  public function mapOffer(array $raw): ?array
  {
    $id = (string) ($raw['offerID'] ?? '');
    if ($id === '') {
      return null;
    }

    $image = (string) ($raw['image'] ?? '');
    $countries = $raw['countries'] ?? [];
    $devices = $raw['devices'] ?? [];

    return [
      'providerOfferId' => $id,
      'name'            => (string) ($raw['name'] ?? ''),
      'tasks'           => isset($raw['goals']) && is_array($raw['goals']) ? $raw['goals'] : null,
      // currencyReward is the offerwall-currency value = coins. Multistep offers
      // carry it per goal, so sum the goals; "variable" payouts read as 0.
      'totalPayout'     => $this->offerCoins($raw),
      'device'          => is_array($devices) ? implode(',', $devices) : (string) $devices,
      'os'              => '',
      'country'         => is_array($countries) ? implode(',', $countries) : (string) $countries,
      'icons'           => $image !== '' ? ['small' => $image] : [],
      // Holds {userID}; ClicksController substitutes it per user.
      'link'            => (string) ($raw['link'] ?? ''),
    ];
  }

  /** Total coin value for the offer card: singlestep reward, or sum of goals. */
  private function offerCoins(array $raw): float
  {
    if (isset($raw['goals']) && is_array($raw['goals'])) {
      $sum = 0.0;
      foreach ($raw['goals'] as $goal) {
        $sum += is_numeric($goal['currencyReward'] ?? null) ? (float) $goal['currencyReward'] : 0.0;
      }
      return $sum;
    }
    return is_numeric($raw['currencyReward'] ?? null) ? (float) $raw['currencyReward'] : 0.0;
  }

  public function callbackFields(): array
  {
    // Every Lootably postback macro. field = our key; key = default request param;
    // macro = the token for the postback URL. `mapped` marks values the
    // CallbackController acts on. ip + revenue are informational but MUST be sent
    // (the signature hashes userID + ip + revenue + currencyReward).
    return [
      ['field' => 'transaction_id', 'key' => 'transactionID', 'macro' => '{transactionID}', 'label' => 'Transaction ID', 'description' => 'The ID of this conversion in Lootably. Used for idempotency.', 'required' => true, 'mapped' => true],
      ['field' => 'user_id', 'key' => 'userID', 'macro' => '{userID}', 'label' => 'User ID', 'description' => 'The ID of the user that completed the offer (our user id).', 'required' => true, 'mapped' => true],
      ['field' => 'amount', 'key' => 'currencyReward', 'macro' => '{currencyReward}', 'label' => 'Currency reward (coins)', 'description' => 'User reward amount from placement settings. Credited as the reward.', 'required' => true, 'mapped' => true],
      ['field' => 'status', 'key' => 'status', 'macro' => '{status}', 'label' => 'Status', 'description' => '"1" = completion (reward), "0" = chargeback (negative reward).', 'required' => true, 'mapped' => true],
      ['field' => 'provider_offer_id', 'key' => 'offerID', 'macro' => '{offerID}', 'label' => 'Offer ID', 'description' => 'The ID of the offer the user completed.', 'required' => false, 'mapped' => true],
      ['field' => 'offer_name', 'key' => 'offerName', 'macro' => '{offerName}', 'label' => 'Offer name', 'description' => 'The name of the offer the user completed.', 'required' => false, 'mapped' => true],
      ['field' => 'task_id', 'key' => 'goalID', 'macro' => '{goalID}', 'label' => 'Goal ID', 'description' => 'Goal identifier for multi-step offers, if applicable.', 'required' => false, 'mapped' => true],
      ['field' => 'task_name', 'key' => 'goalName', 'macro' => '{goalName}', 'label' => 'Goal name', 'description' => 'Goal name if the offer contains goals.', 'required' => false, 'mapped' => true],
      // Informational (captured in the raw payload). ip + revenue feed the hash.
      ['field' => 'ip', 'key' => 'ip', 'macro' => '{ip}', 'label' => 'IP', 'description' => 'The IP address of the user when they started the offer. Part of the hash.', 'required' => false, 'mapped' => false],
      ['field' => 'revenue', 'key' => 'revenue', 'macro' => '{revenue}', 'label' => 'Revenue (USD)', 'description' => 'The USD value you received from the completed offer/goal. Part of the hash.', 'required' => false, 'mapped' => false],
      ['field' => 'numericOfferID', 'key' => 'numericOfferID', 'macro' => '{numericOfferID}', 'label' => 'Numeric offer ID', 'description' => 'Numeric-only variant of the offer ID.', 'required' => false, 'mapped' => false],
      ['field' => 'multistepOfferPercentageComplete', 'key' => 'multistepOfferPercentageComplete', 'macro' => '{multistepOfferPercentageComplete}', 'label' => 'Multistep % complete', 'description' => 'Completion percentage for multi-step offers (0-100).', 'required' => false, 'mapped' => false],
      ['field' => 'sid2', 'key' => 'sid2', 'macro' => '{sid2}', 'label' => 'Sub ID 2', 'description' => 'Custom tracking parameter.', 'required' => false, 'mapped' => false],
      ['field' => 'sid3', 'key' => 'sid3', 'macro' => '{sid3}', 'label' => 'Sub ID 3', 'description' => 'Custom tracking parameter.', 'required' => false, 'mapped' => false],
      ['field' => 'sid4', 'key' => 'sid4', 'macro' => '{sid4}', 'label' => 'Sub ID 4', 'description' => 'Custom tracking parameter.', 'required' => false, 'mapped' => false],
      ['field' => 'sid5', 'key' => 'sid5', 'macro' => '{sid5}', 'label' => 'Sub ID 5', 'description' => 'Custom tracking parameter.', 'required' => false, 'mapped' => false],
      ['field' => 'hash', 'key' => 'hash', 'macro' => '{hash}', 'label' => 'Hash', 'description' => 'SHA256 signature: sha256(userID + ip + revenue + currencyReward + secret).', 'required' => true, 'mapped' => false],
    ];
  }

  public function callbackMacros(): array
  {
    return array_map(
      static fn (array $f) => ['token' => $f['macro'], 'label' => $f['label'], 'description' => $f['description']],
      $this->callbackFields()
    );
  }

  public function postbackTemplate(): string
  {
    $parts = array_map(
      static fn (array $f) => $f['key'] . '=' . $f['macro'],
      $this->callbackFields()
    );

    return '?' . implode('&', $parts);
  }

  public function defaultParamMap(): array
  {
    $map = [];
    foreach ($this->callbackFields() as $f) {
      $map[$f['field']] = $f['key'];
    }
    return $map;
  }

  public function defaultSignatureParam(): string
  {
    return 'hash';
  }

  public function defaultSignatureAlgo(): string
  {
    return 'sha256_concat';
  }

  public function defaultSignatureSource(): string
  {
    // sha256(userID + ip + revenue + currencyReward + secret) — request param
    // names, concatenated in this order (see SignatureVerifier concat: source).
    return 'concat:userID,ip,revenue,currencyReward';
  }

  public function allowsUnsignedCallbacks(): bool
  {
    return false; // Lootably provides a real SHA256 signature; require it.
  }

  public function rewardRule(array $mapped): array
  {
    // Lootably: status "0" = chargeback, otherwise a completion.
    if ((string) ($mapped['status'] ?? '1') === '0') {
      return ['type' => 'chargeback', 'createsReward' => true, 'sign' => -1];
    }

    $amount = (float) ($mapped['amount'] ?? 0);
    if ($amount > 0) {
      return ['type' => 'conversion', 'createsReward' => true, 'sign' => 1];
    }

    // A zero-reward completion (e.g. an optional goal) — logged, no reward.
    return ['type' => 'conversion', 'createsReward' => false, 'sign' => 1];
  }
}
