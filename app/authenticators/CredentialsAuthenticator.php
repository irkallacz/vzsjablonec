<?php

namespace App\Authenticator;

use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;


/**
 * Users authenticator by credetialis.
 */
class CredentialsAuthenticator extends BaseAuthenticator {

	/**
	 * Performs an authentication
	 * @param  string $email
	 * @param  string $password
	 * @throws AuthenticationException
	 */
	public function login($email, $password) {
		$user = $this->userService->getUserByEmail($email);

		if (!$user) {
			throw new AuthenticationException("Uživatel s e-mailem '$email' nenalezen", IAuthenticator::IDENTITY_NOT_FOUND);
		}

		$attempts = $this->userService->getPasswordAttempts($user->id);

		if (($attempts)and($attempts->date_end)) {
			throw new AuthenticationException('Příliš mnoho špatných pokusů. Další přihlášení bude možné až ' . $attempts->date_end->format('d.m.Y \v H:i'), IAuthenticator::NOT_APPROVED);
		}

		if (!Passwords::verify($password, $user->hash)) {
			if (!$attempts) {
				$this->userService->addPasswordAttempts($user->id);
			} else {
				$date_end = ($attempts->count >= 2) ? new DateTime('+ 20 minute') : NULL;
				$attempts->update([
					'count' => $attempts->count + 1,
					'date_end' => $date_end
				]);
			}

			throw new AuthenticationException('Nesprávné heslo', IAuthenticator::INVALID_CREDENTIAL);
		}

		if ($attempts) $attempts->delete();

		$rights = $this->userService->getRightsForUser($user);

		$this->user->login(new Identity($user->id, $rights, $user->toArray()));
	}
}
