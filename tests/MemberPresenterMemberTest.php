<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
class MemberPresenterMemberTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function setUp()
	{
		$this->logIn(2, ['user', 'member'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionAkceAdd()
	{
		$this->checkAction('Member:Akce:add');
	}

	public function testActionAkceEdit()
	{
		$this->checkAction('Member:Akce:edit', ['id' => 1]);
	}

	public function testActionAkceEditException()
	{
		try {
			$this->checkAction('Member:Akce:edit', ['id' => 4]);
		} catch (Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

}

(new MemberPresenterMemberTest())->run();
