<?php
/**
 * Created by PhpStorm.
 * User: jakub
 * Date: 27.7.19
 * Time: 17:31
 */

namespace App\MemberModule\Components;


use App\Model\BillingService;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Tracy\Debugger;

final class BillingControl extends LayerControl {

	/**
	 * @var BillingService
	 */
	private $billingService;

	/**
	 * @var int
	 */
	private $akceId;

	/**
	 * @var int
	 */
	private $userId;

	/**
	 * @var ActiveRow|IRow
	 */
	private $billing;

	/**
	 * @var bool @persistent
	 */
	public $edit = FALSE;

	/**
	 * BillingControl constructor.
	 * @param BillingService $billingService
	 * @param int $akceId
	 * @param int $userId
	 */
	public function __construct(BillingService $billingService, int $akceId, int $userId)
	{
		parent::__construct();
		$this->billingService = $billingService;
		$this->akceId = $akceId;
		$this->userId = $userId;

		$this->billing = $this->billingService->getBillingByAkceId($this->akceId);
	}


	/**
	 * @return Form
	 */
	protected function createComponentBillingForm() {
		$form = new Form();

		$container = function (\Nette\Forms\Container $item) {
			$item->addText('name', 'Název', 30)
				->setRequired('Vyplňte prosím nazev položky')
				->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
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
				->setDefaultValue(FALSE);

			$item->addText('invoice', 'č. faktury', 10)
				->setNullable();

			$item->addHidden('id');

			$item->addButton('remove', '✖')
				->setOmitted()
				->setHtmlAttribute('class', 'buttonLike remove')
				->setHtmlAttribute('title', 'Smazat pložku');
		};

		$incomes = $form->addMultiplier('incomes', $container, 1);
		$incomes->addCreateButton('+');

		$expenses = $form->addMultiplier('expenses', $container, 1);
		$expenses->addCreateButton('+');

		$form->addText('income', 'Příjmy')
			->setRequired('Vyplňte příjmy')
			->setHtmlType('number')
			->setHtmlAttribute('class', 'currency')
			->setHtmlId('billing-income')
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
			->setHtmlId('billing-expense')
			->setOption('description', 'Kč')
			->setHtmlAttribute('readonly')
			->setHtmlAttribute('min', 0)
			->setDefaultValue(0)
			->addRule(Form::FLOAT)
			->addRule(Form::MIN, 'Hodnota nesmí být menší než %d', 0);

		$form->addText('final', 'Výsledek')
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

		$form->addSubmit('save', 'Uložit');

		$form->onValidate[] = [$this, 'validateValues'];

		$form->onSuccess[] = [$this, 'processForm'];

		if ($this->billing) $this->loadValues($form, $this->billing);

		return $form;
	}

	/**
	 * @param Form $form
	 */
	protected function processForm(Form $form) {
		$values = $form->getValues();
		$now = new DateTime();

		$billingValues = [
			'final' => $values->final,
			'expense' => $values->expense,
			'income' => $values->income,
			'date_update' => $now
		];

		if (!$this->billing) {
			$billingValues['date_add'] = $now;
			$billingValues['akce_id'] = $this->akceId;
			$billingValues['created_by'] = $this->userId;

			$this->billing = $this->billingService->addBilling($billingValues);
		} else {
			$this->billing->update($billingValues);
		}

		$this->billingService->deleteBillingItems($this->billing->id);

		foreach ($values->incomes as $income)
			$this->processItem($income, $this->billing->id);

		foreach ($values->expenses as $expense)
			$this->processItem($expense, $this->billing->id, TRUE);

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
	 * @param Form $form
	 * @param ArrayHash $values
	 */
	public function validateValues(Form $form, ArrayHash $values) {
		$expense = 0;
		foreach ($values->expenses as $item)
			$expense += $item->price * $item->count;

		$income = 0;
		foreach ($values->incomes as $item)
			$income += $item->price * $item->count;

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
			$values[$category][] = $item;
		}

		$form->setDefaults($values);
		$form->setDefaults($billing);
	}

	/**
	 *
	 */
	public function render() {
		$this->template->setFile(__DIR__ . '/BillingControl.latte');
		$this->template->edit = $this->edit;
		$this->template->billing = $this->billing;
		$this->template->render();
	}
}