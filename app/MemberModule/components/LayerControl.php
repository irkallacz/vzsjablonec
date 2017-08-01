<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.07.2017
 * Time: 18:19
 */

namespace App\MemberModule\Components;

use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Control;

class LayerControl extends Control {

	public function checkRequirements($element){
		if ($element->hasAnnotation('allow')){
			$role = $element->getAnnotation('allow');
			if (!$this->getPresenter()->getUser()->isInRole($role)) {
				throw new ForbiddenRequestException('Na tuto akci nemáte práva');
			}
		}

		parent::checkRequirements($element);
	}

}