<?php

/*
|--------------------------------------------------------------------------
| Simple Reward Offerwall — REST API (v1)
|--------------------------------------------------------------------------
|
| Served at /wp-json/simple-ro/v1/... (the folder path api/simple-ro/v1 is the
| REST "vendor"/namespace — see WPBones RestProvider).
|
| DSL: Route::get|post|put|patch|delete($path, $callback, $options)
|   - $callback : a closure, or 'SimpleRO\API\SomeController@method'
|   - $options  : merged over ['permission_callback' => '__return_true']
|
| SECURITY: the default permission_callback is __return_true (public). EVERY
| non-public route MUST set an explicit permission_callback. Once the auth
| Guard lands (Phase 1) protected routes will use SimpleRO\API\Auth\Guard::role(...).
|
| The route surface is built out per phase; this file starts with a health
| check that proves the namespace is registered.
|
*/

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\WPBones\Routing\API\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

// Health check — GET /wp-json/simple-ro/v1/health
Route::get('/health', function () {
  return Route::response([
    'ok'      => true,
    'plugin'  => 'simple-reward-offerwall',
    'version' => SimpleRO()->Version,
    'api'     => 'simple-ro/v1',
  ]);
});

/*
|--------------------------------------------------------------------------
| Auth (public — the endpoints that bootstrap a session)
|--------------------------------------------------------------------------
| /auth/me self-reports auth state (200 with user, or 401). /auth/session
| (logout) requires an authenticated session + CSRF header, enforced by Guard.
*/
Route::post('/auth/register', 'SimpleRO\API\AuthController@register');
Route::post('/auth/login', 'SimpleRO\API\AuthController@login');
Route::post('/auth/forgot', 'SimpleRO\API\AuthController@forgot');
Route::post('/auth/reset', 'SimpleRO\API\AuthController@reset');
Route::get('/auth/me', 'SimpleRO\API\AuthController@me');
Route::delete('/auth/session', 'SimpleRO\API\AuthController@logout', [
  'permission_callback' => Guard::authenticated(),
]);
