<?php

namespace App\MemberModule\Presenters;

use App\Model\MemberService;
use App\Model\TimesService;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tracy\Debugger;


/**
 * Class TimesPresenter
 * @package App\MemberModule\Presenters
 * @allow(member)
 */
class TimesPresenter extends LayerPresenter {

	/** @var TimesService @inject */
	public $timesService;

	/** @var MemberService @inject */
	public $memberService;

	/** @var array $order @persistent */
	public $order = [];

	/** @var array $where @persistent */
	public $where = [];

	/**
	 *
	 */
	public function renderDefault() {
		$times = $this->timesService->getDefaultTimes();

		if ($this->order) $times->order(join(',', array_keys($this->order))); else $times->order('jmeno');

		if ($this->where) $times->where($this->where);

		$this->template->items = $times;
		$this->template->where = $this->where;
		$this->template->order = $this->order;

		$this->template->columLabels = ['jmeno' => 'Jméno', 'disciplina' => 'Disciplína', 'time' => 'Čas', 'date' => 'Datum', 'text' => 'Poznámka'];
		$this->template->whereValues = ['user_id' => 'jmeno', 'times_disciplina_id' => 'disciplina', 'times.text' => 'text', 'date' => 'date', 'time' => 'time'];
	}


	/**
	 * @param string $key
	 * @param bool $add
	 */
	public function actionSetOrder($key, $add = false) {
		if ($add) $this->order[$key] = true;
		else unset($this->order[$key]);

		$this->redirect('default');
	}

	/**
	 * @param string $key
	 * @param null $value
	 */
	public function actionSetWhere($key, $value = null) {
		if ($value) $this->where[$key] = $value;
		else unset($this->where[$key]);

		$this->redirect('default');
	}

	/** @allow(admin) */
	public function renderCsv() {
		$times = $this->timesService->getDefaultTimes();

		if ($this->order) $times->order(join(',', array_keys($this->order))); else $times->order('jmeno');

		if ($this->where) $times->where($this->where);

		$this->template->items = $times;

		$httpResponse = $this->context->getByType('Nette\Http\Response');
		$httpResponse->setHeader('Content-Disposition', 'attachment; filename="vysledky.csv"');
	}

	/** @allow(board) */
	public function renderAdd() {
		$this->order = [];
		$this->where = [];

		$this->template->nova = TRUE;

		$this->setView('edit');

		$lastTime = $this->getSession('lastTime');

		if (isset($lastTime->values)) {
			$form = $this['timeForm'];
			$lastTime->values->time = substr($lastTime->values->time, 3);
			$form->setDefaults($lastTime->values);
		}

		unset($lastTime->values);
	}


	/**
	 * @param $id
	 * @allow(admin)
	 */
	public function renderEdit($id) {
		$form = $this['timeForm'];
		if (!$form->isSubmitted()) {
			$time = $this->timesService->getTimeById($id);

			if (!$time) {
				throw new BadRequestException('Záznam nenalezen!');
			}

			$form['user_id']->setDefaultValue($time->member_id);
			$form['times_disciplina_id']->setDefaultValue($time->times_disciplina_id);
			$form['date']->setDefaultValue($time->date);
			$form['text']->setDefaultValue($time->text);

			$form['time']->setDefaultValue($time->time->format('%I:%S'));
		}
	}

	/** @allow(admin) */
	public function actionDelete($id) {
		$this->timesService->getTimeById($id)->delete();
		$this->flashMessage('Výsledek by smazán');

		$this->redirect('default');
	}


	/**
	 * @return Form
	 * @allow(admin)
	 */
	protected function createComponentTimeForm() {
		$form = new Form();

		$form->addSelect('user_id', 'Jméno',
			$this->memberService->getMembersArray()
		)
			->setRequired('Vyplňte jméno');

		$form->addSelect('times_disciplina_id', 'Disciplína',
			$this->timesService->getTimesDisciplineArray()
		)
			->setRequired('Vyplňte disciplínu');

		$form['date'] = new \DateInput('Datum');
		$form['date']->setRequired('Vyplňte datum')
			->setDefaultValue(new DateTime());

		$form->addText('time', 'Výsledný čas', 5)
			->setRequired('Vyplňte %label')
			->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků', 5)
			->addRule(Form::PATTERN, 'Čas musí být ve formátu MM:SS', '[0-5]{1}\d{1}:[0-5]{1}\d{1}')
			->setType('time')
			->setDefaultValue('00:00')
			->setAttribute('class', 'time')
			->getLabelPrototype()->class('hint')->title = 'Čas musí být ve formátu MM:SS';

		$form->addText('text', 'Poznámka', 30)
			->setAttribute('spellcheck', 'true');

		$form->addCheckBox('another', 'Vložit další záznam')
			->setDefaultValue(TRUE);

		$form->addSubmit('save', 'Uložit');

		$form->onSuccess[] = [$this, 'timeFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(admin)
	 */
	public function timeFormSubmitted(Form $form) {
		$id = (int)$this->getParameter('id');

		$values = $form->getValues();

		if ($id) {
			unset($values->another);
			$values->time = '00:' . $values->time;

			Debugger::barDump($values);

			$this->timesService->getTimeById($id)->update($values);
			$this->flashMessage('Výsledek byl změněn');
		} else {
			$values->date_add = new Datetime;

			$lastTime = $this->getSession('lastTime');

			if ($values->another) {
				$lastTime->values = $values;
				$another = $values->another;
			} else unset($lastTime->values);

			unset($values->another);
			$values->time = '00:' . $values->time;

			$this->timesService->addTime($values);

			$this->flashMessage('Výsledek byl v pořádku přidán');
		}

		if (isset($another)) $this->redirect('add'); else $this->redirect('default');
	}

}