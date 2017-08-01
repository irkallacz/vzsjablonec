<?php

namespace App\Model;

use Nette\Object;
use Nette\Security;
use Tracy\Debugger;


/**
 * Users authenticator.
 */
class Authenticator extends Object implements Security\IAuthenticator {

	/**@var MemberService */
	private $memberService;

	/**
	 * Authenticator constructor.
	 * @param MemberService $memberService
	 */
	public function __construct(MemberService $memberService) {
		$this->memberService = $memberService;
	}

	/**
	 * Performs an authentication
	 * @param  array
	 * @return Security\Identity
	 * @throws Security\AuthenticationException
	 */
	public function authenticate(array $credentials) {
		list($email, $password) = $credentials;

		$user = $this->memberService->getMemberByLogin($email);

		if (!$user) {
			throw new Security\AuthenticationException("Uživatel s e-mailem '$email' nenalezen.", self::IDENTITY_NOT_FOUND);
		}

		if (!Security\Passwords::verify($password, $user->hash)) {
			throw new Security\AuthenticationException("Nesprávné heslo.", self::INVALID_CREDENTIAL);
		}

		$roleList = $this->memberService->getRoleList();
		$rights = array_slice($roleList, 0, $user->role + 1);
		$rights = array_merge($rights, array_values($this->memberService->getRightsByUserId($user->id)));

		$data = $user->toArray();

		return new Security\Identity($user->id, $rights, $data);
	}
}
