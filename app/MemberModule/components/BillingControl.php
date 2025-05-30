<?php
/**
 * Created by PhpStorm.
 * User: jakub
 * Date: 27.7.19
 * Time: 17:31
 */

namespace App\MemberModule\Components;


use App\MemberModule\Presenters\BasePresenter;
use App\Model\BillingService;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;
use WebChemistry\Forms\Controls\Multiplier;

final class BillingControl extends LayerControl {

	/**
	 * @var BillingService
	 */
	private $billingService;

	/**
	 * @var ActiveRow|IRow
	 */
	private $akce;

	/**
	 * @var ActiveRow|IRow
	 */
	private $billing;

	/**
	 * @var int
	 */
	private $userId;

	/**
	 * @var bool
	 */
	private $canEdit;

	/**
	 * @var bool @persistent
	 */
	public $edit = FALSE;

	/**
	 * BillingControl constructor.
	 * @param BillingService $billingService
	 * @param int $akceId
	 * @param int $userId
	 * @param bool $canEdit
	 */
	public function __construct(BillingService $billingService, IRow $akce, int $userId, bool $canEdit) {
		parent::__construct();
		$this->billingService = $billingService;
		$this->akce = $akce;
		$this->userId = $userId;
		$this->canEdit = $canEdit;

		$this->billing = $this->billingService->getBillingByAkceId($this->akce->id);
	}

	public function handleEdit(){
		$this->edit = TRUE;
		$this->redrawControl('component');
	}

	/**
	 * @return Form
	 */
	protected function createComponentBillingForm() {
		$form = new Form();

		$container = function (Container $item) {
			$item->addText('name', 'Název', 20)
				->setRequired('Vyplňte prosím nazev položky')
				->addFilter([Strings::class, 'firstUpper'])
				->addFilter([BasePresenter::class, 'removeEmoji'])
				->setHtmlAttribute('class', 'name')
				->setHtmlAttribute('spellcheck', 'true');

			$item->addText('price', 'Cena', 5)
				->setRequired('Vyplňte cenu položky')
				->setHtmlType('number')
				->setDefaultValue(0)
				->setHtmlAttribute('class', 'currency price')
				->setHtmlAttribute('min', 0)
				->setOption('description', 'Kč')
				->addRule(Form::FLOAT)
				->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 0);

			$item->addText('count', 'Počet')
				->setRequired('Vyplňte počet položek')
				->setHtmlType('number')
				->setHtmlAttribute('class', 'number count')
				->setHtmlAttribute('min', 1)
				->setDefaultValue(1)
				->addRule(Form::INTEGER)
				->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 1);

			$item->addText('final', 'Celkem')
				->setOmitted()
				->setRequired(FALSE)
				->setHtmlType('number')
				->setHtmlAttribute('class', 'currency final')
				->setHtmlAttribute('min', 0)
				->setOption('description', 'Kč')
				->setHtmlAttribute('readonly')
				->setDefaultValue(0)
				->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 0);

			$item->addCheckbox('booked')
				->setHtmlAttribute('class', 'hint')
				->setHtmlAttribute('title', 'Účtováno přes náš spolek nebo placeno z peněz spolku')
				->setDefaultValue(FALSE);

			$item->addText('invoice', 'Číslo faktury', 10)
				->setNullable();

			$item->addHidden('id');

			$item->addSubmit('remove', '✖')
				->setOmitted()
				->setHtmlAttribute('class', 'buttonLike')
				->setHtmlAttribute('title', 'Smazat položku')
				->setValidationScope(FALSE)
				->onClick[] = [$this, 'removeFormRow'];

			$item->addSubmit('add', '+')
				->setOmitted()
				->setHtmlAttribute('class', 'buttonLike')
				->setHtmlAttribute('title', 'Přidat řádek')
				->setValidationScope(FALSE)
				->onClick[] = [$this, 'addFormRow'];
		};

		$incomes = $form->addMultiplier('incomes', $container, 0);
		$incomes->addCreateButton('+', 1, [$this, 'addFormRow']);
		$incomes->addRemoveButton('✖');

		$expenses = $form->addMultiplier('expenses', $container, 0);
		$expenses->addCreateButton('+', 1, [$this, 'addFormRow']);
		$expenses->addRemoveButton('✖');

		$form->addText('income', 'Příjmy')
			->setRequired('Vyplňte příjmy')
			->setHtmlType('number')
			->setHtmlAttribute('class', 'currency')
			->setHtmlId('billing-incomes')
			->setOption('description', 'Kč')
			->setHtmlAttribute('readonly')
			->setHtmlAttribute('min', 0)
			->setDefaultValue(0)
			->addRule(Form::FLOAT)
			->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 0);

		$form->addText('expense', 'Výdaje')
			->setRequired('Vyplňte výdaje')
			->setHtmlType('number')
			->setHtmlAttribute('class', 'currency')
			->setHtmlId('billing-expenses')
			->setOption('description', 'Kč')
			->setHtmlAttribute('readonly')
			->setHtmlAttribute('min', 0)
			->setDefaultValue(0)
			->addRule(Form::FLOAT)
			->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 0)
			->addConditionOn($form['income'], Form::EQUAL, 0)
			->addRule(Form::NOT_EQUAL, 'Vyúčtování nesmí být prázdné', 0);

		$form->addText('final', 'Bilance')
			->setRequired('Vyplňte výslednou bilanci')
			->setHtmlType('number')
			->setHtmlAttribute('class', 'currency')
			->setHtmlId('billing-final')
			->setOption('description', 'Kč')
			->setHtmlAttribute('readonly')
			->setDefaultValue(0)
			->addRule(Form::FLOAT);

		$form->addTextArea('note', 'Poznámka')
			->setNullable();

		$button = $form->addSubmit('save', 'Uložit');

		$button->onClick[] = [$this, 'validateValues'];

		$button->onClick[] = [$this, 'processForm'];

		if ($this->billing) $this->loadValues($form, $this->billing);

		return $form;
	}

	public function removeFormRow(SubmitButton $submitButton) {
		/**@var  Multiplier $multiplier*/
		$multiplier = $submitButton->getParent()->getParent();
		$this->redrawControl('component-edit');
		$multiplier->onRemoveSubmit($submitButton);
	}

	public function addFormRow() {
		$this->redrawControl('component-edit');
	}

	/**
	 * @param SubmitButton $submitButton
	 * @param ArrayHash $values
	 * @throws \Nette\Application\AbortException
	 */
	public function processForm(SubmitButton $submitButton, ArrayHash $values) {
		$now = new DateTime();

		$billingValues = [
			'final' => $values->final,
			'expense' => $values->expense,
			'income' => $values->income,
			'note' => $values->note,
			'date_update' => $now
		];

		if (!$this->billing) {
			$billingValues['date_add'] = $now;
			$billingValues['akce_id'] = $this->akce->id;
			$billingValues['created_by'] = $this->userId;

			$this->billing = $this->billingService->addBilling($billingValues);
			$this->akce->update(['bill' => TRUE]);
		} else {
			$this->billing->update($billingValues);
		}

		$this->billingService->deleteBillingItems($this->billing->id);

		foreach ($values->incomes as $income)
			$this->processItem($income, $this->billing->id);

		foreach ($values->expenses as $expense)
			$this->processItem($expense, $this->billing->id, TRUE);

		$this->edit = FALSE;
		$this->flashMessage('Vyúčtování uloženo');

		if ($this->presenter->isAjax()) {
			$this->redrawControl('component');
		} else {
			$this->presenter->redirect('this');
		}

	}

	/**
	 * @param ArrayHash $item
	 * @param int $billingId
	 * @param bool $negative
	 */
	private function processItem(ArrayHash $item, int $billingId, bool $negative = FALSE) {
		if (!$item->id) unset($item->id);

		$item->billing_id = $billingId;
		$item->negative = $negative;
		$this->billingService->addBillingItem($item);
	}

	/**
	 * @param SubmitButton $submitButton
	 * @param ArrayHash $values
	 */
	public function validateValues(SubmitButton $submitButton, ArrayHash $values) {
		$form = $submitButton->getForm();

		$expense = 0;
		foreach ($values->expenses as $item) {
			if (($item->price)and($item->count)) $expense += $item->price * $item->count;
		}

		$income = 0;
		foreach ($values->incomes as $item) {
			if (($item->price)and($item->count)) $income += $item->price * $item->count;
		}

		if ($income != $values->income)
			$form->addError('Výsledný příjem není součtem jednotlivých položek');

		if ($expense != $values->expense)
			$form->addError('Výsledné výdaje nejsou součtem jednotlivých položek');

		if ((($income - $expense) != ($values->income - $values->expense))or(($income - $expense) != $values->final))
			$form->addError('Výsledek vyúčtování nesedí');
	}

	/**
	 * @param Form $form
	 * @param IRow|ActiveRow $billing
	 */
	private function loadValues(Form &$form, IRow $billing) {
		$billingItems = $this->billingService->getBillingItemsByBillingId($billing->id)->fetchPairs('id');

		$values = [];
		foreach ($billingItems as $item) {
			$category = $item->negative ? 'expenses' : 'incomes';
			$item = $item->toArray();
			$item['final'] = $item['price'] * $item['count'];
			$values[$category][] = $item;
		}

		$form->setDefaults($values);
		$form->setDefaults($billing);
	}

	/**
	 * @throws ForbiddenRequestException
	 */
	public function render() {
		if (($this->edit)and($this->billing)and(!$this->canEdit)) {
			throw new ForbiddenRequestException('Nemáte právo editovat vyúčtování akce');
		}

		$this->template->setFile(__DIR__ . '/BillingControl.latte');
		$this->template->edit = $this->edit;
		$this->template->canEdit = $this->canEdit;
		$this->template->billing = $this->billing;
		$this->template->render();
	}
}