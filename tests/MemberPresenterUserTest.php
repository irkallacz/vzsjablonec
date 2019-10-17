<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterUserTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;

	/**
	 *
	 */
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

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionAkceAdd()
	{
		$this->checkAction('Member:Akce:add');
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionAkceEdit()
	{
		$this->checkAction('Member:Akce:edit', ['id' => 1]);
	}

	public function testActionForumDefault()
	{
		$this->checkAction('Member:Forum:default');
	}

	public function testActionForumSearch()
	{
		$this->checkAction('Member:Forum:search');
	}

	public function testActionForumTopic()
	{
		$this->checkAction('Member:Forum:topic', ['id' => 1]);
	}

	public function testActionForumPost()
	{
		$this->checkRedirect('Member:Forum:post', '/forum/topic/1#post/1', ['id' => 1]);
	}

	public function testActionForumTopicLocked()
	{
		$this->checkAction('Member:Forum:topic', ['id' => 2]);
	}

	public function testActionForumTopicDeletedPost()
	{
		$this->checkAction('Member:Forum:topic', ['id' => 3]);
	}

	/**
	 * @throws \Nette\Application\BadRequestException
	 */
	public function testActionForumTopicDeleted()
	{
		$this->checkAction('Member:Forum:topic', ['id' => 4]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionForumTopicEdit()
	{
		$this->checkAction('Member:Forum:edit', ['id' => 1]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionForumPostCite()
	{
		$this->checkAction('Member:Forum:cite', ['id' => 1]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionForumPostAdd()
	{
		$this->checkAction('Member:Forum:add', ['id' => 1]);
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

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionUserViewDeleted()
	{
		$this->checkAction('Member:User:view', ['id' => 0]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionUserTable()
	{
		$this->checkAction('Member:User:table');
	}

	public function testActionUserEditSelf()
	{
		$this->checkAction('Member:User:edit', ['id' => 1]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionUserEditMember()
	{
		$this->checkAction('Member:User:edit', ['id' => 2]);
	}

	public function testActionDokumentyDefault()
	{
		$this->checkAction('Member:Dokumenty:default');
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionAnketyDefault()
	{
		$this->checkAction('Member:Ankety:default');
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionMailDefault()
	{
		$this->checkAction('Member:Mail:default');
	}

}

(new MemberPresenterUserTest())->run();
