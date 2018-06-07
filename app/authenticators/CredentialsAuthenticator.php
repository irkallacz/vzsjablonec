<?php

namespace App\Authenticator;

use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;
use Tracy\Debugger;


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

		$attempt = $this->userService->getPasswordAttempt($user->id);

		if (($attempt)and($attempt->date_end)) {
			throw new AuthenticationException('Příliš mnoho špatných pokusů. Další přihlášení bude možné až ' . $attempt->date_end->format('d.m.Y \v H:i'), IAuthenticator::NOT_APPROVED);
		}

		if (!Passwords::verify($password, $user->hash)) {
			if (!$attempt) {
				$this->userService->addPasswordAttempt($user->id);
			} else {
				$date_end = ($attempt->count >= 2) ? new DateTime('+ 20 minute') : NULL;
				$attempt->update([
					'count' => $attempt->count + 1,
					'date_end' => $date_end
				]);
			}

			throw new AuthenticationException('Nesprávné heslo', IAuthenticator::INVALID_CREDENTIAL);
		}

		if ($attempt) $attempt->delete();

		if (Passwords::needsRehash($user->hash)) {
			$user->update(['hash' => Passwords::hash($password)]);
		}

		$rights = $this->userService->getRightsForUser($user);
		$data = $this->userService->getDataForUser($user);

		$this->user->login(new Identity($user->id, $rights, $data));
	}
}
