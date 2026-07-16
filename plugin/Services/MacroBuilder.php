<?php

namespace SimpleRO\Services;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * MacroBuilder — substitutes {macro_*} tokens in a provider URL template.
 *
 * $macros maps a token to a source key, e.g. {"{macro_user_id}":"user_id"}.
 * $context supplies the source values, e.g. ['user_id' => 42, 'adslot_id' => '9'].
 * Values are urlencoded. Missing sources resolve to an empty string. Secrets must
 * never be placed in $context for client-visible URLs.
 */
class MacroBuilder
{
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
