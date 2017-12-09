<?php

namespace App\Authenticator;

use Nette\Security\IAuthenticator;
use Nette\Security\AuthenticationException;
use Nette\Security\Identity;
use Nette\Utils\Json;


/**
 * Users authenticator by SSO login code and signature
 */
class SsoAuthenticator extends BaseAuthenticator {

	/**
	 * Performs an authentication
	 * @param  int $userId
	 * @param  string $code
	 * @param  string $signature
	 * @throws AuthenticationException
	 */
	public function login($userId, $code, $signature){
		$user = $this->userService->getUserById($userId);
		if (!$user) {
			throw new AuthenticationException('Uživatel nenalezen.', IAuthenticator::IDENTITY_NOT_FOUND);
		}

		if ($signature !== $this->getSignature($user, $code)) {
			throw new AuthenticationException('Chyba při přihlášení.', IAuthenticator::INVALID_CREDENTIAL);
		}

		$rights = $this->userService->getRightsForUser($user);
		$data = $user->toArray();

		$this->user->login(new Identity($user->id, $rights, $data));
	}

	public function getSignature($user, $code){
		return hash_hmac('sha256', Json::encode([
			'id' => $user->id,
			'mail' => $user->mail,
			'code' => $code,
		]), $user->hash);
	}

}
