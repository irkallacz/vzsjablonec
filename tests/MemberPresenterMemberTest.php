<?php //MemberPresenterTest.php

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
final class MemberPresenterMemberTest extends \Tester\TestCase
{

	use \Testbench\TPresenter;
	use \Testbench\TCompiledContainer;

	/**
	 *
	 */
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

	public function testActionAkceDelete()
	{
		$this->checkRedirect('Member:Akce:delete', '/akce/', ['id' => 1]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionAkceEditException()
	{
		$this->checkAction('Member:Akce:edit', ['id' => 4]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
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
		$this->checkAction('Member:User:edit', ['id' => 2]);
	}
	
	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionUserEditUser()
	{
		$this->checkAction('Member:User:edit', ['id' => 1]);
	}

	public function testActionForumPostEdit()
	{
		$this->checkAction('Member:Forum:edit', ['id' => 5]);
	}

	public function testActionForumPostCite()
	{
		$this->checkAction('Member:Forum:cite', ['id' => 1]);
	}

	public function testActionForumPostAdd()
	{
		$this->checkAction('Member:Forum:add', ['id' => 1]);
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
	public function testActionForumTopicDelete()
	{
		$this->checkAction('Member:Forum:delete', ['id' => 1]);
	}

	public function testActionForumPostDelete()
	{
		$this->checkRedirect('Member:Forum:delete', '/forum/topic/1', ['id' => 5]);
	}

	public function testActionForumTopicUnlock()
	{
		$this->checkRedirect('Member:Forum:lockTopic', '/forum/topic/2', ['id' => 2, 'lock' => 0]);
	}

	public function testActionAnketyDefault()
	{
		$this->checkAction('Member:Ankety:default');
	}

	public function testActionAnketyAdd()
	{
		$this->checkAction('Member:Ankety:add');
	}

	public function testActionAnketyView()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 1]);
	}

	public function testActionAnketyVote()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 4, 'anketa-choiceId' => 7, 'do' => 'anketa-vote']);
	}

	/**
	 * @throws \Nette\Application\BadRequestException
	 */
	public function testActionAnketyVoteWrong()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 1, 'anketa-choiceId' => 1, 'do' => 'anketa-vote']);
	}

	public function testActionAnketyVoteDelete()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 1, 'do' => 'anketa-deleteVote']);
	}

	public function testActionAnketyViewLocked()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 2]);
	}

	public function testActionAnketyViewEmpty()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 3]);
	}

	public function testActionAnketyViewNew()
	{
		$this->checkAction('Member:Ankety:view', ['id' => 4]);
	}

	public function testActionAnketyEdit()
	{
		$this->checkAction('Member:Ankety:edit', ['id' => 1]);
	}

	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionAnketyEditOthers()
	{
		$this->checkAction('Member:Ankety:edit', ['id' => 3]);
	}

	public function testActionAnketyLock()
	{
		$this->checkRedirect('Member:Ankety:lock', '/ankety/view/1', ['id' => 1, 'lock' => TRUE]);
	}

	public function testActionAnketyUnlock()
	{
		$this->checkRedirect('Member:Ankety:lock','/ankety/view/2', ['id' => 2, 'lock' => FALSE]);
	}

	public function testActionMailDefault()
	{
		$this->checkAction('Member:Mail:default');
	}

	public function testActionMailView()
	{
		$this->checkRedirect('Member:Mail:view', NULL, ['id' => 1]);
	}
	
	/**
	 * @throws \Nette\Application\ForbiddenRequestException
	 */
	public function testActionMailAdd()
	{
		$this->checkAction('Member:Mail:add');
	}

	public function testActionMailAkce()
	{
		$this->checkAction('Member:Mail:akce', ['id' => 2]);
	}

	public function testActionDokumentyDefault()
	{
		$container = $this->getContainer();
		/** @var \App\Model\DokumentyService $service*/
		$service = $container->getByType('App\Model\DokumentyService');

		$service->addDirectory([
			'id' => $service->driveDir,
			'name' => 'Web',
			'parent' => NULL,
			'webViewLink' => '',
			'level' => 0,
		]);

		$this->checkAction('Member:Dokumenty:default');
	}

}

(new MemberPresenterMemberTest())->run();
