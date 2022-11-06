<?php


namespace App\AccountModule\Presenters;

use App\AccountModule\OauthService;
use App\AccountModule\RedisService;
use App\Model\UserService;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Http\Url;
use Nette\Utils\ArrayHash;
use Nette\Utils\Json;
use Nette\Utils\Strings;

final class OauthPresenter extends BasePresenter
{
	/** @var OauthService @inject */
	public $oauthService;

	/** @var RedisService @inject */
	public $redisService;

	/** @var UserService @inject */
	public $userService;

	/**
	 * @param string $response_type
	 * @param string $access_type
	 * @param string $client_id
	 * @param string $redirect_uri
	 * @param string|null $scope
	 * @param string|null $state
	 * @throws BadRequestException
	 */
	public function actionAuthorize(string $response_type, string $client_id, string $redirect_uri, string $scope = null, string $state = null, string $access_type = null)
	{
		if ($response_type !== 'code') {
			throw new ForbiddenRequestException('Response type should be "code"');
		}

		try {
			$this->oauthService->verifyClient($client_id, $redirect_uri);
		} catch (\Exception $e) {
			throw new ForbiddenRequestException($e->getMessage());
		}

		if ($this->user->isLoggedIn()) {
			$code = $this->redisService->createAndStoreAuthorizationCode($client_id, [
				'user' => $this->user->id,
				'mail' => $this->user->identity->mail
			]);

			$url = new Url($redirect_uri);
			$url->setQueryParameter('code', $code);
			$url->setQueryParameter('state', $state);
			$this->redirectUrl($url);
		} else {
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}
	}

	/**
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function actionToken()
	{
		$request = $this->getHttpRequest();
		$header = $request->getHeader('Content-Type');

		if ($header == 'application/json') {
			$body = Json::decode($request->getRawBody());
		} else {
			$body = ArrayHash::from($request->getPost());
		}

		if ($body->grant_type !== 'authorization_code') {
			throw new ForbiddenRequestException('Grant type do not match');
		}

		try {
			$this->oauthService->verifyClientSecret($body->client_id, $body->client_secret);
		} catch (\Exception $e) {
			throw new ForbiddenRequestException($e->getMessage());
		}

		if (!($data = $this->redisService->getUserDataFromAuthorizationCode($body->client_id, $body->code))) {
			throw new ForbiddenRequestException('Authorization code not found or expired');
		}

		$data['expires_in'] = $this->session->getOptions()['cookie_lifetime'];

		$accessToken = $this->redisService->createAndStoreAccessToken($data);

		$this->sendJson([
			'access_token' => $accessToken,
			'issued_at' => time(),
			'expires_in' => $data['expires_in'],
			'account_name' => $data['mail'],
		]);
	}

	/**
	 * @throws ForbiddenRequestException
	 */
	public function actionMe()
	{
		$request = $this->getHttpRequest();
		if (!($token = $request->getHeader('Authorization'))) {
			throw new ForbiddenRequestException('Access token missing');
		}

		$token = explode(' ', $token);
		if (count($token) != 2) {
			throw new ForbiddenRequestException('Access token auth error');
		}

		if (Strings::lower($token[0]) != 'bearer') {
			throw new ForbiddenRequestException('Access token auth error');
		}
		$token = $token[1];

		if (!($data = $this->redisService->getUserDataFromAccessToken($token))) {
			throw new ForbiddenRequestException('Access token code not found or expired');
		}

		$user = $this->userService->getUserById($data['user']);

		$this->sendJson([
			'sub'=> $user->id,
			'name'=> UserService::getFullName($user),
			'first_name'=> $user->name,
			'last_name'=> $user->surname,
			'picture'=> 'https://account.vzs-jablonec.cz/img/photos/' . $user->photo,
			'user_email' => $user->mail,
		]);
	}

}