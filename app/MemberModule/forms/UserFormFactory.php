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
use Nette\Object;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class UserFormFactory extends Object {
	/** @var UserService */
	private $userService;

	/** @var int */
	private $userId;

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
			->addRule(Form::LENGTH, '%label musí mít %d znaků', 9);

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
	public function setUserId(int $userId) {
		$this->userId = $userId;
	}

	/**
	 * @param BaseControl $item
	 * @return bool|mixed|\Nette\Database\Table\IRow
	 */
	public function uniqueMailValidator(BaseControl $item) {
		return $this->userService->isEmailUnique($item->value, $this->userId);
	}
}