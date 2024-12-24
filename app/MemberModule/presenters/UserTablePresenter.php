<?php

namespace App\MemberModule\Presenters;

use App\Model\AchievementsService;
use App\Model\AnketyService;
use App\Model\UserService;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

final class UserTablePresenter extends LayerPresenter
{

	/**
	 * @var UserService $userService @inject
	 */
	public $userService;

	/**
	 * @var AchievementsService $achievementsService @inject
	 */
	public $achievementsService;

	/**
	 * @var AnketyService $anketyService @inject
	 */
	public $anketyService;

	/**
	 * @var array $selection
	 */
	private $selection = [];

	/** @var bool @persistent */
	public $filter = null;

	/** @var int @persistent */
	public $role = null;

	/**
	 * @var array $userLevels
	 */
	const USER_LEVELS = [
		UserService::DELETED_LEVEL 	=> 'Vše',
		UserService::USER_LEVEL 	=> 'Uživatel',
		UserService::MEMBER_LEVEL 	=> 'Člen',
		UserService::EDITOR_LEVEL 	=> 'Editor',
		UserService::ADMIN_LEVEL 	=> 'Admin',
	];

	/**
	 * @var array
	 */
	const COLUMNS = [
		'id'			=> ['label' => 'ID',			'visible' => true,	'format' => 'integer',			'size' => 5,	'type' => 'integer',	'order' => true],
		'surname' 		=> ['label' => 'Příjmení',		'visible' => true,	'format' => 'string',			'size' => 20,	'type' => 'string', 	'order' => true],
		'name' 			=> ['label' => 'Jméno',			'visible' => true,	'format' => 'string',			'size' => 20,	'type' => 'string', 	'order' => true],
		'date_born' 	=> ['label' => 'Narození', 		'visible' => true,	'format' => 'DD.MM.YYYY', 		'size' => 12,	'type' => 'date', 		'order' => true],
		'age' 			=> ['label' => 'Věk', 			'visible' => true,	'format' => 'integer',			'size' =>  5,	'type' => 'integer',	'order' => true],
		'rc' 			=> ['label' => 'Rodné číslo', 	'visible' => false,	'format' => 'string',			'size' => 12,	'type' => 'code', 	'order' => true],
		'mail' 			=> ['label' => 'E-mail', 		'visible' => true,	'format' => 'string',			'size' => 30,	'type' => 'string', 	'order' => true],
		'phone' 		=> ['label' => 'Telefon', 		'visible' => true,	'format' => '000 000 000', 		'size' => 12,	'type' => 'phone', 		'order' => true],
		'mail2' 		=> ['label' => 'E-mail2', 		'visible' => false,	'format' => 'string', 			'size' => 30,	'type' => 'string', 	'order' => true],
		'phone2' 		=> ['label' => 'Telefon2', 		'visible' => false,	'format' => '000 000 000', 		'size' => 12,	'type' => 'phone', 		'order' => true],
		'send_to_second'=> ['label' => 'Kopie', 		'visible' => false,	'format' => 'string',			'size' =>  5,	'type' => 'bool',		'order' => false],
		'street' 		=> ['label' => 'Ulice',			'visible' => false,	'format' => 'string',			'size' => 20,	'type' => 'string',		'order' => true],
		'street_number'	=> ['label' => 'Číslo p.',		'visible' => false,	'format' => 'string',			'size' => 8,	'type' => 'string',		'order' => true],
		'city'	 		=> ['label' => 'Město',			'visible' => false,	'format' => 'string',			'size' => 20,	'type' => 'string',		'order' => true],
		'postal_code'	=> ['label' => 'PSČ',			'visible' => false,	'format' => 'integer',			'size' => 8,	'type' => 'integer',	'order' => true],
		'bank_account'	=> ['label' => 'Číslo účtu',	'visible' => false,	'format' => 'string',			'size' => 20,	'type' => 'string',		'order' => true],
		'occupation'	=> ['label' => 'Zaměstnání',	'visible' => false,	'format' => 'string',			'size' => 20,	'type' => 'string',		'order' => true],
		'role'			=> ['label' => 'Role',			'visible' => true,	'format' => 'integer',			'size' => 5,	'type' => 'integer',	'order' => true],
		'photo' 		=> ['label' => 'Fotka',			'visible' => false,	'format' => 'string',			'size' =>  5,	'type' => 'string',		'order' => false],
		'hash' 			=> ['label' => 'Heslo', 		'visible' => false,	'format' => 'string',			'size' =>  5,	'type' => 'bool',		'order' => false],
		'vzs_id' 		=> ['label' => 'Vzs ID', 		'visible' => false,	'format' => 'integer',			'size' =>  8,	'type' => 'integer', 	'order' => true],
		'evidsoft_id' 	=> ['label' => 'Evidsfot ID', 	'visible' => false,	'format' => 'integer',			'size' =>  8,	'type' => 'integer', 	'order' => true],
		'card_id' 		=> ['label' => 'ID Karty', 		'visible' => false,	'format' => 'string',			'size' =>  8,	'type' => 'code', 	'order' => true],
		'idoklad_id' 	=> ['label' => 'iDoklad ID', 	'visible' => false,	'format' => 'integer',			'size' =>  8,	'type' => 'integer', 	'order' => true],
		'approved_from'	=> ['label' => 'Schválený od',	'visible' => false,	'format' => 'DD.MM.YYYY', 		'size' => 12, 	'type' => 'date', 		'order' => true],
		'proper_from'	=> ['label' => 'Řádný od',		'visible' => false,	'format' => 'DD.MM.YYYY', 		'size' => 12, 	'type' => 'date', 		'order' => true],
		'date_add' 		=> ['label' => 'Registrace', 	'visible' => true,	'format' => 'DD.MM.YYYY', 		'size' => 12,	'type' => 'date', 		'order' => true],
		'date_update'	=> ['label' => 'Aktualizace', 	'visible' => false,	'format' => 'DD.MM.YYYY HH:MM', 'size' => 12,	'type' => 'datetime', 	'order' => true],
	];

	/**
	 * @var array
	 */
	const YES_NO_ARRAY = ['✗','✓'];

	public function renderDefault()
	{
		$rows = $this->userService->getUsers(is_null($this->role) ? UserService::MEMBER_LEVEL : $this->role)
			->select('*, TIMESTAMPDIFF(YEAR, date_born, CURDATE()) AS age');

		if (($this->filter) && (count($this->selection))) {
			$rows->where('id', $this->selection);
		}

		$this->template->rows = $rows;
		$this->template->selection = $this->selection;
		$this->template->filter = $this->filter;
		$this->template->columns = ArrayHash::from(self::COLUMNS);
	}

	public function actionEvent(int $id)
	{
		$this->selection = $this->userService->getUsersByAkceId($id)->fetchPairs(null, 'id');

		if (is_null($this->filter)) {
			$this->filter = true;
		}

		if (is_null($this->filter)) {
			$this->role = UserService::USER_LEVEL;
		}

		$this->setView('default');
	}

	public function actionAchievement(int $id)
	{
		$this->selection = $this->achievementsService->getUsersForBadge($id)->fetchPairs(null, 'user_id');

		if (is_null($this->filter)) {
			$this->filter = true;
		}

		if (is_null($this->role)) {
			$this->role = UserService::USER_LEVEL;
		}

		$this->setView('default');
	}

	public function actionSurvey(int $id)
	{
		$this->selection = $this->anketyService->getMembersByAnketaId($id)->fetchPairs(null, 'user_id');

		if (is_null($this->filter)) {
			$this->filter = true;
		}

		if (is_null($this->role)) {
			$this->role = UserService::USER_LEVEL;
		}

		$this->setView('default');
	}

	public function createComponentGridForm(): Form
	{
		$form = new Form();

		//$form->addCheckbox('filter', 'Filtrovat')
		//	->setDefaultValue($this->filter);

		$form->addSelect('role', 'Role', self::USER_LEVELS)
			->setDefaultValue(is_null($this->role) ? UserService::MEMBER_LEVEL : $this->role);

		$form->addSubmit('ok', 'OK')
			->onClick[] = [$this, 'processChange'];

		$form->addSubmit('export', 'Export')
			->setHtmlAttribute('class', 'buttonLike')
			->onClick[] = [$this, 'processExport'];

		$form->addSubmit('email', 'Email')
			->setHtmlAttribute('class', 'buttonLike')
			->onClick[] = [$this, 'processEmail'];

		$form->addSubmit('approve', 'Schválení')
			->setHtmlAttribute('class', 'buttonLike')
			->onClick[] = [$this, 'processApprove'];

		return $form;
	}

	public function processChange(SubmitButton $submitButton, ArrayHash $values)
	{
		$this->redirect('this', ['role' => $values->role]);
	}

	public function processExport(SubmitButton $submitButton, ArrayHash $values)
	{
		$form = $submitButton->getForm();
		$selection = $form->getHttpData($form::DATA_TEXT, 'id[]');

		if (count($selection)) {
			$header = [];
			$widths = [];
			foreach (self::COLUMNS as $setting) {
				$setting = ArrayHash::from($setting);
				$header[$setting->label] = $setting->format;
				$widths[] = $setting->size;
			}

			$writer = new \XLSXWriter();
			$writer->setTempDir(WWW_DIR . '/../tmp');
			$writer->writeSheetHeader('List1', $header, ['font-style' => 'bold', 'widths' => $widths]);

			$users = $this->userService->getUsers(UserService::DELETED_LEVEL)
				->select('*, TIMESTAMPDIFF(YEAR, date_born, CURDATE()) AS age')
				->where('id', $selection);

			foreach ($users as $user){
				$data = [];
				foreach (self::COLUMNS as $column => $setting){
					switch ($column){
						case 'hash':
						case 'send_to_second':
							$value = self::YES_NO_ARRAY[boolval($user->{$column})];
							break;
						case 'date_born':
						case 'date_add':
						case 'date_update':
							$value = ($user->{$column}) ? $user->{$column}->format('Y-m-d H:i:s') : NULL;
							break;
						default:
							$value = $user->{$column};
					}
					$data[] = $value;
				}
				$writer->writeSheetRow('List1', $data);
			}

			header('Content-disposition: attachment; filename="export.xlsx"');
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Transfer-Encoding: binary');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');

			$writer->writeToStdOut();

			$this->presenter->terminate();
		} else{
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		}
	}

	public function processEmail(SubmitButton $submitButton, ArrayHash $values)
	{
		$form = $submitButton->getForm();
		$selection = $form->getHttpData($form::DATA_TEXT, 'id[]');

		if (count($selection)){
			$this->presenter->redirect('Mail:add', ['recipients' => $selection]);
		}else{
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		}
	}

	public function processRegistration(SubmitButton $submitButton, ArrayHash $values) {
		$form = $submitButton->getForm();
		$selection = $form->getHttpData($form::DATA_TEXT, 'id[]');

		if (count($selection)) {
			$year = (int) date('Y');

			$count = 0;
			foreach ($selection as $user_id) {
				if ($this->userService->addRegistration($user_id, $year)) {
					$count++;
				}
			}

			$this->flashMessage(sprintf('Registrováno %d uživatelů', $count), $count ? 'info' : 'error');
		} else {
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		}
	}

	public function processApproval(SubmitButton $submitButton, ArrayHash $values) {
		$form = $submitButton->getForm();
		$selection = $form->getHttpData($form::DATA_TEXT, 'id[]');

		if (count($selection)) {
			$count = 0;
			foreach ($selection as $user_id) {
				if ($this->userService->addApproval($user_id)) {
					$count++;
				}
			}

			$this->flashMessage(sprintf('Schváleno %d uživatelů', $count), $count ? 'info' : 'error');
		} else {
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		}
	}

}