<?php

namespace SimpleRewardOffer\Providers\Schemas;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Schemas\Contracts\OfferSchema;

/**
 * AyetStudiosSchema — mapping for the AyetStudios Static/Offerwall API.
 *
 * Offers request: GET <provider.url> (the admin's URL already carries ?apiKey=…),
 * offers live at the top-level `offers` array. Coins come straight from ayet's
 * `currency_amount` (already the offerwall currency), so ayet providers run with
 * coin_rate = 1.
 *
 * Callbacks: ayet does not HMAC-sign postbacks by default — the unguessable
 * user_hash inside `external_identifier` (=<prefix>-<user_id>-<user_hash>) is the
 * shared secret, verified server-side. A pending reward is created only for paid
 * conversions (payout > 0) and chargebacks (negative); installation / optional /
 * iap / iaa callbacks are logged only.
 */
class AyetStudiosSchema implements OfferSchema
{
  public function key(): string
  {
    return 'ayetstudios';
  }

  public function label(): string
  {
    return 'AyetStudios';
  }

  public function httpMethod(): string
  {
    return 'GET';
  }

  public function requestBody(object $provider): array
  {
    return []; // GET feed; the apiKey is already in the admin's URL.
  }

  public function offersPath(): string
  {
    return 'offers';
  }

  public function mapOffer(array $raw): ?array
  {
    $id = (string) ($raw['id'] ?? '');
    if ($id === '') {
      return null;
    }

    $icon = (string) ($raw['icon'] ?? '');
    $countries = $raw['countries'] ?? [];
    $devices = $raw['devices'] ?? [];

    return [
      'providerOfferId' => $id,
      'name'            => (string) ($raw['name'] ?? ''),
      'tasks'           => $raw['tasks'] ?? null,
      // currency_amount is the offerwall-currency value = the coins for this offer.
      'totalPayout'     => (float) ($raw['currency_amount'] ?? 0),
      'device'          => is_array($devices) ? implode(',', $devices) : (string) $devices,
      'os'              => (string) ($raw['platform'] ?? ''),
      'country'         => is_array($countries) ? implode(',', $countries) : (string) $countries,
      'icons'           => $icon !== '' ? ['small' => $icon] : [],
      // Holds {external_identifier}; ClicksController substitutes it per user.
      'link'            => (string) ($raw['tracking_link'] ?? ''),
    ];
  }

  public function callbackFields(): array
  {
    // Every ayet postback macro. field = our key for this value; key = default
    // request param; macro = the token to place in the postback URL. `mapped`
    // marks the values our CallbackController acts on (reward/idempotency/user);
    // the rest are informational — sent in the URL and captured in the raw
    // payload for the admin audit. This is the single source of truth:
    // defaultParamMap() + postbackTemplate() derive from it, so every macro is
    // available in the form and included in the generated callback URL.
    return [
      // --- Values the system acts on -------------------------------------
      ['field' => 'transaction_id', 'key' => 'transaction_id', 'macro' => '{transaction_id}', 'label' => 'Transaction ID', 'description' => 'Unique transaction id (chargebacks prepend r-). Used for idempotency.', 'required' => true, 'mapped' => true],
      ['field' => 'external_identifier', 'key' => 'external_identifier', 'macro' => '{external_identifier}', 'label' => 'External identifier', 'description' => 'The value we passed when requesting offers — resolves + verifies our user.', 'required' => true, 'mapped' => true],
      ['field' => 'amount', 'key' => 'currency_amount', 'macro' => '{currency_amount}', 'label' => 'Amount (coins)', 'description' => 'Coins the user earns (negative on chargeback). Credited as the reward.', 'required' => true, 'mapped' => true],
      ['field' => 'callback_type', 'key' => 'callback_type', 'macro' => '{callback_type}', 'label' => 'Callback type', 'description' => 'conversion for paid conversions, chargeback for chargebacks.', 'required' => false, 'mapped' => true],
      ['field' => 'is_chargeback', 'key' => 'is_chargeback', 'macro' => '{is_chargeback}', 'label' => 'Is chargeback', 'description' => '0 = conversion, 1 = chargeback (credits a negative reward).', 'required' => false, 'mapped' => true],
      ['field' => 'provider_offer_id', 'key' => 'offer_id', 'macro' => '{offer_id}', 'label' => 'Offer ID', 'description' => 'Offer id of the converting offer.', 'required' => false, 'mapped' => true],
      ['field' => 'offer_name', 'key' => 'offer_name', 'macro' => '{offer_name}', 'label' => 'Offer name', 'description' => 'Name / title of the converting offer.', 'required' => false, 'mapped' => true],
      ['field' => 'task_id', 'key' => 'task_uuid', 'macro' => '{task_uuid}', 'label' => 'Task UUID', 'description' => 'CPE campaigns only — persistent task UUID for that conversion.', 'required' => false, 'mapped' => true],
      ['field' => 'task_name', 'key' => 'task_name', 'macro' => '{task_name}', 'label' => 'Task name', 'description' => 'CPE campaigns only — individual task name shown to the user.', 'required' => false, 'mapped' => true],
      ['field' => 'currency', 'key' => 'currency_identifier', 'macro' => '{currency_identifier}', 'label' => 'Currency identifier', 'description' => 'Virtual currency name as set in the adslot.', 'required' => false, 'mapped' => true],
      // --- Informational (captured in the raw payload) -------------------
      ['field' => 'payout_usd', 'key' => 'payout_usd', 'macro' => '{payout_usd}', 'label' => 'Payout (USD)', 'description' => 'The actual conversion payout in USD (negative on chargeback).', 'required' => false, 'mapped' => false],
      // NB: not our canonical `user_id` — this is ayet's internal id. Kept under a
      // distinct field so it never feeds CallbackController's user resolution.
      ['field' => 'provider_user_id', 'key' => 'user_id', 'macro' => '{user_id}', 'label' => 'Provider user ID', 'description' => "ayeT's internal ID for this offerwall user.", 'required' => false, 'mapped' => false],
      ['field' => 'placement_identifier', 'key' => 'placement_identifier', 'macro' => '{placement_identifier}', 'label' => 'Placement identifier', 'description' => 'The placement_identifier for which the conversion occurred.', 'required' => false, 'mapped' => false],
      ['field' => 'adslot_id', 'key' => 'adslot_id', 'macro' => '{adslot_id}', 'label' => 'Ad slot ID', 'description' => 'The ID of the adslot for which the conversion occurred.', 'required' => false, 'mapped' => false],
      ['field' => 'sub_id', 'key' => 'sub_id', 'macro' => '{sub_id}', 'label' => 'Sub ID', 'description' => 'The ID of the placement for which the conversion occurred.', 'required' => false, 'mapped' => false],
      ['field' => 'ip', 'key' => 'ip', 'macro' => '{ip}', 'label' => 'IP', 'description' => "Converting device's IP address if known, 0.0.0.0 otherwise.", 'required' => false, 'mapped' => false],
      ['field' => 'device_uuid', 'key' => 'device_uuid', 'macro' => '{device_uuid}', 'label' => 'Device UUID', 'description' => 'ayeT-Studios internal device identificator.', 'required' => false, 'mapped' => false],
      ['field' => 'device_make', 'key' => 'device_make', 'macro' => '{device_make}', 'label' => 'Device make', 'description' => 'Device manufacturer.', 'required' => false, 'mapped' => false],
      ['field' => 'device_model', 'key' => 'device_model', 'macro' => '{device_model}', 'label' => 'Device model', 'description' => 'Device model.', 'required' => false, 'mapped' => false],
      ['field' => 'advertising_id', 'key' => 'advertising_id', 'macro' => '{advertising_id}', 'label' => 'Advertising ID', 'description' => 'Device advertising id (GAID/IDFA) if known, otherwise empty.', 'required' => false, 'mapped' => false],
      ['field' => 'sha1_android_id', 'key' => 'sha1_android_id', 'macro' => '{sha1_android_id}', 'label' => 'SHA1 Android ID', 'description' => 'Device sha1 hashed android id if known, otherwise empty.', 'required' => false, 'mapped' => false],
      ['field' => 'sha1_imei', 'key' => 'sha1_imei', 'macro' => '{sha1_imei}', 'label' => 'SHA1 IMEI', 'description' => 'Device sha1 hashed imei if known, otherwise empty.', 'required' => false, 'mapped' => false],
      ['field' => 'chargeback_reason', 'key' => 'chargeback_reason', 'macro' => '{chargeback_reason}', 'label' => 'Chargeback reason', 'description' => 'Reason why chargeback created. Only when is_chargeback = 1.', 'required' => false, 'mapped' => false],
      ['field' => 'chargeback_date', 'key' => 'chargeback_date', 'macro' => '{chargeback_date}', 'label' => 'Chargeback date', 'description' => 'Date of chargeback creation. Only when is_chargeback = 1.', 'required' => false, 'mapped' => false],
      ['field' => 'event_name', 'key' => 'event_name', 'macro' => '{event_name}', 'label' => 'Event name', 'description' => 'For CPA & CPE campaigns, internal event name of the conversion.', 'required' => false, 'mapped' => false],
      ['field' => 'event_value', 'key' => 'event_value', 'macro' => '{event_value}', 'label' => 'Event value', 'description' => 'Value associated with the event (non-billable; IAP/IAA value).', 'required' => false, 'mapped' => false],
      ['field' => 'currency_conversion_rate', 'key' => 'currency_conversion_rate', 'macro' => '{currency_conversion_rate}', 'label' => 'Currency conversion rate', 'description' => 'Conversion rate used to calculate the user currency for this conversion.', 'required' => false, 'mapped' => false],
      ['field' => 'callback_ts', 'key' => 'callback_ts', 'macro' => '{callback_ts}', 'label' => 'Callback timestamp', 'description' => 'Timestamp at which the user triggered the event/callback.', 'required' => false, 'mapped' => false],
      ['field' => 'click_date', 'key' => 'click_date', 'macro' => '{click_date}', 'label' => 'Click date', 'description' => "Date/time (Y-m-d H:i:s) at which the user clicked the offer.", 'required' => false, 'mapped' => false],
      ['field' => 'custom_1', 'key' => 'custom_1', 'macro' => '{custom_1}', 'label' => 'Custom 1', 'description' => 'Custom parameter appended to tracking links / the offerwall entry URL.', 'required' => false, 'mapped' => false],
      ['field' => 'custom_2', 'key' => 'custom_2', 'macro' => '{custom_2}', 'label' => 'Custom 2', 'description' => 'Custom parameter appended to tracking links / the offerwall entry URL.', 'required' => false, 'mapped' => false],
      ['field' => 'custom_3', 'key' => 'custom_3', 'macro' => '{custom_3}', 'label' => 'Custom 3', 'description' => 'Custom parameter appended to tracking links / the offerwall entry URL.', 'required' => false, 'mapped' => false],
      ['field' => 'custom_4', 'key' => 'custom_4', 'macro' => '{custom_4}', 'label' => 'Custom 4', 'description' => 'Custom parameter appended to tracking links / the offerwall entry URL.', 'required' => false, 'mapped' => false],
      ['field' => 'custom_5', 'key' => 'custom_5', 'macro' => '{custom_5}', 'label' => 'Custom 5', 'description' => 'Custom parameter appended to tracking links / the offerwall entry URL.', 'required' => false, 'mapped' => false],
    ];
  }

  public function callbackMacros(): array
  {
    // Reference list = every field's token (callbackFields is already complete).
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
    return '';
  }

  public function defaultSignatureAlgo(): string
  {
    return 'none'; // authenticated by the verified external_identifier.
  }

  public function defaultSignatureSource(): string
  {
    return 'ordered_params';
  }

  public function allowsUnsignedCallbacks(): bool
  {
    return true; // the unguessable user_hash in external_identifier is the secret.
  }

  public function rewardRule(array $mapped): array
  {
    $type = strtolower(trim((string) ($mapped['callback_type'] ?? '')));
    $isChargeback = (int) ($mapped['is_chargeback'] ?? 0) === 1 || $type === 'chargeback';

    if ($isChargeback) {
      return ['type' => 'chargeback', 'createsReward' => true, 'sign' => -1];
    }

    $amount = (float) ($mapped['amount'] ?? 0);
    // A paid conversion is the only forward credit; ayet may send an empty type
    // on the primary conversion callback, so treat '' + positive payout as one.
    if (($type === 'conversion' || $type === '') && $amount > 0) {
      return ['type' => $type !== '' ? $type : 'conversion', 'createsReward' => true, 'sign' => 1];
    }

    // installation / optional (unpaid) / iap / iaa — audit only.
    return ['type' => $type !== '' ? $type : 'other', 'createsReward' => false, 'sign' => 1];
  }
}
