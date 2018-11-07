<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 9.1.2017
 * Time: 17:56
 */

use Nette\Forms\Form;
use Nette\Utils\DateTime;
use	Nette\Utils\Html;

class DateInput extends Nette\Forms\Controls\BaseControl {

	const DATE_FORMAT = 'Y-m-d';

	/**
	 * @var string
	 */
	private $date;

	public function __construct($label = NULL){
		parent::__construct($label);
		$this->addRule(__CLASS__ . '::validateDate', 'Datum má špatný formát');
	}

	public function setValue($value){
		if ($value) {
			$date = DateTime::from($value);
			$this->date = $date->format(self::DATE_FORMAT);
		} else {
			$this->date = NULL;
		}
	}

	/**
	 * @return DateTime|NULL
	 */
	public function getValue(){
		if (self::validateDate($this)) {
			$date = DateTime::createFromFormat(self::DATE_FORMAT, $this->date);
			$date->setTime(0,0,0);
			return $date;
		}else {
			return NULL;
		}
	}

	public function loadHttpData(){
		$this->date = $this->getHttpData(Form::DATA_LINE, '[date]');
	}

	/**
	 * Generates control's HTML element.
	 */
	public function getControl(){
		$name = $this->getHtmlName();

		return Html::el('input')->name($name . '[date]')
				->id($this->getHtmlId())
				->pattern('[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
				->type('date')
				->size('10')
				->value($this->date)
				->class('date');
	}

	/**
	 * @return bool
	 */
	public static function validateDate(Nette\Forms\IControl $control){
		$value = $control->date;
		$datetime = [];
		$find = preg_match ('~([1-2]{1}\d{3})-([0-1]{1}\d{1})-([0-3]{1}\d{1})~', $value, $datetime);

		if ($find) {
			if (checkdate(intval($datetime[2]), intval($datetime[3]), intval($datetime[1]))) {
				return (bool) DateTime::createFromFormat(self::DATE_FORMAT, $value);
			} else return FALSE;
		} else return FALSE;
	}
}