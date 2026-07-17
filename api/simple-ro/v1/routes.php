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

/*
|--------------------------------------------------------------------------
| Server-to-server callback (public — signature-authenticated, no cookie)
|--------------------------------------------------------------------------
*/
Route::request(['get', 'post'], '/callback/(?P<hash>[a-zA-Z0-9]+)', 'SimpleRO\API\CallbackController@handle');

/*
|--------------------------------------------------------------------------
| User (role: user)
|--------------------------------------------------------------------------
*/
$user = ['permission_callback' => Guard::role('user')];

Route::get('/offers', 'SimpleRO\API\User\OffersController@index', $user);
Route::post('/clicks', 'SimpleRO\API\User\ClicksController@store', $user);
Route::get('/offerwalls', 'SimpleRO\API\User\OfferwallsController@index', $user);
Route::get('/offerwalls/(?P<id>\d+)/url', 'SimpleRO\API\User\OfferwallsController@url', $user);
Route::get('/me/balance', 'SimpleRO\API\User\AccountController@balance', $user);
Route::get('/me/ledger', 'SimpleRO\API\User\AccountController@ledger', $user);
Route::get('/me/rewards', 'SimpleRO\API\User\AccountController@rewards', $user);
Route::get('/payouts', 'SimpleRO\API\User\RedemptionsController@catalog', $user);
Route::post('/redemptions', 'SimpleRO\API\User\RedemptionsController@store', $user);
Route::get('/me/redemptions', 'SimpleRO\API\User\RedemptionsController@mine', $user);

// Engagement: surveys, daily wheel, leaderboard, bonuses, referral.
Route::get('/surveys', 'SimpleRO\API\User\SurveysController@index', $user);
Route::get('/wheel', 'SimpleRO\API\User\WheelController@show', $user);
Route::post('/wheel/spin', 'SimpleRO\API\User\WheelController@spin', $user);
Route::get('/leaderboard', 'SimpleRO\API\User\LeaderboardController@index', $user);
Route::get('/bonuses', 'SimpleRO\API\User\BonusController@index', $user);
Route::post('/bonuses/(?P<key>[a-z0-9_]+)/claim', 'SimpleRO\API\User\BonusController@claim', $user);
Route::get('/me/referral', 'SimpleRO\API\User\ReferralController@show', $user);

/*
|--------------------------------------------------------------------------
| Support tickets
|--------------------------------------------------------------------------
| User: manage own tickets. Shared (owner or staff): view + post message.
| Staff (support, admin superset): queue, assign, status, user context.
*/
$authed = ['permission_callback' => Guard::authenticated()];
$support = ['permission_callback' => Guard::role('support')];

Route::get('/support/tickets', 'SimpleRO\API\SupportController@myTickets', $user);
Route::post('/support/tickets', 'SimpleRO\API\SupportController@create', $user);
Route::get('/support/tickets/(?P<id>\d+)', 'SimpleRO\API\SupportController@show', $authed);
Route::post('/support/tickets/(?P<id>\d+)/messages', 'SimpleRO\API\SupportController@postMessage', $authed);

Route::get('/support/queue', 'SimpleRO\API\SupportController@queue', $support);
Route::post('/support/tickets/(?P<id>\d+)/assign', 'SimpleRO\API\SupportController@assign', $support);
Route::put('/support/tickets/(?P<id>\d+)', 'SimpleRO\API\SupportController@setStatus', $support);
Route::get('/support/users/(?P<id>\d+)', 'SimpleRO\API\SupportController@userContext', $support);

/*
|--------------------------------------------------------------------------
| Admin (role: admin)
|--------------------------------------------------------------------------
*/
$admin = ['permission_callback' => Guard::role('admin')];

Route::get('/admin/settings', 'SimpleRO\API\Admin\SettingsController@show', $admin);
Route::put('/admin/settings', 'SimpleRO\API\Admin\SettingsController@update', $admin);

Route::get('/admin/providers', 'SimpleRO\API\Admin\ProvidersController@index', $admin);
Route::post('/admin/providers', 'SimpleRO\API\Admin\ProvidersController@store', $admin);
Route::get('/admin/providers/(?P<id>\d+)', 'SimpleRO\API\Admin\ProvidersController@show', $admin);
Route::put('/admin/providers/(?P<id>\d+)', 'SimpleRO\API\Admin\ProvidersController@update', $admin);
Route::delete('/admin/providers/(?P<id>\d+)', 'SimpleRO\API\Admin\ProvidersController@destroy', $admin);

Route::get('/admin/providers/(?P<id>\d+)/callbacks', 'SimpleRO\API\Admin\ProviderCallbacksController@index', $admin);
Route::post('/admin/providers/(?P<id>\d+)/callbacks', 'SimpleRO\API\Admin\ProviderCallbacksController@store', $admin);
Route::put('/admin/providers/(?P<id>\d+)/callbacks/(?P<cbId>\d+)', 'SimpleRO\API\Admin\ProviderCallbacksController@update', $admin);
Route::delete('/admin/providers/(?P<id>\d+)/callbacks/(?P<cbId>\d+)', 'SimpleRO\API\Admin\ProviderCallbacksController@destroy', $admin);

Route::post('/admin/providers/(?P<id>\d+)/ingest', 'SimpleRO\API\Admin\ProvidersController@ingest', $admin);
Route::get('/admin/offers', 'SimpleRO\API\Admin\OffersController@index', $admin);
Route::put('/admin/offers/(?P<id>\d+)', 'SimpleRO\API\Admin\OffersController@update', $admin);

Route::get('/admin/users', 'SimpleRO\API\Admin\UsersController@index', $admin);
Route::get('/admin/users/(?P<id>\d+)', 'SimpleRO\API\Admin\UsersController@show', $admin);
Route::put('/admin/users/(?P<id>\d+)', 'SimpleRO\API\Admin\UsersController@update', $admin);

Route::get('/admin/stats', 'SimpleRO\API\Admin\StatsController@index', $admin);
Route::get('/admin/callbacks', 'SimpleRO\API\Admin\CallbacksController@index', $admin);

Route::get('/admin/rewards', 'SimpleRO\API\Admin\RewardsController@index', $admin);
Route::post('/admin/rewards/(?P<id>\d+)/approve', 'SimpleRO\API\Admin\RewardsController@approve', $admin);
Route::post('/admin/rewards/(?P<id>\d+)/reject', 'SimpleRO\API\Admin\RewardsController@reject', $admin);

Route::get('/admin/payouts', 'SimpleRO\API\Admin\PayoutsController@index', $admin);
Route::post('/admin/payouts', 'SimpleRO\API\Admin\PayoutsController@store', $admin);
Route::put('/admin/payouts/(?P<id>\d+)', 'SimpleRO\API\Admin\PayoutsController@update', $admin);
Route::delete('/admin/payouts/(?P<id>\d+)', 'SimpleRO\API\Admin\PayoutsController@destroy', $admin);

Route::get('/admin/redemptions', 'SimpleRO\API\Admin\RedemptionsController@index', $admin);
Route::post('/admin/redemptions/(?P<id>\d+)/approve', 'SimpleRO\API\Admin\RedemptionsController@approve', $admin);
Route::post('/admin/redemptions/(?P<id>\d+)/reject', 'SimpleRO\API\Admin\RedemptionsController@reject', $admin);
