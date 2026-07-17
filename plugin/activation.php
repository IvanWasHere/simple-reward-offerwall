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
| The user app is NOT a page — it takes over the WordPress template at /reward.
|
*/

$simple_ro_pages = [
  'admin'   => ['slug' => SimpleRO()->config('custom.pages.admin', 'offerwall-admin'), 'title' => 'Offerwall Admin', 'shortcode' => '[simple_ro_admin_app]'],
  'support' => ['slug' => SimpleRO()->config('custom.pages.support', 'offerwall-support'), 'title' => 'Offerwall Support', 'shortcode' => '[simple_ro_support_app]'],
];

foreach ($simple_ro_pages as $simple_ro_page) {
  $existing = get_page_by_path($simple_ro_page['slug'], OBJECT, 'page');
  if ($existing) {
    continue;
  }

  wp_insert_post([
    'post_title'   => $simple_ro_page['title'],
    'post_name'    => $simple_ro_page['slug'],
    'post_content' => $simple_ro_page['shortcode'],
    'post_status'  => 'publish',
    'post_type'    => 'page',
  ]);
}

// User SPA takeover: register the rewrite rule, then flush so /reward resolves.
$simple_ro_reward_slug = SimpleRO()->config('custom.reward_slug', 'reward');
add_rewrite_rule('^' . $simple_ro_reward_slug . '(?:/.*)?/?$', 'index.php?simple_ro_spa=user', 'top');
flush_rewrite_rules();
