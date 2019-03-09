<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class PhotoPresenterUserTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function setUp()
	{
		$this->logIn(1, ['user'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionNewsDefault()
	{
		$this->checkAction('Photo:News:default');
	}

	public function testActionSignOut()
	{
		$this->checkRedirect('Photo:Sign:out', '/sign/');
	}

	public function testActionSignDefault()
	{
		$this->checkAction('Photo:Sign:default');
	}

	public function testActionAlbumDefault()
	{
		$this->checkAction('Photo:Album:default');
	}

	public function testActionAlbumViewPublic()
	{
		$this->checkAction('Photo:Album:view', ['slug' => '1-viditelne-album-akce']);
	}

	public function testActionAlbumViewPrivate()
	{
		$this->checkAction('Photo:Album:view', ['slug' => '2-neviditelne-album-akce']);
	}

	public function testActionAlbumEditPublic()
	{
		try {
			$this->checkAction('Photo:Album:edit', ['slug' => '1-viditelne-album-akce']);
		} catch (Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

	public function testActionAlbumEditPrivate()
	{
		try {
			$this->checkAction('Photo:Album:edit', ['slug' => '2-neviditelne-album-akce']);
		} catch (Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

	public function testActionAlbumEditMine()
	{
		try {
			$this->checkAction('Photo:Album:edit', ['slug' => '3-neviditelne-album-akce']);
		} catch (Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

	public function testActionAlbumViewUsers()
	{
		$this->checkAction('Photo:Album:users');
	}

	public function testActionAlbumAddPublic()
	{
		$this->checkAction('Photo:Album:add', ['slug' => '1-viditelne-album-akce']);
	}

	public function testActionAlbumAddPrivate()
	{
		$this->checkAction('Photo:Album:add', ['slug' => '2-neviditelne-album-akce']);
	}

	public function testActionMyselfDefault()
	{
		try {
			$this->checkAction('Photo:Myself:default');
	} catch (Exception $exception) {
			\Tester\Assert::type('\Nette\Application\ForbiddenRequestException', $exception);
		}
	}

}

(new PhotoPresenterUserTest())->run();
