<?php

namespace App\Model;

use Nette\Object;
use	Nette\Security;


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
	public function __construct(MemberService $memberService){
		$this->memberService = $memberService;
	}

	/**
	 * Performs an authentication
	 * @param  array
	 * @return Security\Identity
	 * @throws Security\AuthenticationException
	 */
	public function authenticate(array $credentials){
		list($email, $password) = $credentials;

		$user = $this->memberService->getMemberByLogin($email);

		if (!$user) {
			throw new Security\AuthenticationException("Uživatel s e-mailem '$email' nenalezen.", self::IDENTITY_NOT_FOUND);
		}

		if (!Security\Passwords::verify($password, $user->hash)) {
			throw new Security\AuthenticationException("Nesprávné heslo.", self::INVALID_CREDENTIAL);
		}

		$rights = $this->memberService->getRightsByMemberId($user->id);
		$data = $user->toArray();

		return new Security\Identity($user->id, array_values($rights), $data);
	}
}
