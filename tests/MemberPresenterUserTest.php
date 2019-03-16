<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterUserTest extends \Tester\TestCase
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
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionAkceEdit()
	{
		try {
			$this->checkAction('Member:Akce:edit', ['id' => 1]);
		} catch (\Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionForumDefault()
	{
		$this->checkAction('Member:Forum:default');
	}

	public function testActionUserDefault()
	{
		$this->checkAction('Member:User:default');
	}

	public function testActionUserViewUser()
	{
		$this->checkAction('Member:User:view', ['id' => 1]);
	}

	public function testActionUserViewMember()
	{
		$this->checkAction('Member:User:view', ['id' => 2]);
	}

	public function testActionUserViewBoard()
	{
		$this->checkAction('Member:User:view', ['id' => 3]);
	}

	public function testActionUserViewAdmin()
	{
		$this->checkAction('Member:User:view', ['id' => 4]);
	}

	public function testActionUserViewDeleted()
	{
		try {
			$this->checkAction('Member:User:view', ['id' => 0]);
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionUserTable()
	{
		try {
			$this->checkAction('Member:User:table');
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionUserEditSelf()
	{
		$this->checkAction('Member:User:edit', ['id' => 1]);
	}

}

(new MemberPresenterUserTest())->run();
