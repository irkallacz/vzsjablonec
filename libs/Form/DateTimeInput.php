<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 9.1.2017
 * Time: 17:56
 */

use Nette\Forms\Form;
use	Nette\Utils\Html;

class DateTimeInput extends Nette\Forms\Controls\BaseControl {
	private $date, $time;

	public function __construct($label = NULL){
		parent::__construct($label);
		$this->addRule(__CLASS__ . '::validateDate', 'Date is invalid.');
	}

	public function setValue($value){
		if ($value) {
			$date = \Nette\DateTime::from($value);
			$this->date = $date->format('d.m.Y');
			$this->time = $date->format('H:i');
		} else {
			$this->date = $this->time = NULL;
		}
	}

	/**
	 * @return DateTime|NULL
	 */
	public function getValue(){
		return self::validateDate($this)
			? \Nette\DateTime::createFromFormat('d.m.Y H:i',$this->date.' '.$this->time)
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
				->pattern('[0-3]{1}\d{1}\.[0-1]{1}\d{1}\.[1-2]{1}\d{3}')
				->type('text')
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
		return (bool) \Nette\DateTime::createFromFormat('d.m.Y H:i',$control->date.' '.$control->time);
	}
}