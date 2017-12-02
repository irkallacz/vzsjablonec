<?php

namespace App\Authenticator;

use Nette\Security;


/**
 * Users authenticator by email.
 */
class EmailAuthenticator extends BaseAuthenticator {

	/**
	 * Performs an authentication
	 * @param  string $email
	 * @throws Security\AuthenticationException
	 */
	public function login($email) {
		$user = $this->userService->getUserByEmail($email);

		if (!$user) {
			throw new Security\AuthenticationException("Uživatel s e-mailem '$email' nenalezen.", Security\IAuthenticator::IDENTITY_NOT_FOUND);
		}

		$rights = $this->userService->getRightsForUser($user);
		$data = $user->toArray();

		$this->user->login(new Security\Identity($user->id, $rights, $data));
	}
}
