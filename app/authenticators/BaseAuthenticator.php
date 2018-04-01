<?php

namespace App\Authenticator;

use App\Model\UserService;
use Nette\Security;
use Nette\SmartObject;

/**
 * Users authenticator .
 */
abstract class BaseAuthenticator {
	use SmartObject;

	/**@var UserService */
	protected $userService;

	/**@var Security\User */
	protected $user;

	/**
	 * Authenticator constructor.
	 * @param UserService $userService
	 * @param Security\User $user
	 */
	public function __construct(UserService $userService, Security\User $user) {
		$this->userService = $userService;
		$this->user = $user;
	}
}
