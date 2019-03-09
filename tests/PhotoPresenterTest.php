<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class PhotoPresenterTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	public function testActionNewsDefault()
	{
		$this->checkAction('Photo:News:default');
	}

	public function testActionSignIn()
	{
		$this->checkRedirect('Photo:Sign:in', '/sign/sso');
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
		$this->checkRedirect('Photo:Album:view', '/sign/in', ['slug' => '2-neviditelne-album-akce']);
	}

	public function testActionAlbumEditPublic()
	{
		$this->checkRedirect('Photo:Album:edit', '/sign/in', ['slug' => '1-viditelne-album-akce']);
	}

	public function testActionAlbumEditPrivate()
	{
		$this->checkRedirect('Photo:Album:edit', '/sign/in', ['slug' => '2-neviditelne-album-akce']);
	}

}

(new PhotoPresenterTest())->run();
