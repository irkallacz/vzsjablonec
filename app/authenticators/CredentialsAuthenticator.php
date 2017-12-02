<?php

namespace App\Authenticator;

use Nette\Security;


/**
 * Users authenticator by credetialis.
 */
class CredentialsAuthenticator extends BaseAuthenticator {

	/**
	 * Performs an authentication
	 * @param  string $email
	 * @param  string $password
	 * @throws Security\AuthenticationException
	 */
	public function login($email, $password) {
		$user = $this->userService->getUserByLogin($email);

		if (!$user) {
			throw new Security\AuthenticationException("Uživatel s e-mailem '$email' nenalezen.", Security\IAuthenticator::IDENTITY_NOT_FOUND);
		}

		if (!Security\Passwords::verify($password, $user->hash)) {
			throw new Security\AuthenticationException("Nesprávné heslo.", Security\IAuthenticator::INVALID_CREDENTIAL);
		}

		$rights = $this->userService->getRightsForUser($user);
		$data = $user->toArray();

		$this->user->login(new Security\Identity($user->id, $rights, $data));
	}
}
