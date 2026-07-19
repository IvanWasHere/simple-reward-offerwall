<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * SignatureVerifier — validates the authenticity of an incoming S2S postback.
 *
 * Algorithms (callback.signature_algo):
 *   - 'hmac_sha256'   : hash_hmac('sha256', canonical, secret)   [recommended]
 *   - 'sha256_concat' : sha256(canonical . secret)              [e.g. Lootably]
 *   - 'md5_concat'    : md5(canonical . secret)                 [legacy networks]
 *   - 'none'          : no verification                         [testing only]
 *
 * Canonical string (callback.signature_source):
 *   - 'ordered_params'    : all params except the signature param, ksort'd, as a
 *                           query string (k=v&k=v). Default.
 *   - 'concat:a,b,c'      : the raw values of params a,b,c concatenated in order
 *                           with no separators (Lootably: userID+ip+revenue+
 *                           currencyReward).
 *
 * Comparison is constant-time (hash_equals).
 */
class SignatureVerifier
{
  public static function verify(object $callback, array $params): bool
  {
    $algo = $callback->signature_algo ?? 'hmac_sha256';

    if ($algo === 'none') {
      return true;
    }

    $sigParam = (string) ($callback->signature_param ?? '');
    $provided = strtolower((string) ($params[$sigParam] ?? ''));
    if ($sigParam === '' || $provided === '') {
      return false;
    }

    $canonical = self::canonical((string) ($callback->signature_source ?? 'ordered_params'), $params, $sigParam);

    switch ($algo) {
      case 'hmac_sha256':
        $expected = hash_hmac('sha256', $canonical, (string) $callback->secret);
        break;
      case 'sha256_concat':
        $expected = hash('sha256', $canonical . (string) $callback->secret);
        break;
      case 'md5_concat':
        $expected = md5($canonical . (string) $callback->secret);
        break;
      default:
        return false;
    }

    return hash_equals($expected, $provided);
  }

  private static function canonical(string $source, array $params, string $sigParam): string
  {
    // 'concat:a,b,c': raw values of the named params, in order, no separators.
    if (strncmp($source, 'concat:', 7) === 0) {
      $out = '';
      foreach (explode(',', substr($source, 7)) as $field) {
        $field = trim($field);
        if ($field !== '') {
          $out .= (string) ($params[$field] ?? '');
        }
      }
      return $out;
    }

    unset($params[$sigParam]);

    // 'ordered_params' (default): deterministic query string of the remaining params.
    ksort($params);

    return http_build_query($params);
  }
}
