<?php
namespace App\MemberModule\Presenters;

use App\MemberModule\Components\AnketaControl;
use App\Model\AnketyService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use WebLoader;
use Tracy\Debugger;

/** @allow(member) */
class AnketyPresenter extends LayerPresenter {

	/** @var AnketyService @inject */
	public $anketyService;

	/**
	 *
	 */
	public function renderDefault() {
		$ankety = $this->anketyService->getAnkety();
		$this->template->ankety = $ankety;
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 */
	public function renderView(int $id) {
		$anketa = $this->anketyService->getAnketaById($id);

		if (!$anketa) {
			throw new BadRequestException('Anekta nenalezena!');
		}

		$this->template->anketa = $anketa;
		$this->template->members = $anketa->related('anketa_member')->order('date_add');
		$this->template->odpovedi = $anketa->related('anketa_odpoved')->order('text');

		$this->template->title = $anketa->title;
	}

	/**
	 *
	 */
	public function renderAdd() {
		$this->setView('edit');
		$this->template->nova = TRUE;
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderEdit(int $id) {
		$this->template->nova = false;

		/** @var Form $form*/
		$form = $this['anketaForm'];
		if (!$form->isSubmitted()) {

			$anketa = $this->anketyService->getAnketaById($id);

			if (!$anketa) {
				throw new BadRequestException('Anekta nenalezena!');
			}

			if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
				throw new ForbiddenRequestException('Nemáte právo editovat tuto anketu');
			}

			//$odpovedi = $this->anketyService->getOdpovediByAnketaId($id);

			$form->setDefaults($anketa);
			//$form['users']->setValues($odpovedi);

			$this->template->title = ucfirst($anketa->title);
		}
	}

	/**
	 * @param int $odpoved
	 * @throws BadRequestException
	 */
	public function handleVote(int $odpoved) {
		$id = (int) $this->getParameter('id');

		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$anketa) or ($anketa->locked)) {
			throw new BadRequestException('V této anketě nemůžete hlasovat');
		}

		$odpovedi = $anketa->related('anketa_odpoved')->fetchPairs('id', 'id');

		if (!in_array($odpoved, $odpovedi)) {
			throw new BadRequestException('V této anketě nemůžete hlasovat');
		}

		$values = [
			'user_id' => $this->getUser()->getId(),
			'anketa_id' => $id,
			'anketa_odpoved_id' => $odpoved,
			'date_add' => new Datetime
		];

		$this->anketyService->addVote($values);
		$this->redirect('view', $id);
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 */
	public function actionDeleteVote(int $id) {
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$anketa) or ($anketa->locked)) {
			throw new BadRequestException('V této anketě nemůžete zrušit hlas');
		}

		$vote = $this->anketyService->getMemberVote($id, $this->getUser()->getId());

		if (!$vote) {
			throw new BadRequestException('V této anketě nemůžete zrušit hlas');
		}
		$this->anketyService->deleteMemberVote($id, $this->getUser()->getId());

		$this->redirect('Ankety:view', $id);
	}

	/**
	 * @param int $id
	 * @throws ForbiddenRequestException
	 */
	public function actionDelete(int $id) {
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}
		$this->anketyService->deleteAnketaById($id);

		$this->redirect('Ankety:default');
	}

	/**
	 * @param int $id
	 * @param bool $lock
	 * @throws ForbiddenRequestException
	 */
	public function actionLock(int $id, bool $lock) {
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$this->getUser()->isInRole('admin')) and ($anketa->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		$anketa->update(['locked' => $lock]);
		$this->redirect('Ankety:view', $id);
	}

	/**
	 * @return \WebLoader\Nette\JavaScriptLoader
	 */
	public function createComponentTexylaJs() {
		$files = new WebLoader\FileCollection(WWW_DIR . '/texyla/js');
		$files->addFiles(['texyla.js', 'selection.js', 'texy.js', 'buttons.js', 'cs.js', 'dom.js', 'view.js', 'window.js']);
		$files->addFiles(['../plugins/table/table.js']);
		$files->addFiles(['../plugins/color/color.js']);
		$files->addFiles(['../plugins/symbol/symbol.js']);
		$files->addFiles(['../plugins/textTransform/textTransform.js']);
		$files->addFiles([WWW_DIR . '/js/texyla_anketa.js']);


		$compiler = WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
		$compiler->addFileFilter(new JsMinFilter());

		return new WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
	}

	/**
	 * @return Form
	 */
	protected function createComponentAnketaForm() {
		$form = new Form;

		$form->addText('title', 'Název', 30)
			->setRequired('Vyplňte prosím název ankety')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
			->setAttribute('spellcheck', 'true');

		$form->addTextArea('text', 'Otázka', 60)
			->setRequired('Vyplňte prosím text ankety')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
			->setAttribute('spellcheck', 'true');

		$users = $form->addMultiplier('users', function (\Nette\Forms\Container $user) {
			$user->addText('text', 'Odpověď', 30)
				->setRequired('Vyplňte prosím text odpovědi')
				->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
				->setAttribute('spellcheck', 'true');

			$user->addHidden('id');

			$user->addButton('remove', '✖')
				->setAttribute('class', 'buttonLike')
				->setAttribute('title', 'Smazat odpověď')
				->setAttribute('onClick', 'removeRow(this)');

		}, 0);

		$users->addCreateButton('Přidat odpovědi');

		$form->addHidden('pocet', 0);

		$form->addSubmit('save', 'Uložit')
			->onClick[] = [$this, 'addAnketaFormSubmitted'];

		$id = $this->getParameter('id');

		if ($id) {
			$odpovedi = $this->anketyService->getOdpovediByAnketaId($id)->fetchPairs('id');
			$form->setDefaults(['users' => $odpovedi, 'pocet' => count($odpovedi)]);
		}

		return $form;
	}

	/**
	 *
	 */
	public function addAnketaFormSubmitted() {
		$id = (int) $this->getParameter('id');

		/** @var Form $form*/
		$form = $this['anketaForm'];
		$values = $form->getValues();

		$datum = new DateTime();
		$values->date_update = $datum;

		$pocet = $values->pocet;
		unset($values->pocet);
		$odpovedi = $values->users;
		unset($values->users);

		if ($id) {
			$anketa = $this->anketyService->getAnketaById($id);
			$anketa->update($values);
			$anketa_id = $id;
			$this->flashMessage('Anketa byla aktualizována');
		} else {
			$values->user_id = $this->getUser()->getId();
			$values->date_add = $datum;
			$values->locked = FALSE;

			$anketa = $this->anketyService->addAnketa($values);
			$anketa_id = $anketa->id;
			$this->flashMessage('Nová anketa byla v pořádku vytvořena');
		}

		if ($pocet != count($odpovedi)) {
			if ($id) {
				$this->anketyService->deleteVotesByAnketaId($id);
				$this->anketyService->deleteOdpovediByAnketaId($id);
			}

			foreach ($odpovedi as $odpoved) {
				$array = ['anketa_id' => $anketa_id, 'text' => $odpoved->text];
				$this->anketyService->addOdpoved($array);
			}
		} else {
			foreach ($odpovedi as $odpoved) {
				$row = $this->anketyService->getOdpovedById($odpoved->id);
				$row->update(['text' => $odpoved->text]);
			}
		}

		$this->redirect('view', $anketa_id);
	}

	/**
	 * @return AnketaControl
	 */
	public function createComponentAnketa() {
		$id = $this->getParameter('id');
		return new AnketaControl($id, $this->anketyService);
	}

}
    