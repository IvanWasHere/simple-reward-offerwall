<?php

if (!defined('ABSPATH')) {
    exit();
}

/*
|--------------------------------------------------------------------------
| Plugin activation
|--------------------------------------------------------------------------
|
| Runs on activation (after config/options delta and BEFORE the migrations in
| database/migrations/*.php are included by the framework). We use it to:
|   1. make sure the staff SPA pages exist, each hosting its shortcode, and
|   2. register + flush the /reward rewrite rule for the user SPA takeover
|      (SpaRouteServiceProvider serves it; the rule must exist at flush time).
|
| The user + admin apps are NOT pages — they take over the WordPress template at
| /reward and /offerwall-admin. Only the support app is still shortcode-hosted.
|
*/

$simplerewardoffer_pages = [
  'support' => ['slug' => SimpleRewardOffer()->config('custom.pages.support', 'offerwall-support'), 'title' => 'Offerwall Support', 'shortcode' => '[simplerewardoffer_support_app]'],
];

foreach ($simplerewardoffer_pages as $simplerewardoffer_page) {
  $existing = get_page_by_path($simplerewardoffer_page['slug'], OBJECT, 'page');
  if ($existing) {
    continue;
  }

  wp_insert_post([
    'post_title'   => $simplerewardoffer_page['title'],
    'post_name'    => $simplerewardoffer_page['slug'],
    'post_content' => $simplerewardoffer_page['shortcode'],
    'post_status'  => 'publish',
    'post_type'    => 'page',
  ]);
}

// User + admin SPA takeovers: register the rewrite rules, then flush so the slugs resolve.
$simplerewardoffer_spa_slugs = [
  'user'  => SimpleRewardOffer()->config('custom.reward_slug', 'reward'),
  'admin' => SimpleRewardOffer()->config('custom.admin_slug', 'offerwall-admin'),
];
foreach ($simplerewardoffer_spa_slugs as $simplerewardoffer_spa_key => $simplerewardoffer_spa_slug) {
  add_rewrite_rule('^' . $simplerewardoffer_spa_slug . '(?:/.*)?/?$', 'index.php?simplerewardoffer_spa=' . $simplerewardoffer_spa_key, 'top');
}
flush_rewrite_rules();
