<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 31.8.2017
 * Time: 18:54
 */

namespace App\CronModule\Presenters;

use Tracy\Debugger;

class TestPresenter extends BasePresenter {

	/** @var \Nette\Http\Request @inject */
	public $httpRequest;

	public function beforeRender() {
		parent::beforeRender();
		$this->setView('../Cron.default');
	}

	public function actionDefault() {
		Debugger::barDump($this->httpRequest->getRemoteAddress().' '.$this->httpRequest->getRemoteHost());
		Debugger::log($this->httpRequest->getRemoteAddress().' '.$this->httpRequest->getRemoteHost());
	}

}