<?php

namespace SimpleRewardOffer\Providers\Schemas;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Schemas\Contracts\OfferSchema;

/**
 * AdscendMediaSchema — mapping for the Adscend Media Offers API v1 + postbacks.
 *
 * Offers: GET https://api.adscendmedia.com/v1/publisher/{pubId}/offers.json?api_key=…
 * (the admin's URL carries the pub id + api_key); offers live under `offers`.
 * `payout` is the affiliate earning in USD, so — unlike ayet/lootably where the
 * feed already gives coins — Adscend providers set `coin_rate` = coins-per-USD and
 * total_payout holds the USD payout (coins = payout × coin_rate).
 *
 * Click: the API's `click_url` needs our user id appended as `sub1`; we store it
 * with a `{userID}` macro that ClicksController substitutes per user, and it comes
 * back in the postback as `[subid1]`.
 *
 * Postbacks: GET. Adscend sends the `[subid*]` values we passed plus `[PAY]`,
 * `[CUR]`, `[transaction_id]`, `[offer_id]`, `[offer_name]`, `[event_*]`, `[ip]`
 * and an optional secure `[hash]`. Authenticate live callbacks with the callback's
 * IP allowlist (Adscend's postback IPs) and/or the secure-hash secret.
 */
class AdscendMediaSchema implements OfferSchema
{
  public function key(): string
  {
    return 'adscendmedia';
  }

  public function label(): string
  {
    return 'AdscendMedia';
  }

  public function httpMethod(): string
  {
    return 'GET';
  }

  public function requestBody(object $provider): array
  {
    return []; // GET feed; pub id + api_key live in the admin's URL.
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

    // Offer-wall product name preferred; fall back to the base offer name.
    $name = (string) ($raw['adwall_name'] ?? '');
    if ($name === '') {
      $name = (string) ($raw['name'] ?? '');
    }

    $countries = $raw['countries'] ?? [];

    return [
      'providerOfferId' => $id,
      'name'            => $name,
      'tasks'           => isset($raw['events']) && is_array($raw['events']) ? $raw['events'] : null,
      // Affiliate payout in USD; coin_rate converts it to coins on our side.
      'totalPayout'     => (float) ($raw['payout'] ?? 0),
      'device'          => '',
      'os'              => '',
      'country'         => is_array($countries) ? implode(',', $countries) : (string) $countries,
      'icons'           => $this->icon($raw),
      'link'            => $this->clickLink($raw),
    ];
  }

  /** First active image creative, wrapped as our icons shape. */
  private function icon(array $raw): array
  {
    $creatives = $raw['creatives'] ?? [];
    if (is_array($creatives)) {
      foreach ($creatives as $c) {
        if ((int) ($c['type'] ?? 0) === 1 && !empty($c['url'])) { // type 1 = image
          return ['small' => (string) $c['url']];
        }
      }
    }
    return [];
  }

  /** click_url + our user id as sub1 (`{userID}` substituted per user at click). */
  private function clickLink(array $raw): string
  {
    $url = (string) ($raw['click_url'] ?? '');
    if ($url === '') {
      return '';
    }
    $sep = strpos($url, '?') !== false ? '&' : '?';
    return $url . $sep . 'sub1={userID}';
  }

  public function callbackFields(): array
  {
    // field = our canonical key; key = the request param name we choose; macro =
    // the Adscend postback token (square brackets). `mapped` marks values the
    // CallbackController acts on; the rest are captured in the raw payload.
    return [
      ['field' => 'transaction_id', 'key' => 'transaction_id', 'macro' => '[transaction_id]', 'label' => 'Transaction ID', 'description' => 'Unique lead/transaction id. Used for idempotency.', 'required' => true, 'mapped' => true],
      ['field' => 'user_id', 'key' => 'subid1', 'macro' => '[subid1]', 'label' => 'User ID (sub1)', 'description' => 'The sub1 value we appended to the click — our user id.', 'required' => true, 'mapped' => true],
      ['field' => 'amount', 'key' => 'payout', 'macro' => '[PAY]', 'label' => 'Payout (USD)', 'description' => 'Commission owed for the lead. Credited as coins × coin_rate.', 'required' => true, 'mapped' => true],
      ['field' => 'provider_offer_id', 'key' => 'offer_id', 'macro' => '[offer_id]', 'label' => 'Offer ID', 'description' => 'The completed offer id.', 'required' => false, 'mapped' => true],
      ['field' => 'offer_name', 'key' => 'offer_name', 'macro' => '[offer_name]', 'label' => 'Offer name', 'description' => 'Name of the completed offer.', 'required' => false, 'mapped' => true],
      ['field' => 'task_id', 'key' => 'event_id', 'macro' => '[event_id]', 'label' => 'Event ID', 'description' => 'Multi-event offers — the completed event id.', 'required' => false, 'mapped' => true],
      ['field' => 'task_name', 'key' => 'event_name', 'macro' => '[event_name]', 'label' => 'Event name', 'description' => 'Multi-event offers — the completed event name.', 'required' => false, 'mapped' => true],
      // Informational (captured in the raw payload).
      ['field' => 'cur', 'key' => 'cur', 'macro' => '[CUR]', 'label' => 'Currency amount', 'description' => "Adscend-calculated user credits (from their offer-wall profile). We credit via [PAY] × coin_rate instead.", 'required' => false, 'mapped' => false],
      ['field' => 'ip', 'key' => 'ip', 'macro' => '[ip]', 'label' => 'IP', 'description' => "Converting user's IP address.", 'required' => false, 'mapped' => false],
      ['field' => 'subid2', 'key' => 'subid2', 'macro' => '[subid2]', 'label' => 'Sub ID 2', 'description' => 'Custom tracking value passed as sub2.', 'required' => false, 'mapped' => false],
      ['field' => 'subid3', 'key' => 'subid3', 'macro' => '[subid3]', 'label' => 'Sub ID 3', 'description' => 'Custom tracking value passed as sub3.', 'required' => false, 'mapped' => false],
      ['field' => 'subid4', 'key' => 'subid4', 'macro' => '[subid4]', 'label' => 'Sub ID 4', 'description' => 'Custom tracking value passed as sub4.', 'required' => false, 'mapped' => false],
      ['field' => 'hash', 'key' => 'hash', 'macro' => '[hash]', 'label' => 'Secure hash', 'description' => 'Optional secure-hash signature (set the secret in the callback to verify).', 'required' => false, 'mapped' => false],
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
    // Adscend has no self-authenticating identifier; rely on the callback's IP
    // allowlist (Adscend's postback IPs) — set it before going live.
    return true;
  }

  public function rewardRule(array $mapped): array
  {
    // Adscend reversals send a negative payout; a positive payout is a credit.
    $amount = (float) ($mapped['amount'] ?? 0);
    if ($amount < 0) {
      return ['type' => 'chargeback', 'createsReward' => true, 'sign' => -1];
    }
    if ($amount > 0) {
      return ['type' => 'conversion', 'createsReward' => true, 'sign' => 1];
    }

    return ['type' => 'conversion', 'createsReward' => false, 'sign' => 1];
  }
}
