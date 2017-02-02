<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 9.1.2017
 * Time: 17:56
 */

use Nette\Forms\Form;
use Nette\DateTime;
use	Nette\Utils\Html;

class DateTimeInput extends Nette\Forms\Controls\BaseControl {

	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i';

	private $date, $time;

	public function __construct($label = NULL){
		parent::__construct($label);
		$this->addRule(__CLASS__ . '::validateDate', 'Datum nebo čas má špatný formát');
	}

	public function setValue($value){
		if ($value) {
			$date = DateTime::from($value);
			$this->date = $date->format(self::DATE_FORMAT);
			$this->time = $date->format(self::TIME_FORMAT);
		} else {
			$this->date = $this->time = NULL;
		}
	}

	/**
	 * @return DateTime|NULL
	 */
	public function getValue(){
		return self::validateDate($this)
			? DateTime::createFromFormat(self::DATE_FORMAT.' '.self::TIME_FORMAT,$this->date.' '.$this->time)
			: NULL;
	}

	public function loadHttpData(){
		$this->date = $this->getHttpData(Form::DATA_LINE, '[date]');
		$this->time = $this->getHttpData(Form::DATA_LINE, '[time]');
	}

	/**
	 * Generates control's HTML element.
	 */
	public function getControl(){
		$name = $this->getHtmlName();

		return Html::el('span')
			->class('datetime')
			->add(Html::el('input')->name($name . '[date]')
				->id($this->getHtmlId())
				->pattern('[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
				->type('date')
				->size('10')
				->value($this->date)
				->class('date')
			)
			->add(' ')
			->add(Html::el('input')->name($name . '[time]')
				->pattern('[0-2]{1}\d{1}:[0-5]{1}\d{1}')
				->type('time')
				->size('5')
				->value($this->time)
				->class('time')
			);
	}

	/**
	 * @return bool
	 */
	public static function validateDate(Nette\Forms\IControl $control){
		$value = $control->date.' '.$control->time;
		$datetime = [];
		$find = preg_match ('~([1-2]{1}\d{3})-([0-1]{1}\d{1})-([0-3]{1}\d{1}) ([0-2]{1}\d{1}):([0-5]{1}\d{1})~', $value, $datetime);

		if ($find) {
			if (checkdate(intval($datetime[2]), intval($datetime[3]), intval($datetime[1]))) {
				return (bool) DateTime::createFromFormat(self::DATE_FORMAT.' '.self::TIME_FORMAT, $value);
			} else return FALSE;
		} else return FALSE;
	}
}