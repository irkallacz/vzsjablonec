<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class PhotoPresenterMemberTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	/**
	 *
	 */
	public function setUp()
	{
		$this->logIn(2, ['user', 'member'], ['date_last' => new \Nette\Utils\DateTime('- 1 day')]);
	}

	public function testActionAlbumEditPublicMine()
	{
		$this->checkAction('Photo:Album:edit', ['slug' => '1-viditelne-album-akce']);
	}

	public function testActionAlbumEditPrivateMine()
	{
		$this->checkAction('Photo:Album:edit', ['slug' => '2-neviditelne-album-akce']);
	}

	public function testActionAlbumEditPrivateYours()
	{
		try {
			$this->checkAction('Photo:Album:edit', ['slug' => '3-neviditelne-album-akce']);
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionMyselfDefault()
	{
		$this->checkAction('Photo:Myself:default');
	}

	public function testSignalAddAlbum()
	{
		$this->checkAction('Photo:Myself:default', ['do' => 'addAlbum']);
	}
}

(new PhotoPresenterMemberTest())->run();
