<?php

namespace SimpleRewardOffer\Providers\Schemas;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Schemas\Contracts\OfferSchema;

/**
 * RevUSchema — mapping for the RevU (Revenue University / RevenueUniverse) Get
 * Offers API + postbacks.
 *
 * Offers: GET https://publishers.revenueuniverse.com/getoffers_api.php?wall={id}&api_key=…
 * (the admin's URL carries the wall id + api_key); offers live under `offers`.
 * `total_user_reward` is the reward in the wall's virtual currency — configure the
 * wall so 1 unit = 1 of our coins and run the provider with `coin_rate` = 1
 * (like ayet/lootably), so total_payout already holds coins.
 *
 * Click: the API's `click_url_base` ends with an empty `&sid2=`; we fill it with a
 * `{userID}` macro (ClicksController substitutes it per user), which RevU returns
 * in the postback as `sid2`. `sid4` is reserved by RevU for the event id.
 *
 * Postbacks: GET. RevU echoes `sid2` (user), `sid4` (event id) and the custom
 * `sid3/5/6` we passed, plus offer/reward fields. Authenticate live callbacks with
 * the callback's IP allowlist (and/or a secure-hash secret).
 */
class RevUSchema implements OfferSchema
{
  public function key(): string
  {
    return 'revu';
  }

  public function label(): string
  {
    return 'RevU';
  }

  public function httpMethod(): string
  {
    return 'GET';
  }

  public function requestBody(object $provider): array
  {
    return []; // GET feed; wall id + api_key live in the admin's URL.
  }

  public function offersPath(): string
  {
    return 'offers';
  }

  public function mapOffer(array $raw): ?array
  {
    $id = (string) ($raw['offer_id'] ?? '');
    if ($id === '') {
      return null;
    }

    // Marketing headline preferred; fall back to the internal campaign name.
    $name = (string) ($raw['headline'] ?? '');
    if ($name === '') {
      $name = (string) ($raw['offer_name'] ?? '');
    }

    $countries = $raw['countries'] ?? [];

    return [
      'providerOfferId' => $id,
      'name'            => $name,
      'tasks'           => isset($raw['events']) && is_array($raw['events']) ? $raw['events'] : null,
      // Reward in wall virtual currency = coins (coin_rate = 1).
      'totalPayout'     => (float) ($raw['total_user_reward'] ?? 0),
      'device'          => $this->devices($raw),
      'os'              => '',
      'country'         => is_array($countries) ? implode(',', $countries) : (string) $countries,
      'icons'           => $this->icon($raw),
      'link'            => $this->clickLink($raw),
    ];
  }

  /** Comma-joined enabled platforms from the `platform` object. */
  private function devices(array $raw): string
  {
    $plat = $raw['platform'] ?? [];
    if (!is_array($plat)) {
      return '';
    }
    $devices = [];
    if (!empty($plat['desktop'])) {
      $devices[] = 'desktop';
    }
    if (!empty($plat['android']['enabled'])) {
      $devices[] = 'android';
    }
    if (!empty($plat['ios']['enabled'])) {
      $devices[] = 'ios';
    }
    return implode(',', $devices);
  }

  /** First creative image url, wrapped as our icons shape. */
  private function icon(array $raw): array
  {
    $creatives = $raw['creatives'] ?? [];
    if (is_array($creatives)) {
      foreach ($creatives as $c) {
        if (!empty($c['url'])) {
          return ['small' => (string) $c['url']];
        }
      }
    }
    return [];
  }

  /**
   * Fill click_url_base's empty `sid2=` with our `{userID}` macro (substituted per
   * user at click time). Appends `&sid2={userID}` if the base has no sid2 slot.
   */
  private function clickLink(array $raw): string
  {
    $url = (string) ($raw['click_url_base'] ?? '');
    if ($url === '') {
      return '';
    }
    if (preg_match('/[?&]sid2=/', $url)) {
      $count = 0;
      $out = preg_replace('/([?&]sid2=)(?=&|$)/', '${1}{userID}', $url, 1, $count);
      return $count > 0 ? (string) $out : $url;
    }
    $sep = strpos($url, '?') !== false ? '&' : '?';
    return $url . $sep . 'sid2={userID}';
  }

  public function callbackFields(): array
  {
    // field = our canonical key; key = the request param name; macro = the RevU
    // postback token. `mapped` marks values the CallbackController acts on.
    return [
      ['field' => 'transaction_id', 'key' => 'transaction_id', 'macro' => '{transaction_id}', 'label' => 'Transaction ID', 'description' => 'Unique conversion id. Used for idempotency.', 'required' => true, 'mapped' => true],
      ['field' => 'user_id', 'key' => 'sid2', 'macro' => '{sid2}', 'label' => 'User ID (sid2)', 'description' => 'The sid2 value we appended to the click — our user id.', 'required' => true, 'mapped' => true],
      ['field' => 'amount', 'key' => 'amount', 'macro' => '{amount}', 'label' => 'User reward (coins)', 'description' => 'Virtual currency the user earned for this conversion. Credited as coins.', 'required' => true, 'mapped' => true],
      ['field' => 'provider_offer_id', 'key' => 'offer_id', 'macro' => '{offer_id}', 'label' => 'Offer ID', 'description' => 'The completed offer id.', 'required' => false, 'mapped' => true],
      ['field' => 'offer_name', 'key' => 'offer_name', 'macro' => '{offer_name}', 'label' => 'Offer name', 'description' => 'Name of the completed offer.', 'required' => false, 'mapped' => true],
      ['field' => 'task_id', 'key' => 'sid4', 'macro' => '{sid4}', 'label' => 'Event ID (sid4)', 'description' => 'Multi-event offers — the completed event id (RevU-reserved sid4).', 'required' => false, 'mapped' => true],
      // Informational (captured in the raw payload).
      ['field' => 'payout', 'key' => 'payout', 'macro' => '{payout}', 'label' => 'Payout (USD)', 'description' => 'Publisher payout in USD for the conversion.', 'required' => false, 'mapped' => false],
      ['field' => 'ip', 'key' => 'ip', 'macro' => '{ip}', 'label' => 'IP', 'description' => "Converting user's IP address.", 'required' => false, 'mapped' => false],
      ['field' => 'sid3', 'key' => 'sid3', 'macro' => '{sid3}', 'label' => 'Sub ID 3', 'description' => 'Custom tracking value passed as sid3.', 'required' => false, 'mapped' => false],
      ['field' => 'sid5', 'key' => 'sid5', 'macro' => '{sid5}', 'label' => 'Sub ID 5', 'description' => 'Custom tracking value passed as sid5.', 'required' => false, 'mapped' => false],
      ['field' => 'sid6', 'key' => 'sid6', 'macro' => '{sid6}', 'label' => 'Sub ID 6', 'description' => 'Custom tracking value passed as sid6.', 'required' => false, 'mapped' => false],
      ['field' => 'status', 'key' => 'status', 'macro' => '{status}', 'label' => 'Status', 'description' => 'Conversion status; a reversal/negative amount credits a chargeback.', 'required' => false, 'mapped' => false],
      ['field' => 'hash', 'key' => 'hash', 'macro' => '{hash}', 'label' => 'Secure hash', 'description' => 'Optional secure-hash signature (set the secret in the callback to verify).', 'required' => false, 'mapped' => false],
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
    return ''; // no signature by default — authenticate via the IP allowlist.
  }

  public function defaultSignatureAlgo(): string
  {
    return 'none';
  }

  public function defaultSignatureSource(): string
  {
    return 'ordered_params';
  }

  public function allowsUnsignedCallbacks(): bool
  {
    // No self-authenticating identifier; rely on the callback's IP allowlist
    // (RevU's postback IPs) — set it before going live.
    return true;
  }

  public function rewardRule(array $mapped): array
  {
    $status = strtolower(trim((string) ($mapped['status'] ?? '')));
    $amount = (float) ($mapped['amount'] ?? 0);

    // A reversal (explicit status or a negative amount) credits a chargeback.
    if ($amount < 0 || in_array($status, ['reversal', 'reversed', 'chargeback', 'declined'], true)) {
      return ['type' => 'chargeback', 'createsReward' => true, 'sign' => -1];
    }
    if ($amount > 0) {
      return ['type' => 'conversion', 'createsReward' => true, 'sign' => 1];
    }

    return ['type' => 'conversion', 'createsReward' => false, 'sign' => 1];
  }
}
