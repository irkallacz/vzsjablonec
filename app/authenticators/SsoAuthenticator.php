<?php

namespace App\Authenticator;

use App\Model\UserService;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Session;
use Nette\Security\IAuthenticator;
use Nette\Security\AuthenticationException;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Nette\Security\IUserStorage;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\Random;


/**
 * Users authenticator by SSO login code and signature
 */
class SsoAuthenticator extends BaseAuthenticator {

	const SESSION_SECTION = 'login';

	/** @var string */
	private $secret;

	/** @var Session */
	private $session;

	/**
	 * Authenticator constructor.
	 * @param string $secret
	 * @param UserService $userService
	 * @param User $user
	 * @param Session $session
	 */
	public function __construct(string $secret, UserService $userService, User $user, Session $session) {
		$this->secret = $secret;
		$this->session = $session;
		parent::__construct($userService, $user);
	}

	/**
	 * Performs an authentication
	 * @param int $userId
	 * @param string $code
	 * @param int $timestamp
	 * @param string $signature
	 * @param int $module
	 * @throws AuthenticationException
	 */
	public function login(int $userId, string $code, int $timestamp, string $signature, int $module) {
		$login = $this->session->getSection(self::SESSION_SECTION);

		if ((isset($login->code))and($login->code == $code)) {
			unset($login->code);
		}else {
			throw new AuthenticationException('Neplatný identifikátor přihlášení');
		}

		if (abs(time() - $timestamp) > 60) {
			throw new AuthenticationException('Neplatná časová značka');
		}

		$user = $this->userService->getUserById($userId);
		if (!$user) {
			throw new AuthenticationException('Uživatel nenalezen.', IAuthenticator::IDENTITY_NOT_FOUND);
		}

		if ($signature !== $this->getSignature($user, $code, $timestamp)) {
			throw new AuthenticationException('Chyba při přihlášení.', IAuthenticator::INVALID_CREDENTIAL);
		}

		$rights = $this->userService->getRightsForUser($user);
		$data = $this->userService->getDataForUser($user);

		$dateLast = $this->userService->getLastLoginByUserId($userId, $module);
		$data['date_last'] = $dateLast ? $dateLast : new DateTime();

		$this->user->login(new Identity($userId, $rights, $data));
		$this->user->setExpiration('6 hours', IUserStorage::CLEAR_IDENTITY);

		$this->userService->addModuleLogin($userId, $module);
	}

	/**
	 * @param ActiveRow|IIdentity $user
	 * @param string $code
	 * @param int $timestamp
	 * @return string
	 * @throws \Nette\Utils\JsonException
	 */
	public function getSignature($user, string $code, int $timestamp) {
		return hash_hmac('sha256', Json::encode([
			'id' => $user->id,
			'mail' => $user->mail,
			'code' => $code,
			'timestamp' => $timestamp
		]), $this->secret);
	}

	/**
	 * @return string
	 */
	public function generateCode() {
		$code = Random::generate();
		$login = $this->session->getSection(self::SESSION_SECTION);
		$login->code = $code;
		return $code;
	}


}
