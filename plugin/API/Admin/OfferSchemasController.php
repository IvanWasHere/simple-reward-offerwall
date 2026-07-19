<?php

namespace SimpleRewardOffer\API\Admin;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\Providers\Schemas\OfferSchemaRegistry;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

/**
 * OfferSchemasController (admin) — read-only list of the built-in offer schemas,
 * so the SPA can render the provider "Offer Schema" dropdown and, per schema, its
 * callback macro docs + a ready-to-paste postback URL template + the default
 * param map for new callbacks. Guarded by Guard::role('admin').
 */
class OfferSchemasController extends RestController
{
  public function index()
  {
    $schemas = array_map(static function ($s) {
      return [
        'key'              => $s->key(),
        'label'            => $s->label(),
        'httpMethod'       => $s->httpMethod(),
        'callbackMacros'   => $s->callbackMacros(),
        'callbackFields'   => $s->callbackFields(),
        'postbackTemplate' => $s->postbackTemplate(),
        'defaultParamMap'  => $s->defaultParamMap(),
        'signatureParam'   => $s->defaultSignatureParam(),
        'signatureAlgo'    => $s->defaultSignatureAlgo(),
        'signatureSource'  => $s->defaultSignatureSource(),
        'allowsUnsigned'   => $s->allowsUnsignedCallbacks(),
      ];
    }, OfferSchemaRegistry::all());

    return $this->response(['schemas' => $schemas]);
  }
}
