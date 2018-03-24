<?php

namespace App\SignModule\Presenters;

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
	 *
	 */
	protected function afterRender() {
		parent::afterRender();
		if (!$this->context->parameters['productionMode']) {
			parent::afterRender();
			$this->template->basePath .= '/sign';
			$this->template->baseUri .= '/sign';
		}
	}


}
