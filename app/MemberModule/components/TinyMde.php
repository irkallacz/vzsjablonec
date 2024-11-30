<?php


namespace App\MemberModule\Components;


use Nette\Forms\Controls\TextArea;
use Nette\Utils\Html;

class TinyMde extends TextArea
{
	public function getControl()
	{
		$input = parent::getControl();

		return Html::el(null)
			->addHtml(Html::el('dialog', ['class' => 'editor-dialog', 'id' => $this->getHtmlId() . '-dialog'])
				->addHtml(Html::el('div', ['class' => 'right'])
					->addHtml(Html::el('button', ['class' => 'editor-dialog-close-button', 'id' => $this->getHtmlId() . '-editor-dialog-close-button'])
						->addText('✖'))
				)->addHtml(Html::el('div', ['class' => 'editor-preview', 'id' => $this->getHtmlId() . '-preview'])))
			->addHtml(Html::el('div', ['class' => 'editor-toolbar', 'id' => $this->getHtmlId() . '-toolbar']))
			->addHtml($input);
	}
}