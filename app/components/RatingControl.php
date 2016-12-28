<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.12.2016
 * Time: 16:16
 */

use Nette\Application\UI\Form;
use Nette\Utils\Arrays;

class RatingControl extends \Nette\Application\UI\Control {

	/** @var int */
	private $userId;

	/** @var int */
	private $akceId;

	/** @var RatingService */
	private $ratingService;

	/** @var bool */
	private $isOrg;

	/**
	 * RatingControl constructor.
	 * @param $userId
	 * @param $akceId
	 * @param RatingService $ratingService
	 */
	public function __construct($userId, $akceId, RatingService $ratingService, $isOrg){
		parent::__construct();
		$this->userId = $userId;
		$this->akceId = $akceId;
		$this->ratingService = $ratingService;
		$this->isOrg = $isOrg;
	}


	public function render(){
	    $this->template->setFile(__DIR__ . '/RatingControl.latte');
		$this->template->isOrg = $this->isOrg;

	    $rating = $this->ratingService->getRatingArrayByAkceId($this->akceId);
	    if ($rating) {
		    $this->template->rating_stars = round(array_sum($rating)/count($rating));
		    $this->template->rating_count = count($rating);
 	    }

	    $ratings = $this->ratingService->getRatingByAkceId($this->akceId)->order('date_add')->fetchPairs('member_id');
	    $rating = Arrays::get($ratings, $this->userId, []);
//	    unset($ratings[$this->userId]);

	    $this['ratingForm']->setDefaults($rating);
	    $this->template->ratings = $ratings;
	    $this->template->myrating = $rating;

	    $this->template->registerHelper('stars', function($s){
		    $s = intval($s);
		    return str_repeat('★', $s).str_repeat('☆', 5-$s);
	    });

	    $this->template->render();
    }

	protected function createComponentRatingForm(){
		$form = new Form;

		$form->getElementPrototype()->name = 'ratingForm';

		$form->addRadioList('rating', 'Hvězdy:', array_combine(range(1,5),range(1,5)))
			->getSeparatorPrototype()->setName(NULL);

		$form->addCheckbox('public','Veřejné')->setDefaultValue(TRUE);
		$form->addCheckbox('anonymous','Anonymní')->setDefaultValue(FALSE);

		$form->addTextArea('message','Slovní hodnocení')
			->addConditionOn($form['rating'], ~Form::FILLED)
			->setRequired('Vyplňte hodnocení nebo hvězdy');

		$form->addSubmit('ok', 'Uložit');

		$form->onSuccess[] = callback($this, 'ratingFormSubmitted');

		return $form;
	}

	public function ratingFormSubmitted(Form $form){
		$values = $form->getValues();

		$rating = $this->ratingService->getRatingByAkceAndMemberId($this->akceId,$this->userId);

		if ($rating) $this->ratingService->updateRatingByAkceAndMemberId($this->akceId,$this->userId,$values);
		else $this->ratingService->addRatingByAkceAndMemberId($this->akceId,$this->userId,$values);

		$this->flashMessage('Hodnocení bylo změněno');

		$this->redrawControl();
	}


}
