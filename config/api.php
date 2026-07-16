<?php

if (!defined('ABSPATH')) {
    exit();
}

/*
|--------------------------------------------------------------------------
| WordPress REST API configuration
|--------------------------------------------------------------------------
|
| Here is where you can set up the WordPress REST API features.
|
*/

return [

    // embed wp-json-server
    'wp' => [
        // We run our OWN token-based auth (see SimpleRO\API\Auth\Guard); do not
        // force WordPress authentication on every REST route.
        'require_authentication' => false, // will affect all routes.
    ],

    // your custom rest api — served at /wp-json/simple-ro/v1/... from api/simple-ro/v1/routes.php
    'custom' => [
        'path' => '/api',
        'enabled' => true,
    ],

    // authentication
    'auth' => [
        // Disable the framework's HTTP Basic auth handler — our Guard validates
        // opaque session cookies + CSRF per route via permission_callback.
        'basic' => false
    ]
];
