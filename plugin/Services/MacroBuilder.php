<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * MacroBuilder — substitutes macro tokens in a provider URL template.
 *
 * Two mechanisms:
 *  - substitute(): direct canonical tokens, e.g. {user_id} in the URL is replaced
 *    with $values['user_id']. This is what admins use ("…&sid={user_id}").
 *  - build(): a legacy indirection where $macros maps a token to a source key,
 *    e.g. {"{macro_user_id}":"user_id"}, resolved against $context.
 *
 * All values are rawurlencoded. Missing sources resolve to an empty string.
 * Secrets must never be placed in the values/context for client-visible URLs.
 */
class MacroBuilder
{
  /**
   * Replace inline {key} tokens with rawurlencoded $values[key]. Tokens with no
   * matching value are left untouched (so unrelated braces survive).
   */
  public static function substitute(string $template, array $values): string
  {
    $search = [];
    $replace = [];
    foreach ($values as $key => $value) {
      $search[] = '{' . $key . '}';
      $replace[] = rawurlencode((string) $value);
    }

    return $search ? str_replace($search, $replace, $template) : $template;
  }

  public static function build(string $template, array $macros, array $context): string
  {
    if (!$macros) {
      return $template;
    }

    $search = [];
    $replace = [];
    foreach ($macros as $token => $sourceKey) {
      $search[] = (string) $token;
      $replace[] = rawurlencode((string) ($context[$sourceKey] ?? ''));
    }

    return str_replace($search, $replace, $template);
  }
}
