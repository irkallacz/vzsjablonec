<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function testActionNewsDefault()
	{
		$this->checkRedirect('Member:News:default', '/sign/in');
	}

	public function testActionSignIn()
	{
		$this->checkRedirect('Member:Sign:in', '/sign/sso');
	}

	public function testActionSignOut()
	{
		$this->checkRedirect('Member:Sign:out', '/sign/');
	}

	public function testActionSignDefault()
	{
		$this->checkAction('Member:Sign:default');
	}

}

(new MemberPresenterTest())->run();
