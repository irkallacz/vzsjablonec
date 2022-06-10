<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 17.3.2018
 * Time: 10:04
 */

namespace App\AccountModule\Presenters;

use App\MemberModule\Forms\UserFormFactory;
use App\Model\MessageService;
use App\Model\UserService;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Nette\Utils\Html;

class RegisterPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var MessageService @inject */
	public $messageService;

	/** @var UserFormFactory @inject */
	public $userFormFactory;

	/**
	 * @return Form
	 */
	protected function createComponentRegisterForm() {
		$form = $this->userFormFactory->create();

		$form['mail']->caption = 'E-mail';
		$form['telefon']->caption = 'Telefon';

		$form['mail2']->caption = 'Druhý e-mail';
		$form['mail2']->setOption('description','(na rodiče atd...)');

		$form['telefon2']->caption = 'Druhý telefon';
		$form['telefon2']->setOption('description','(na rodiče atd...)');

		unset($form['bank_account']);

		$form->addGroup(NULL);

		$form->addCheckbox('check', Html::el()->setHtml('Souhlasím se <a target="_blank" href="gdpr">zpracováním osobních údajů'))
			->setOmitted()
			->setRequired('Musíte souhlasit se zpracováním osobních údajů')
			->setDefaultValue(TRUE);

		$form->addGroup('Oveření');

		$form->addReCaptcha('recaptcha', NULL)
			->setRequired('Potvrďte, že nejste robot')
			->setOmitted();

		$form->addGroup(NULL);

		$form->addSubmit('ok', 'Odeslat');

		$form->onValidate[] = function (Form $form){
			$values = $form->getValues();
			if (($values->date_born->diff(date_create())->y < 18)and((!$values->mail2)or(!$values->telefon2))) {
				$form->addError('U nezletilých je potřeba vyplnit e-mail a telefon rodičů');
			}
		};

		$form->onSuccess[] = function (Form $form){
			$values = $form->getValues();
			$now = new DateTime();

			if ($values->date_born->diff($now)->y < 18) {
				$values->send_to_second = TRUE;
			}

			$values->date_add = $now;

			$user = $this->userService->addUser($values, UserService::DELETED_LEVEL);
			$this->flashMessage('Záznam byl uložen, čekejte prosím na e-mail od administrátora');

			$this->addRegistrationMail($user);

			$this->redirect('Sign:in');
		};

		return $form;
	}

	/**
	 * @param IRow|ActiveRow $user
	 */
	private function addRegistrationMail(IRow $user){
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/newRegistration.latte');
		$template->member = $user;

		$message = new MessageService\Message(MessageService\Message::REGISTRATION_NEW_TYPE);
		$message->setSubject('Nová registrace uživatele');
		$message->setText($template);
		$message->setAuthor($user->id);
		$message->setRecipients($this->userService->getUsers(UserService::ADMIN_LEVEL));
		$message->setParameters(['user_id' => $user->id]);

		$this->messageService->addMessage($message);
	}

}