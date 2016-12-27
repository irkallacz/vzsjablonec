<?php

namespace MemberModule;

use Nette\Application\UI\Presenter;
use Nette\Utils\Html;


abstract class BasePresenter extends Presenter{

//    protected function startup(){
//        parent::startup();
//        $this->getUser()->getStorage()->setNamespace('member');
//    }
//
    protected function afterRender(){
        parent::afterRender();
        if (!$this->context->parameters['productionMode']) {
            parent::afterRender();
            $this->template->basePath .= '/member';
            $this->template->baseUri .= '/member';

        }
    }

    public function registerTexy(){
        $texy = \TexyFactory::createTexy();
        $this->template->registerHelper('texy', callback($texy, 'process'));
    }

    public function actionTexyPreview($class = false){
        if ($this->isAjax()){

            $texy = \TexyFactory::createTexy();
            $httpRequest = $this->context->getByType('Nette\Http\Request');

            $div = Html::el('div')->setHtml($texy->process($httpRequest->getPost('texy')));
            $div->id = 'texyPreview';
            if ($class) $div->class = 'texy';

            $this->sendResponse(new \Nette\Application\Responses\TextResponse($div));
        }
    }

    public function getNewMail(){
      $mail = new \Nette\Mail\Message;

      $mail->setFrom('info@vzs-jablonec.cz','VZS Jablonec')
        ->addCc('info@vzs-jablonec.cz');

      return $mail;
    }
}
