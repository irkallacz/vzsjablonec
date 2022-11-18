<?php


namespace App\MemberModule\Components;


use Nette\Application\UI\Control;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Tracy\Debugger;

final class Menu extends Control
{
	/**
	 * @var ArrayHash
	 */
	private $items;

	public function __construct(array $items = [])
	{
		$this->items = ArrayHash::from($items);
	}

	public function render(string $baseUrl)
	{
		$ul = Html::el('ul', ['id' => 'menu']);

		foreach ($this->items as $item) {
			if (!$this->presenter->user->isInRole($item->role)) {
				continue;
			}

			$li = $ul->create('li');

			if (property_exists($item, 'current')) {
				if ($this->presenter->isLinkCurrent($item->current)) {
					$li->setAttribute('class', 'current');
				}
			}

			$a = $li->create('a');

			if (property_exists($item,'link')) {
				$a->href = $item->link;
				$a->target = '_blank';
			}

			if (property_exists($item,'action')) {
				$a->href = $this->presenter->link($item->action);
			}

			$a->addHtml('<svg class="icon icon-'.$item->icon.'"><use xlink:href="'.$baseUrl.'/img/symbols.svg#icon-'.$item->icon.'"></use></svg>');

			$a->create('span')
				->setText($item->title);
		}

		print $ul->render();
	}



}