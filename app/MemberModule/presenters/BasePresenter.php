<?php

namespace App\MemberModule\Presenters;

use App\Template\LatteFilters;
use App\Template\TemplateProperty;
use Nette\Application\AbortException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Html;

/**
 * @property-read TemplateProperty|\Nette\Bridges\ApplicationLatte\Template $template
 */
abstract class BasePresenter extends Presenter {

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
	 */
	public function checkRequirements($element) {
		if (!$this->context->parameters['productionMode']) {
			$this->getUser()->getStorage()->setNamespace('member');
		}
		parent::checkRequirements($element);
	}

	/**
	 * @param bool $class
	 * @throws AbortException
	 */
	public function actionTexyPreview(bool $class = FALSE) {
		if ($this->isAjax()) {

			$httpRequest = $this->context->getByType('Nette\Http\Request');

			$div = Html::el('div')->setHtml(LatteFilters::texy($httpRequest->getPost('texy')));
			$div->id = 'texyPreview';
			if ($class) $div->class = 'texy';

			$this->sendResponse(new TextResponse($div));
		}
	}

}
