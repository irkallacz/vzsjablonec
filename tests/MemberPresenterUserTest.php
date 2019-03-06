<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
class MemberPresenterUserTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function setUp()
	{
		$this->logIn(1, ['user'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionNewsDefault()
	{
		$this->checkAction('Member:News:default');
	}

	public function testActionAkceDefault()
	{
		$this->checkAction('Member:Akce:default');
	}

	public function testActionAkceDefaultAll()
	{
		$this->checkAction('Member:Akce:default', ['year' => INF]);
	}

	public function testActionAkceView()
	{
		$this->checkAction('Member:Akce:view', ['id' => 1]);
	}

	public function testActionAkceAdd()
	{
		try{
			$this->checkAction('Member:Akce:add');
		} catch (\Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

	public function testActionAkceEdit()
	{
		try {
			$this->checkAction('Member:Akce:edit', ['id' => 1]);
		} catch (\Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

	public function testActionForumDefault()
	{
		$this->checkAction('Member:Forum:default');
	}
}

(new MemberPresenterUserTest())->run();
