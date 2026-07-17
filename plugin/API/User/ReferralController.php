<?php

namespace SimpleRO\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRO\API\Auth\Guard;
use SimpleRO\Services\ReferralService;
use SimpleRO\WPBones\Routing\API\RestController;

/**
 * ReferralController — the signed-in user's referral code, share URL, and stats.
 */
class ReferralController extends RestController
{
  public function show()
  {
    $user = Guard::user($this->request);
    return $this->response(['referral' => ReferralService::summary((int) $user->id)]);
  }
}
