<?php

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| Plugin Menus routes
|--------------------------------------------------------------------------
|
| Here is where you can register all the menu routes for a plugin.
| In this context, the route are the menu link.
|
*/

return [
  'simple_reward_offerwall_slug_menu' => [
    "page_title" => "ReactJS",
    "menu_title" => "ReactJS",
    'capability' => 'read',
    'icon' => 'wpbones-logo-menu.png',
    'items' => [
      [
        "page_title" => __('Main View', 'simple-reward-offerwall'),
        "menu_title" => __('Main View', 'simple-reward-offerwall'),
        'capability' => 'read',
        'route' => [
          'get' => 'Dashboard\DashboardController@index'
        ],
      ],
    ]
  ]
];
