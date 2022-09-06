<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 13.1.2018
 * Time: 12:19
 */

namespace App\MemberModule\Forms;

use App\Model\UserService;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\TextInput;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class UserFormFactory {
	use SmartObject;

	/** @var UserService */
	private $userService;

	/** @var int */
	private $userId = NULL;

	/**
	 * UserFormFactory constructor.
	 * @param UserService $userService
	 */
	public function __construct(UserService $userService) {
		$this->userService = $userService;
	}

	/**
	 * @return Form
	 */
	public function create(){
		$form = new Form();

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addGroup('Osobní data');

		$form->addText('name', 'Jméno', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper']);

		$form->addText('surname', 'Příjmení', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper']);

		$form['date_born'] = new \DateInput('Datum narození');
		$form['date_born']->setRequired('Vyplňte datum narození')
			->setDefaultValue(new DateTime());

		$form->addText('rc', 'Rodné číslo', 11)
			->setRequired(FALSE)
			->setNullable()
			->setHtmlAttribute('placeholder', '000000/0000')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_LENGTH, 'Rodné číslo by nemělo být delší než %d znaků', 11);

		$form->addText('zamestnani', 'Zaměstnání/Škola', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		$form->addGroup('Kontakty');

		$form->addText('mail', 'Primární e-mail', 30)
			->setType('email')
			->addFilter(['\Nette\Utils\Strings', 'lower'])
			->addRule(Form::EMAIL, 'Zadejte platný email')
			->addRule([$this, 'uniqueMailValidator'], 'V databázi se již vyskytuje osoba se stejnou emailovou adresou')
			->setRequired('Vyplňte %label');

		$form->addText('telefon', 'Primární telefon', 30)
			->setType('tel')
			->setRequired('Vyplňte %label')
			->addRule(Form::INTEGER, '%label musí obsahovat jenom čísla')
			->addRule(Form::LENGTH, '%label musí mít %d znaků', 9);

		$form->addText('mail2', 'Sekundární e-mail', 30)
			->setType('email')
			->setNullable()
			->addCondition(Form::FILLED)
			->addFilter(['\Nette\Utils\Strings', 'lower'])
			->addRule(Form::EMAIL, 'Zadejte platný email')
			->addRule([$this, 'uniqueMailValidator'], 'V databázi se již vyskytuje osoba se stejnou emailovou adresou')
			->addRule(Form::NOT_EQUAL, 'E-maily se nesmí shodovat', $form['mail'])
			->toggle('send_to_second');

		$form->addCheckbox('send_to_second', 'Zasílat e-maily i na sekundární email?')
			->setDefaultValue(FALSE)
			->getLabelPrototype()->id = 'send_to_second';

		$form['mail2']->setRequired(FALSE)
			->addConditionOn($form['send_to_second'], Form::EQUAL, TRUE)
			->addRule(Form::EMAIL, 'Vyplňte sekundární email');

		$form->addText('telefon2', 'Sekundární telefon', 30)
			->setType('tel')
			->setNullable()
			->addCondition(Form::FILLED)
			->addRule(Form::NOT_EQUAL, 'Telefony se nesmí shodovat', $form['telefon'])
			->addRule(Form::INTEGER, '%label musí obsahovat jenom čísla')
			->addRule(Form::LENGTH, '%label musí mít %d znaků', 9);

		$form->addText('bank_account', 'Číslo účtu',30)
			->setNullable()
			->setRequired(FALSE)
			->addRule([$this, 'validBankAccountValidator'], 'Jste si jistí, že jste zadali správné číslo účtu?');

		$form->addGroup('Adresa');

		$form->addText('ulice', 'Ulice', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte ulici');

		$form->addText('mesto', 'Město', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		return $form;
	}

	/**
	 * @param int $userId
	 */
	public function setUserId(int $userId = NULL) {
		$this->userId = $userId;
	}

	/**
	 * @param BaseControl $item
	 * @return bool
	 */
	public function uniqueMailValidator(BaseControl $item) {
		return $this->userService->isEmailUnique($item->value, $this->userId);
	}

	/**
	 * @param Form $form
	 * @param ArrayHash $values
	 */
	public function uniqueCredentialsValidator(Form $form, ArrayHash $values) {
		if ((!isset($values->skip))or((isset($values->skip))and(!$values->skip))) {

			if (!$this->userService->isCredentialsUnique($values))
				$form->addError('V databázi máme již podobného uživatele, jste si jistí, že nejde o tutéž osobu?');
		}
	}

	/**
	 * @param BaseControl $bank_account
	 * @return bool
	 */
	public function validBankAccountValidator(BaseControl $bank_account) {
		$matches = [];
		if (!preg_match('/^(?:([0-9]{1,6})-)?([0-9]{2,10})\/([0-9]{4})$/', $bank_account->getValue(), $matches)) {
			return false;
		}
		$weights = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];
		$prefix = str_pad($matches[1], 10, '0', STR_PAD_LEFT);
		$main   = str_pad($matches[2], 10, '0', STR_PAD_LEFT);

		// Check prefix
		$checkSum = 0;
		for ($i=0; $i < strlen($prefix); $i++) {
			$checkSum += $weights[$i] * (int)$prefix[$i];
		}
		if ($checkSum % 11 !== 0) {
			return false;
		}

		// Check main part
		$checkSum = 0;
		for ($i=0; $i < strlen($main); $i++) {
			$checkSum += $weights[$i] * (int)$main[$i];
		}
		if ($checkSum % 11 !== 0) {
			return false;
		}
		return true;
	}
}