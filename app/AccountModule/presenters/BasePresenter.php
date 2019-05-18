<?php

namespace App\AccountModule\Presenters;

use App\Model\MessageService;
use App\Template\LatteFilters;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\Html;

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
