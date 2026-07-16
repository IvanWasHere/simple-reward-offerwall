<?php

namespace SimpleRO\Http\Controllers\Dashboard;

use SimpleRO\Http\Controllers\Controller;

if (!defined('ABSPATH')) {
  exit();
}

class DashboardController extends Controller
{
  public function index()
  {
    return SimpleRO()
      ->view('dashboard.index')
      ->withAdminStyle('prism')
      ->withAdminScript('prism')
      ->withAdminStyle('simple-reward-offerwall-common')
      ->withAdminAppsScript('app');
  }
}
