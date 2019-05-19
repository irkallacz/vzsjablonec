<?php

namespace App\AccountModule\Presenters;

use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template|\stdClass $template
 */
abstract class BasePresenter extends Presenter {

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
	 */
	public function checkRequirements($element) {
		if (!$this->context->parameters['productionMode']) {
			$this->getUser()->getStorage()->setNamespace('account');
		}
		parent::checkRequirements($element);
	}

}
