<?php

namespace SimpleRO\Providers\Schemas;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\Providers\Schemas\Contracts\OfferSchema;

/**
 * OfferSchemaRegistry — the built-in list of provider offer schemas. Add a network
 * by adding its OfferSchema class to schemas(). Providers reference a schema by
 * key (ro_providers.offer_schema); '' / unknown means "no schema" (legacy
 * config.field_map path).
 */
class OfferSchemaRegistry
{
  /** @return array<int,OfferSchema> */
  public static function all(): array
  {
    return [
      new AyetStudiosSchema(),
      new LootablySchema(),
    ];
  }

  /** Resolve a schema by key, or null when unset/unknown. */
  public static function for(?string $key): ?OfferSchema
  {
    $key = trim((string) $key);
    if ($key === '') {
      return null;
    }
    foreach (self::all() as $schema) {
      if ($schema->key() === $key) {
        return $schema;
      }
    }
    return null;
  }

  /** True when the key names a registered schema. */
  public static function exists(?string $key): bool
  {
    return self::for($key) !== null;
  }

  /**
   * Compact list for the admin dropdown.
   *
   * @return array<int,array{key:string,label:string}>
   */
  public static function options(): array
  {
    return array_map(
      static fn (OfferSchema $s) => ['key' => $s->key(), 'label' => $s->label()],
      self::all()
    );
  }
}
