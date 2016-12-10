<?php

use Nette\Object;
use	Nette\Diagnostics\Debugger;
use	Nette\Security as NS;
use Nette\DateTime;


/**
 * Users authenticator.
 */
class Authenticator extends Object implements NS\IAuthenticator{

	/**
	 * @var MemberService
     */
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
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials){
		list($username, $password) = $credentials;

		$user = $this->memberService->getMemberByLogin($username);

		if (!$user) {
			throw new NS\AuthenticationException("Uživatel '$username' nenalezen.", self::IDENTITY_NOT_FOUND);
		}

		if (!NS\Passwords::verify($password, $user->hash)) {
			throw new NS\AuthenticationException("Nesprávné heslo.", self::INVALID_CREDENTIAL);
		}

		$rights = $this->memberService->getRightsByMemberId($user->id);
		$data = $user->toArray();

		return new NS\Identity($user->id, array_values($rights), $data);
	}
}
