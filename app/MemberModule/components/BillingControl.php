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
			->setRequired('Vyplňte celkovou bilanci')
			->setHtmlType('number')
			->setHtmlAttribute('class', 'currency')
			->setHtmlId('billing-final')
			->setOption('description', 'Kč')
			->setHtmlAttribute('readonly')
			->setDefaultValue(0)
			->addRule(Form::FLOAT);

		$form->addSubmit('save', 'Uložit');

		$form->onSubmit[] = function (Form $form){
			Debugger::barDump($form->getValues());
		};

		$form->onSubmit[] = function (Form $form){
			$values = $form->getValues();
			$now = new DateTime();

			$billing = $this->billingService->getBillingByAkceId($this->akceId);

			$billingValues = [
				'final' => $values->final,
				'expense' => $values->expense,
				'income' => $values->income,
				'date_update' => $now
			];

			if (!$billing) {
				$billingValues['date_add'] = $now;
				$billingValues['akce_id'] = $this->akceId;
				$billingValues['created_by'] = $this->userId;

				$billing = $this->billingService->addBilling($billingValues);
			} else {
				$billing->update($billingValues);
			}

			$this->billingService->deleteBillingItems($billing->id);

			foreach ($values->incomes as $income) {
				$this->processItem($income, $billing->id);
			}

			foreach ($values->expenses as $expense) {
				$this->processItem($expense, $billing->id, TRUE);
			}
		};

		return $form;
	}

	private function processItem(ArrayHash $item, int $billingId, bool $minus = FALSE) {
		if (!$item->id) unset($item->id);

		$item->billing_id = $billingId;
		$item->minus = $minus;
		$this->billingService->addBillingItem($item);
	}

	/**
	 *
	 */
	public function render() {
		$this->template->setFile(__DIR__ . '/BillingControl.latte');
		$this->template->render();
	}
}