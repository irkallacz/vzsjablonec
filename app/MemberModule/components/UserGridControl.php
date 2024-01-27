<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 27.11.2018
 * Time: 19:57
 */

namespace App\MemberModule\Components;

use App\Model\UserService;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;
use Nette\Http\SessionSection;
use Nette\Utils\ArrayHash;
use Nette\Utils\Paginator;
use Nextras\Datagrid\Datagrid;
use Tracy\Debugger;

class UserGridControl extends Control {

	/**
	 * @var UserService $userService
	 */
	private $userService;

	/**
	 * @var SessionSection $session
	 */
	private $session;

	/**
	 * @var array $columns
	 */
	private $columns = ['surname', 'name', 'date_born', 'age', 'mail', 'telefon', 'date_add'];

	/**
	 * @var int $itemsPerPage;
	 */
	private $itemsPerPage = 50;

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
	 *
	 */
	const COLUMNS = [
		'surname' 		=> ['label' => 'Příjmení',		'size' => 20,	'format' => 'string'],
		'name' 			=> ['label' => 'Jméno',			'size' => 20,	'format' => 'string'],
		'date_born' 	=> ['label' => 'Datum nar.', 	'size' => 12,	'format' => 'DD.MM.YYYY'],
		'age' 			=> ['label' => 'Věk', 			'size' =>  5,	'format' => 'integer',	'order' => FALSE],
		'rc' 			=> ['label' => 'Rodné čís.', 	'size' => 12,	'format' => 'string'],
		'mail' 			=> ['label' => 'E-mail', 		'size' => 30,	'format' => 'string'],
		'telefon' 		=> ['label' => 'Telefon', 		'size' => 12,	'format' => '000 000 000'],
		'mail2' 		=> ['label' => 'E-mail2', 		'size' => 30,	'format' => 'string'],
		'telefon2' 		=> ['label' => 'Telefon2', 		'size' => 12,	'format' => '000 000 000'],
		'send_to_second'=> ['label' => 'Kopie', 		'size' =>  5,	'format' => 'string',	'order' => FALSE],
		'street' 		=> ['label' => 'Ulice',			'size' => 20,	'format' => 'string'],
		'street_number'	=> ['label' => 'Číslo p.',		'size' => 8,	'format' => 'string'],
		'city'	 		=> ['label' => 'Město',			'size' => 20,	'format' => 'string'],
		'postal_code'	=> ['label' => 'PSČ',			'size' => 8,	'format' => 'integer'],
		'bank_account'	=> ['label' => 'Číslo účtu',	'size' => 20,	'format' => 'string'],
		'zamestnani'	=> ['label' => 'Zaměstnání',	'size' => 20,	'format' => 'string'],
		'photo' 		=> ['label' => 'Fotka',			'size' =>  5,	'format' => 'string',	'order' => FALSE],
		'hash' 			=> ['label' => 'Heslo', 		'size' =>  5,	'format' => 'string',	'order' => FALSE],
		'vzsId' 		=> ['label' => 'Vzs ID', 		'size' =>  8,	'format' => 'integer'],
		'cardId' 		=> ['label' => 'ID Karty', 		'size' =>  8,	'format' => 'string'],
		'iDokladId' 	=> ['label' => 'iDokladID', 	'size' =>  8,	'format' => 'integer'],
		'date_add' 		=> ['label' => 'Datum reg.', 	'size' => 12,	'format' => 'DD.MM.YYYY'],
		'date_update'	=> ['label' => 'Datum akt.', 	'size' => 12,	'format' => 'DD.MM.YYYY HH:MM'],
	];

	/**
	 *
	 */
	const YES_NO_ARRAY = ['✗','✓'];

	/**
	 * UserGridControl constructor.
	 * @param UserService $userService
	 * @param SessionSection $session
	 */
	public function __construct(UserService $userService, SessionSection $session){
		parent::__construct();
		$this->userService = $userService;
		$this->session = $session;

		if ($session->columns) {
			$this->columns = $session->columns;
		}

		if ($session->itemsPerPage){
			$this->itemsPerPage = $session->itemsPerPage;
		}
	}


	/**
	 * @return Datagrid
	 */
	protected function createComponentGrid(){
		$grid = new Datagrid();
		$grid->addColumn('id',' ');

		$first = TRUE;
		foreach ($this->columns as $name){
			$item = ArrayHash::from(self::COLUMNS[$name]);
			$column = $grid->addColumn($name, $item->label);
			if (!isset($item->order)) $column->enableSort($first ? Datagrid::ORDER_ASC : NULL);
			if ($first) $first = FALSE;
		}

		$grid->addColumn('role', 'Role')->enableSort();

		$grid->setFilterFormFactory(function() {
			$form = new Container();
			$form->addText('id', NULL, 3);

			foreach (self::COLUMNS as $name => $array){
				$item = ArrayHash::from($array);
				switch($name){
					case 'hash':
					case 'send_to_second':
					case 'photo':
						$form->addSelect($name, $item->label, self::YES_NO_ARRAY)->setPrompt(' ');
						break;
					default:
						$form->addText($name, $item->label, isset($item->size) ?  $item->size : NULL);
				};
			}

			$userLevels = self::USER_LEVELS;
			if (!$this->presenter->user->isInRole('admin')) unset($userLevels[UserService::DELETED_LEVEL]);

			$form->addSelect('role', NULL, $userLevels)
				->setDefaultValue(UserService::MEMBER_LEVEL);

			return $form;
		});

		$grid->setDataSourceCallback([$this, 'getDataSourceCallback']);

		$grid->setPagination($this->itemsPerPage, function($filter){
			return $this->getDataSourceCallback($filter)->count();
		});

		$grid->addCellsTemplate(__DIR__ . '/UserGridControlColumns.latte');

		$grid->addGlobalAction('export', 'export', [$this, 'export']);
		$grid->addGlobalAction('mail', 'mail', [$this, 'mail']);
		$grid->addGlobalAction('registration', 'registrace', [$this, 'registration']);
		$grid->addGlobalAction('approval', 'schválení', [$this, 'approval']);

		return $grid;
	}


	/**
	 * @param array $filter
	 * @param array|null $order
	 * @return Selection
	 */
	public function getDataSourceCallback(array $filter, $order = NULL, Paginator $paginator = NULL){
		$filters = [];
		foreach ($filter as $column => $value) {
			switch ($column) {
				case 'age':
					$filters['TIMESTAMPDIFF(YEAR, date_born, CURDATE()) = ?'] = intval($value);
					break;
				case 'role':
					if ($value != UserService::DELETED_LEVEL) $filters[$column. ' >= ?'] = $value-1;
					break;
				case 'hash':
				case 'photo':
					if ($value) $key = $column . ' NOT'; else $key = $column;
					$filters[$key] = NULL;
					break;
				case 'id':
				case 'send_to_second':
					$filters[$column] = $value;
					break;
				case 'mail':
				case 'mail2':
					$filters[$column. ' LIKE ?'] = "%$value%";
					break;
				default:
					$filters[$column. ' LIKE ?'] = "$value%";
			};
		}

		$selection = $this->userService->getUsers(UserService::DELETED_LEVEL)->where($filters);

		if ($order) {
			$selection->order(implode(' ', $order));
		}

		if ($paginator) {
			$selection->limit($paginator->getItemsPerPage(), $paginator->getOffset());
		}

		return $selection;
	}

	/**
	 * @return Form
	 */
	protected function createComponentColumnsForm(){
		$items = [];
		foreach (self::COLUMNS as $name => $array){
			$items[$name] = $array['label'];
		}

		$form = new Form();
		//$form->getElementPrototype()->class = 'ajax';

		$form->addCheckbox('all', NULL)
			->setHtmlId('selectAll')
			->setOmitted();

		$form->addSubmit('save','Uložit');

		$columns = $form->addMultiplier('columns', function (\Nette\Forms\Container $column) use($items) {
			$column->addSelect('column', NULL, $items);
		}, 0);

		$default = [];
		foreach ($this->columns as $name){
			$default[] = ['column' => $name];
		}

		$columns->addCreateButton('+');
		$columns->addRemoveButton('✖');

		$columns->setDefaults($default);

		$form->addText('itemsPerPage',NULL)
			->setHtmlType('number')
			->setHtmlAttribute('class', 'int')
			->setRequired(TRUE)
			->addRule(Form::INTEGER)
			->setDefaultValue($this->itemsPerPage);

		$form->onSuccess[] = function (Form $form, ArrayHash $values){
			$columns = [];
			foreach ($values->columns as $item){
				$columns[$item->column] = $item->column;
			}
			$columns = array_keys($columns);

			$this->session->columns = $this->columns = $columns;
			$this->session->itemsPerPage = $this->itemsPerPage = $values->itemsPerPage;

			if ($this->getPresenter()->isAjax()) {
				$this->redrawControl('datagrid');
			} else{
				$this->getPresenter()->redirect('this');
			}
		};

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = 'tr';
		$renderer->wrappers['pair']['container'] = 'td';
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @param array $selection
	 * @throws \Nette\Application\AbortException
	 */
	public function export(array $selection){
		if (!empty($selection)){
			$header = [' ' => 'integer'];
			$widths = [5];
			foreach ($this->columns as $name){
				$column = ArrayHash::from(self::COLUMNS[$name]);
				$header[$column->label] = $column->format;
				$widths[] = $column->size;
			}

			$writer = new \XLSXWriter();
			$writer->setTempDir(WWW_DIR . '/../tmp');
			$writer->writeSheetHeader('List1', $header, ['font-style' => 'bold', 'widths' => $widths]);

			foreach ($selection as $id){
				$user = $this->userService->getUserById($id, UserService::DELETED_LEVEL);
				$data = [$user->id];

				foreach ($this->columns as $column){
					switch ($column){
						case 'age':
							$value = ($user->date_born) ? $user->date_born->diff(date_create())->y : NULL;
							break;
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

	public function registration(array $selection) {
		if (empty($selection)) {
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		} else {
			$year = (int) date('Y');

			$count = 0;
			foreach ($selection as $user_id) {
				if ($this->userService->addRegistration($user_id, $year)) {
					$count++;
				}
			}

			$this->flashMessage(sprintf('Registrováno %d uživatelů', $count), $count ? 'info' : 'error');
		}
	}

	public function approval(array $selection) {
		if (empty($selection)) {
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		} else {
			$count = 0;
			foreach ($selection as $user_id) {
				if ($this->userService->addApproval($user_id)) {
					$count++;
				}
			}

			$this->flashMessage(sprintf('Schváleno %d uživatelů', $count), $count ? 'info' : 'error');
		}
	}

	/**
	 * @param array $selection
	 * @throws \Nette\Application\AbortException
	 */
	public function mail(array $selection){
		if (!empty($selection)){
			$this->presenter->redirect('Mail:add', ['recipients' => $selection]);
		}else{
			$this->flashMessage('Musíte vybrat uživatele', 'error');
		}
	}

	/**
	 *
	 */
	public function render(){
		$this->template->setFile(__DIR__ . '/UserGridControl.latte');
		$this->template->render();
	}

}