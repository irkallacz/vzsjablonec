<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterBoardTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function setUp()
	{
		$this->logIn(3, ['user', 'member', 'board'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionUserTable()
	{
		$this->checkAction('Member:User:table');
	}

	public function testActionUserAdd()
	{
		$this->checkAction('Member:User:add');
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
		$this->checkAction('Member:User:view', ['id' => 0]);
	}

	public function testActionUserEditSelf()
	{
		$this->checkAction('Member:User:edit', ['id' => 3]);
	}


}

(new MemberPresenterBoardTest())->run();
