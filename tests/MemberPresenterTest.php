<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
class MemberPresenterTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function testActionNewsDefault()
	{
		$this->checkRedirect('Member:News:default', '/sign/in');
	}
}

(new MemberPresenterTest())->run();
