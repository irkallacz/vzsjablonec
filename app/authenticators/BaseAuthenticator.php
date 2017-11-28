<?php

namespace App\Authenticator;

use App\Model\UserService;
use Nette\Object;
use Nette\Security;


/**
 * Users authenticator .
 */
abstract class BaseAuthenticator extends Object {

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
