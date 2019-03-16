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

	public function testActionAkceEditException()
	{
		try {
			$this->checkAction('Member:Akce:edit', ['id' => 4]);
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionUserViewUser()
	{
		try {
			$this->checkAction('Member:User:view', ['id' => 1]);
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
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
		$this->checkAction('Member:User:edit', ['id' => 2]);
	}

	public function testActionUserEditUser()
	{
		try {
			$this->checkAction('Member:User:edit', ['id' => 1]);
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
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

	public function testActionForumTopicEdit()
	{
		try {
			$this->checkAction('Member:Forum:edit', ['id' => 1]);
		} catch (\Exception $exception) {
			\Tester\Assert::type('\Nette\Application\BadRequestException', $exception);
		}
	}

	public function testActionForumTopicDelete()
	{
		try {
			$this->checkAction('Member:Forum:delete', ['id' => 1]);
		} catch (\Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
	}

	public function testActionForumPostDelete()
	{
		$this->checkRedirect('Member:Forum:delete', '/forum/topic/1', ['id' => 5]);
	}

	public function testActionForumTopicUnlock()
	{
		$this->checkRedirect('Member:Forum:lockTopic', '/forum/topic/2', ['id' => 2, 'lock' => 0]);
	}

	public function testActionMailDefault()
	{
		$this->checkAction('Member:Mail:default');
	}

	public function testActionMailView()
	{
		$this->checkRedirect('Member:Mail:view', NULL, ['id' => 1]);
	}

	public function testActionMailAdd()
	{
		try {
			$this->checkAction('Member:Mail:add');
		} catch (Exception $exception) {
			\Tester\Assert::type(\Nette\Application\ForbiddenRequestException::class, $exception);
		}
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
