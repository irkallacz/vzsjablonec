<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterUserFeedTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	/**
	 *
	 */
	public function setUp()
	{
		$this->logIn(1, ['user'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionFeedAkce()
	{
		$this->checkAction('Member:Feed:akce');
	}

	public function testActionFeedForum()
	{
		$this->checkAction('Member:Feed:forum');
	}

	public function testActionFeedAnkety()
	{
		$this->checkAction('Member:Feed:ankety');
	}

	public function testActionFeedHlasovani()
	{
		$this->checkAction('Member:Feed:forum');
	}

	public function testActionFeedAlbums()
	{
		$this->checkAction('Member:Feed:albums');
	}
}

(new MemberPresenterUserFeedTest())->run();
