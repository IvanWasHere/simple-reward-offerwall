<?php

namespace SimpleRewardOffer\API\User;

if (!defined('ABSPATH')) {
  exit();
}

use SimpleRewardOffer\API\Auth\Guard;
use SimpleRewardOffer\Services\ReferralService;
use SimpleRewardOffer\WPBones\Routing\API\RestController;

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
