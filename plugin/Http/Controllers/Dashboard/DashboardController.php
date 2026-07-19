<?php

namespace SimpleRewardOffer\Http\Controllers\Dashboard;

use SimpleRewardOffer\Http\Controllers\Controller;

if (!defined('ABSPATH')) {
  exit();
}

class DashboardController extends Controller
{
  public function index()
  {
    return SimpleRewardOffer()
      ->view('dashboard.index')
      ->withAdminStyle('prism')
      ->withAdminScript('prism')
      ->withAdminStyle('simple-reward-offerwall-common')
      ->withAdminAppsScript('app');
  }
}
