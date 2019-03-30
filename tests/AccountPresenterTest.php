<?php //AccountPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class AccountPresenterTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;
	use \Testbench\TCompiledContainer;

	public function testRenderDefault()
	{
		$this->checkAction('Account:Sign:default');
	}

	public function testRenderIn()
	{
		$this->checkAction('Account:Sign:in');
	}

	public function testActionOut()
	{
		$this->checkRedirect('Account:Sign:out', '/sign/in');
	}

	public function testActionGoogleLogin()
	{
		$this->checkRedirect('Account:Sign:googleLogin', '/sign/in', ['code' => \Nette\Utils\Random::generate(8)]);
	}

	public function testActionFacebookLogin()
	{
		$this->checkRedirect('Account:Sign:facebookLogin', '/sign/in');
	}

	public function testActionSsoMember()
	{
		$this->checkRedirect('Account:Sign:sso', '/sign/in', ['code' => \Nette\Utils\Random::generate(8), 'redirect' => ':Member:Sign:ssoLogIn']);
	}

	public function testActionSsoPhoto()
	{
		$this->checkRedirect('Account:Sign:sso', '/sign/in', ['code' => \Nette\Utils\Random::generate(8), 'redirect' => ':Photo:Sign:ssoLogIn']);
	}

	/**
	 * @throws \Nette\Application\BadRequestException
	 */
	public function testActionRestorePasswordWrong()
	{
		$this->checkAction('Account:Sign:restorePassword', ['pubkey' => \Nette\Utils\Random::generate(8)]);
	}

	public function testActionRestorePasswordRight()
	{
		$container = $this->getContainer();
		/** @var \App\Model\UserService $service*/
		$service = $container->getByType('App\Model\UserService');
		$session = $service->addPasswordSession(1);
		$this->checkAction('Account:Sign:restorePassword', ['pubkey' => $session->pubkey]);
	}
}

(new AccountPresenterTest())->run();
