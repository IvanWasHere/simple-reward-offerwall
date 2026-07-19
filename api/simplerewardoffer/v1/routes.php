<?php

/*
|--------------------------------------------------------------------------
| Simple Reward Offerwall — REST API (v1)
|--------------------------------------------------------------------------
|
| Served at /wp-json/simplerewardoffer/v1/... (the folder path api/simplerewardoffer/v1 is the
| REST "vendor"/namespace — see WPBones RestProvider).
|
| DSL: Route::get|post|put|patch|delete($path, $callback, $options)
|   - $callback : a closure, or 'SimpleRewardOffer\API\SomeController@method'
|   - $options  : merged over ['permission_callback' => '__return_true']
|
| SECURITY: the default permission_callback is __return_true (public). EVERY
| non-public route MUST set an explicit permission_callback. Once the auth
| Guard lands (Phase 1) protected routes will use SimpleRewardOffer\API\Auth\Guard::role(...).
|
| The route surface is built out per phase; this file starts with a health
| check that proves the namespace is registered.
|
*/

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\WPBones\Routing\API\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

// Health check — GET /wp-json/simplerewardoffer/v1/health
Route::get('/health', function () {
  return Route::response([
    'ok'      => true,
    'plugin'  => 'simple-reward-offerwall',
    'version' => SimpleRewardOffer()->Version,
    'api'     => 'simplerewardoffer/v1',
  ]);
});

/*
|--------------------------------------------------------------------------
| Auth (public — the endpoints that bootstrap a session)
|--------------------------------------------------------------------------
| /auth/me self-reports auth state (always 200: {user} when signed in, {user:null}
| when anonymous — never 4xx, so the SPA's load-time probe logs no console error).
| /auth/session
| (logout) requires an authenticated session + CSRF header, enforced by Guard.
*/
Route::post('/auth/register', 'SimpleRewardOffer\API\AuthController@register');
Route::post('/auth/login', 'SimpleRewardOffer\API\AuthController@login');
Route::post('/auth/forgot', 'SimpleRewardOffer\API\AuthController@forgot');
Route::post('/auth/reset', 'SimpleRewardOffer\API\AuthController@reset');
Route::get('/auth/me', 'SimpleRewardOffer\API\AuthController@me');
Route::delete('/auth/session', 'SimpleRewardOffer\API\AuthController@logout', [
  'permission_callback' => Guard::authenticated(),
]);

/*
|--------------------------------------------------------------------------
| Server-to-server callback (public — signature-authenticated, no cookie)
|--------------------------------------------------------------------------
*/
Route::request(['get', 'post'], '/callback/(?P<hash>[a-zA-Z0-9]+)', 'SimpleRewardOffer\API\CallbackController@handle');

/*
|--------------------------------------------------------------------------
| User (role: user)
|--------------------------------------------------------------------------
*/
$user = ['permission_callback' => Guard::role('user')];

Route::get('/offers', 'SimpleRewardOffer\API\User\OffersController@index', $user);
Route::post('/clicks', 'SimpleRewardOffer\API\User\ClicksController@store', $user);
Route::get('/offerwalls', 'SimpleRewardOffer\API\User\OfferwallsController@index', $user);
Route::get('/offerwalls/(?P<id>\d+)/url', 'SimpleRewardOffer\API\User\OfferwallsController@url', $user);
Route::get('/me/balance', 'SimpleRewardOffer\API\User\AccountController@balance', $user);
Route::get('/me/ledger', 'SimpleRewardOffer\API\User\AccountController@ledger', $user);
Route::get('/me/rewards', 'SimpleRewardOffer\API\User\AccountController@rewards', $user);
Route::get('/me/clicks', 'SimpleRewardOffer\API\User\AccountController@clicks', $user);
Route::put('/me/profile', 'SimpleRewardOffer\API\User\AccountController@updateProfile', $user);
Route::post('/me/fingerprint', 'SimpleRewardOffer\API\User\AccountController@storeFingerprint', $user);
Route::get('/payouts', 'SimpleRewardOffer\API\User\RedemptionsController@catalog', $user);
Route::post('/redemptions', 'SimpleRewardOffer\API\User\RedemptionsController@store', $user);
Route::get('/me/redemptions', 'SimpleRewardOffer\API\User\RedemptionsController@mine', $user);

// Engagement: surveys, daily wheel, leaderboard, bonuses, referral.
Route::get('/surveys', 'SimpleRewardOffer\API\User\SurveysController@index', $user);
Route::get('/wheel', 'SimpleRewardOffer\API\User\WheelController@show', $user);
Route::post('/wheel/spin', 'SimpleRewardOffer\API\User\WheelController@spin', $user);
Route::get('/leaderboard', 'SimpleRewardOffer\API\User\LeaderboardController@index', $user);
Route::get('/bonuses', 'SimpleRewardOffer\API\User\BonusController@index', $user);
Route::post('/bonuses/(?P<key>[a-z0-9_]+)/claim', 'SimpleRewardOffer\API\User\BonusController@claim', $user);
Route::get('/me/referral', 'SimpleRewardOffer\API\User\ReferralController@show', $user);

/*
|--------------------------------------------------------------------------
| Support tickets
|--------------------------------------------------------------------------
| User: manage own tickets. Shared (owner or staff): view + post message.
| Staff (support, admin superset): queue, assign, status, user context.
*/
$authed = ['permission_callback' => Guard::authenticated()];
$support = ['permission_callback' => Guard::role('support')];

Route::get('/support/tickets', 'SimpleRewardOffer\API\SupportController@myTickets', $user);
Route::post('/support/tickets', 'SimpleRewardOffer\API\SupportController@create', $user);
Route::get('/support/tickets/(?P<id>\d+)', 'SimpleRewardOffer\API\SupportController@show', $authed);
Route::post('/support/tickets/(?P<id>\d+)/messages', 'SimpleRewardOffer\API\SupportController@postMessage', $authed);

Route::get('/support/queue', 'SimpleRewardOffer\API\SupportController@queue', $support);
Route::post('/support/tickets/(?P<id>\d+)/assign', 'SimpleRewardOffer\API\SupportController@assign', $support);
Route::put('/support/tickets/(?P<id>\d+)', 'SimpleRewardOffer\API\SupportController@setStatus', $support);
Route::get('/support/users/(?P<id>\d+)', 'SimpleRewardOffer\API\SupportController@userContext', $support);

/*
|--------------------------------------------------------------------------
| Admin (role: admin)
|--------------------------------------------------------------------------
*/
$admin = ['permission_callback' => Guard::role('admin')];

Route::get('/admin/settings', 'SimpleRewardOffer\API\Admin\SettingsController@show', $admin);
Route::put('/admin/settings', 'SimpleRewardOffer\API\Admin\SettingsController@update', $admin);
Route::get('/admin/media', 'SimpleRewardOffer\API\Admin\MediaController@index', $admin);

Route::get('/admin/providers', 'SimpleRewardOffer\API\Admin\ProvidersController@index', $admin);
Route::post('/admin/providers', 'SimpleRewardOffer\API\Admin\ProvidersController@store', $admin);
Route::get('/admin/providers/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\ProvidersController@show', $admin);
Route::put('/admin/providers/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\ProvidersController@update', $admin);
Route::delete('/admin/providers/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\ProvidersController@destroy', $admin);

Route::get('/admin/providers/(?P<id>\d+)/callbacks', 'SimpleRewardOffer\API\Admin\ProviderCallbacksController@index', $admin);
Route::post('/admin/providers/(?P<id>\d+)/callbacks', 'SimpleRewardOffer\API\Admin\ProviderCallbacksController@store', $admin);
Route::put('/admin/providers/(?P<id>\d+)/callbacks/(?P<cbId>\d+)', 'SimpleRewardOffer\API\Admin\ProviderCallbacksController@update', $admin);
Route::delete('/admin/providers/(?P<id>\d+)/callbacks/(?P<cbId>\d+)', 'SimpleRewardOffer\API\Admin\ProviderCallbacksController@destroy', $admin);

Route::get('/admin/offer-schemas', 'SimpleRewardOffer\API\Admin\OfferSchemasController@index', $admin);
Route::post('/admin/providers/(?P<id>\d+)/ingest', 'SimpleRewardOffer\API\Admin\ProvidersController@ingest', $admin);
Route::get('/admin/offers', 'SimpleRewardOffer\API\Admin\OffersController@index', $admin);
Route::put('/admin/offers/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\OffersController@update', $admin);

Route::get('/admin/users', 'SimpleRewardOffer\API\Admin\UsersController@index', $admin);
Route::get('/admin/users/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\UsersController@show', $admin);
Route::get('/admin/users/(?P<id>\d+)/clicks', 'SimpleRewardOffer\API\Admin\UsersController@clicks', $admin);
Route::get('/admin/users/(?P<id>\d+)/fingerprints', 'SimpleRewardOffer\API\Admin\UsersController@fingerprints', $admin);
Route::delete('/admin/users/(?P<id>\d+)/fingerprints/(?P<fpId>\d+)', 'SimpleRewardOffer\API\Admin\UsersController@deleteFingerprint', $admin);
Route::put('/admin/users/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\UsersController@update', $admin);

Route::get('/admin/stats', 'SimpleRewardOffer\API\Admin\StatsController@index', $admin);
Route::get('/admin/callbacks', 'SimpleRewardOffer\API\Admin\CallbacksController@index', $admin);

Route::get('/admin/rewards', 'SimpleRewardOffer\API\Admin\RewardsController@index', $admin);
Route::post('/admin/rewards/(?P<id>\d+)/approve', 'SimpleRewardOffer\API\Admin\RewardsController@approve', $admin);
Route::post('/admin/rewards/(?P<id>\d+)/reject', 'SimpleRewardOffer\API\Admin\RewardsController@reject', $admin);

Route::get('/admin/payouts', 'SimpleRewardOffer\API\Admin\PayoutsController@index', $admin);
Route::post('/admin/payouts', 'SimpleRewardOffer\API\Admin\PayoutsController@store', $admin);
Route::put('/admin/payouts/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\PayoutsController@update', $admin);
Route::delete('/admin/payouts/(?P<id>\d+)', 'SimpleRewardOffer\API\Admin\PayoutsController@destroy', $admin);

Route::get('/admin/redemptions', 'SimpleRewardOffer\API\Admin\RedemptionsController@index', $admin);
Route::post('/admin/redemptions/(?P<id>\d+)/approve', 'SimpleRewardOffer\API\Admin\RedemptionsController@approve', $admin);
Route::post('/admin/redemptions/(?P<id>\d+)/reject', 'SimpleRewardOffer\API\Admin\RedemptionsController@reject', $admin);
