<?php


namespace App\Form;

use Nette\Forms\Controls\TextInput;
use Nette\Utils\Html;

class DropdownInput extends TextInput
{
	protected $items = [];

	public function getControl(): Html
	{
		$input = parent::getControl();
		$input->addAttributes(['list' => $this->getHtmlId() . '-list']);

		$list = Html::el('datalist')
			->setAttribute('id', $this->getHtmlId() . '-list');

		foreach ($this->items as $item) {
			$list->addHtml(Html::el('option')->setText($item));
		}

		return Html::el(null)
			->addHtml($input)
			->addHtml($list);
	}

	public function setItems(array $items): self
	{
		$this->items = $items;
		return $this;
	}

}