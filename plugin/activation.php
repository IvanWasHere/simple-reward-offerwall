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
| database/migrations/*.php are included by the framework). We use it to make
| sure the three front-end SPA pages exist, each hosting its shortcode.
|
*/

$simple_ro_pages = [
  'user'    => ['slug' => SimpleRO()->config('custom.pages.user', 'dashboard'), 'title' => 'Offerwall Dashboard', 'shortcode' => '[simple_ro_user_app]'],
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
