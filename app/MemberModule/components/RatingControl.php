<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.12.2016
 * Time: 16:16
 */

namespace App\MemberModule\Components;

use App\Model\RatingService;
use Nette\Application\UI\Form;
use Nette\Application\UI\Control;
use    Nette\Utils\Arrays;

class RatingControl extends Control {

	/** @var int */
	private $userId;

	/** @var int */
	private $akceId;

	/** @var RatingService */
	private $ratingService;

	/** @var bool */
	private $isOrg;

	/** @var bool */
	private $canComment;

	/**
	 * RatingControl constructor.
	 * @param int $userId
	 * @param int $akceId
	 * @param RatingService $ratingService
	 * @param bool $isOrg
	 * @param bool $canComment
	 */
	public function __construct(int $akceId, RatingService $ratingService, int $userId, bool $isOrg, bool $canComment) {
		parent::__construct();
		$this->userId = $userId;
		$this->akceId = $akceId;
		$this->ratingService = $ratingService;
		$this->isOrg = $isOrg;
		$this->canComment = $canComment;
	}

	public function render() {
		$this->template->setFile(__DIR__ . '/RatingControl.latte');
		$this->template->isOrg = $this->isOrg;
		$this->template->canComment = $this->canComment;

		$rating = $this->ratingService->getRatingArrayByAkceId($this->akceId);
		if ($rating) {
			$this->template->rating_stars = round(array_sum($rating) / count($rating));
			$this->template->rating_count = count($rating);
		}

		$ratings = $this->ratingService->getRatingByAkceId($this->akceId)->order('date_add')->fetchPairs('user_id');
		$myrating = Arrays::get($ratings, $this->userId, []);

		$this['ratingForm']->setDefaults($myrating);
		$this->template->ratings = $ratings;
		$this->template->myrating = (bool) $myrating;

		$this->template->addFilter('stars', function ($count) {
			$s = intval($count);
			return str_repeat('★', $count) . str_repeat('☆', 5 - $count);
		});

		$this->template->render();
	}

	protected function createComponentRatingForm() {
		$form = new Form;

		$form->addRadioList('rating', 'Hvězdy:', array_combine(range(5, 1), range(5, 1)))
			->getSeparatorPrototype()->setName(NULL);

		$form->addCheckbox('public', 'Veřejné')->setDefaultValue(TRUE);
		$form->addCheckbox('anonymous', 'Anonymní')->setDefaultValue(FALSE);

		$form->addTextArea('message', 'Slovní hodnocení')
			->addConditionOn($form['rating'], Form::BLANK)
			->setRequired('Vyplňte slovní hodnocení nebo hvězdy');

		$form->addSubmit('ok', 'Uložit');

		$form->onSuccess[] = [$this, 'ratingFormSubmitted'];

		return $form;
	}

	public function ratingFormSubmitted(Form $form) {
		$values = $form->getValues();

		$rating = $this->ratingService->getRatingByAkceAndMemberId($this->akceId, $this->userId);

		if ($rating) $this->ratingService->updateRatingByAkceAndMemberId($this->akceId, $this->userId, $values);
		else $this->ratingService->addRatingByAkceAndMemberId($this->akceId, $this->userId, $values);

		$this->flashMessage('Hodnocení bylo změněno');

		$this->redrawControl();
		$this->redirect('this#rating');
	}


}
