<?php

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\TinyMde;
use App\Model\HlasovaniService;
use App\Model\MessageService;
use App\Model\UserService;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

/** @allow(member) */
class HlasovaniPresenter extends LayerPresenter {

	/** @var HlasovaniService @inject */
	public $hlasovani;

	/** @var MessageService @inject */
	public $messageService;

	/** @var UserService @inject */
	public $userService;

	/**
	 *
	 */
	public function renderDefault() {
		$ankety = $this->hlasovani->getAnkety();
		if (!$this->user->isInRole('board')) $ankety->where('date_deatline < NOW() OR locked = ?', 1);

		$this->template->ankety = $ankety;
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderView(int $id) {
		/** @var ActiveRow $anketa*/
		$anketa = $this->hlasovani->getAnketaById($id);

		if (!$anketa) {
			throw new BadRequestException('Hlasování nenalezeno!');
		}

		$locked = $anketa->locked;
		if ($anketa->date_deatline < date_create()) $locked = 1;


		if ((!$locked) and (!$this->getUser()->isInRole('board'))) {
			throw new ForbiddenRequestException('Nemáte právo prohlížet toto hlasování!');
		}

		$this->template->anketa = $anketa;
		$this->template->locked = $locked;
		$this->template->items = $anketa->related('hlasovani_odpoved')->order('text');

		$members = $this->hlasovani->getMembersByAnketaId($id)->order(':hlasovani_member.date_add');

		$this->template->members = $members;//$members->fetchPairs('id','jmeno');

		$this->template->celkem = count($members);

		$memberList = $members->fetchPairs('id', 'hlasovani_odpoved_id');
		$this->template->memberList = $memberList;
		$this->template->isLogged = Arrays::get($memberList, $this->getUser()->getId(), 0);

		$this->template->title = $anketa->title;
	}

	/**
	 *
	 */
	public function renderAdd() {
		$form = $this['anketaForm'];
		$form['users'][0]['text']->setValue('Jsem pro');
		$form['users'][1]['text']->setValue('Jsem proti');
		$form['users'][2]['text']->setValue('Zdržuji se hlasovaní');

		$this->setView('edit');
		$this->template->nova = TRUE;
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderEdit(int $id) {
		$this->template->nova = FALSE;

		$form = $this['anketaForm'];
		if (!$form->isSubmitted()) {
			$anketa = $this->hlasovani->getAnketaById($id);

			if (!$anketa) {
				throw new BadRequestException('Hlasování nenalezeno!');
			}

			if (!$this->getUser()->isInRole('board')) {
				throw new ForbiddenRequestException('Nemáte právo editovat toto hlasování!');
			}

			if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
				throw new ForbiddenRequestException('Nemáte právo editovat toto hlasování!');
			}

			$odpovedi = $this->hlasovani->getOdpovediByAnketaId($id);

			$form['pocet']->setDefaultValue(count($odpovedi));
			$form->setDefaults($anketa);
			$form['users']->setValues($odpovedi);

			$this->template->title = ucfirst($anketa->title);
		}
	}

	/**
	 * @param int $odpoved
	 * @throws BadRequestException
	 */
	public function handleVote(int $odpoved) {
		$id = (int) $this->getParameter('id');

		$anketa = $this->hlasovani->getAnketaById($id);

		if ((!$anketa) or ($anketa->locked) or (!$this->user->isInRole('board'))) {
			throw new BadRequestException('V tomto hlasovaní nemůžete hlasovat');
		}

		$odpovedi = $anketa->related('hlasovani_odpoved')->fetchPairs('id', 'id');

		if (!in_array($odpoved, $odpovedi)) {
			throw new BadRequestException('Pro tuto odpověď nemůžete hlasovat');
		}

		$values = [
			'user_id' => $this->getUser()->getId(),
			'hlasovani_id' => $id,
			'hlasovani_odpoved_id' => $odpoved,
			'date_add' => new DateTime()
		];

		$this->hlasovani->addVote($values);
		$this->redirect('view', $id);
	}

	/**
	 * @param int $id
	 * @throws ForbiddenRequestException
	 */
	public function actionDelete(int $id) {
		$anketa = $this->hlasovani->getAnketaById($id);

		if (!$this->user->isInRole('board')) {
			throw new ForbiddenRequestException('Nemáte právo smazat toto hlasování!');
		}

		if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		$this->hlasovani->deleteAnketaById($id);

		$this->redirect('default');
	}

	/**
	 * @param int $id
	 * @param bool $lock
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionLock(int $id, bool $lock) {
		$anketa = $this->hlasovani->getAnketaById($id);

		if (!$this->user->isInRole('board')) {
			throw new ForbiddenRequestException('Nemáte právo měnit toto hlasování!');
		}

		if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		$anketa->update(['locked' => $lock, 'date_update' => new Datetime]);
		$this->redirect('view', $id);
	}

	/**
	 * @return Form
	 */
	protected function createComponentAnketaForm() {
		$form = new Form;

		$form->addText('title', 'Název', 30)
			->addFilter([Strings::class, 'firstUpper'])
			->addFilter([$this, 'removeEmoji'])
			->setRequired('Vyplňte název')
			->setAttribute('spellcheck', 'true');

		$form->addComponent((new TinyMde( 'Otázka'))
			->setRequired('Vyplňte prosím text ankety')
			->addFilter([Strings::class, 'firstUpper'])
			->setHtmlAttribute('cols', 60)
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'editor'), 'text');

		$form['date_deatline'] = new \DateInput('Konec hlasování');
		$form['date_deatline']->setRequired('Vyplňte datum konce hlasování')
			->setDefaultValue(new DateTime());

		$users = $form->addMultiplier('users', function (\Nette\Forms\Container $user) {
			$user->addText('text', 'Odpověď', 30);
			$user->addHidden('id');
		}, 3);

		//$users->addCreateButton('Přidat odpovědi'); // metodu vytváří replicator

		$form->addHidden('pocet', 0);

		$form->addSubmit('save', 'Uložit')
			->onClick[] = [$this, 'addAnketaFormSubmitted'];

		$id = $this->getParameter('id');

		if ($id) {
			$odpovedi = $this->hlasovani->getOdpovediByAnketaId($id)->fetchPairs('id');
			$form->setDefaults(['users' => $odpovedi, 'pocet' => count($odpovedi)]);
		}


		return $form;
	}

	/**
	 *
	 * @throws AbortException
	 */
	public function addAnketaFormSubmitted() {
		$id = (int)$this->getParameter('id');

		$values = $this['anketaForm']->getValues();
		$datum = new Datetime();

		$values->date_update = $datum;
		$pocet = $values->pocet;
		unset($values->pocet);
		$odpovedi = $values->users;
		unset($values->users);

		if ($id) {
			$this->hlasovani->getAnketaById($id)->update($values);
			$anketa_id = $id;

			$this->flashMessage('Hlasování bylo aktualizováno');
		} else {
			$values->user_id = $this->getUser()->getId();
			$values->date_add = $datum;

			$anketa = $this->hlasovani->addAnketa($values);
			$anketa_id = $anketa->id;

			$this->addHlasovaniMail($anketa, $odpovedi);
			$this->flashMessage('Nové hlasovaní bylo v pořádku vytvořeno');
		}

		if ($pocet != count($odpovedi)) {
			if ($id) {
				$this->hlasovani->deleteVotesByAnketaId($id);
				$this->hlasovani->deleteOdpovediByAnketaId($id);
			}

			foreach ($odpovedi as $odpoved) {
				$array = ['hlasovani_id' => $anketa_id, 'text' => $odpoved->text];
				$this->hlasovani->addOdpoved($array);
			}
		} else {
			foreach ($odpovedi as $odpoved) {
				$this->hlasovani->getOdpovedById($odpoved->id)->update(['text' => $odpoved->text]);
			}
		}

		$this->redirect('view', $anketa_id);
	}

	/**
	 * @param $hlasovani
	 * @param $odpovedi
	 */
	protected function addHlasovaniMail($hlasovani, $odpovedi) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/newHlasovani.latte');
		$template->hlasovani = $hlasovani;
		$template->odpovedi = $odpovedi;

		$message = new MessageService\Message(MessageService\Message::VOTE_NEW_TYPE);
		$message->setSubject('Nové hlasování představenstva');
		$message->setText($template);
		$message->setAuthor($this->user->id);
		$message->setRecipients($this->userService->getUsersByRight('board'));
		$message->setParameters(['hlasovani_id' => $hlasovani->id]);

		$this->messageService->addMessage($message);

	}


}