<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 18:45
 */

namespace App\MemberModule\Components;

use Nette\Application\BadRequestException;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use App\Model\AnketyService;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class AnketaControl extends LayerControl {

	/**
	 * @var AnketyService
	 */
	private $anketyService;

	/**
	 * @var int
	 */
	private $surveyId;

	/**
	 * @var int
	 */
	private $userId;

	/**
	 * @var ActiveRow|IRow
	 */
	private $survey;

	/**
	 * @var Selection
	 */
	private $choices;

	/**
	 * AnketaControl constructor.
	 * @param int $surveyId
	 * @param AnketyService $anketyService
	 * @param int $userId
	 * @throws BadRequestException
	 */
	public function __construct(int $surveyId, AnketyService $anketyService, int $userId) {
		parent::__construct();
		$this->anketyService = $anketyService;
		$this->surveyId = $surveyId;
		$this->userId = $userId;

		$survey = $this->anketyService->getAnketaById($this->surveyId);

		if ($survey) {
			$this->survey = $survey;
			$this->choices = $survey->related('anketa_odpoved')->order('text');
		} else {
			throw new BadRequestException('Anekta nenalezena!');
		}

	}

	private function beforeRender(){
		$this->template->setFile(__DIR__ . '/AnketaControl.latte');

		$this->template->survey = $this->survey;
		$this->template->choices = $this->choices;

		$this->template->selectedChoice = $this->anketyService->getOdpovedIdByAnketaId($this->surveyId, $this->userId);

		$votesCount = $this->anketyService->getOdpovediCountByAnketaId($this->surveyId);
		$this->template->votesCount = $votesCount;

		$this->template->total = array_sum($votesCount);
		$this->template->max = count($votesCount) ? max($votesCount) : 0;

		$this->template->votes = $this->survey->related('anketa_member')->order('date_add');
	}

	/**
	 * @throws BadRequestException
	 */
	public function render() {
		$this->beforeRender();
		$this->template->render();
	}

	/**
	 * @throws BadRequestException
	 */
	public function renderWhole() {
		$this->beforeRender();
		$this->template->showList = TRUE;
		$this->template->render();
	}

	/**
	 * @allow(member)
	 * @throws BadRequestException
	 */
	public function handleDeleteVote() {
		if ($this->survey->locked) {
			throw new BadRequestException('V této anketě nemůžete zrušit hlas');
		} else {
			$vote = $this->anketyService->getMemberVote($this->surveyId, $this->userId);

			if ($vote) {
				$this->anketyService->deleteMemberVote($this->surveyId, $this->userId);
				$this->flashMessage('Váš hlas byl smazán');
			} else {
				throw new BadRequestException('V této anketě jste nehlasoval');
			}
		}
		$this->redrawControl('flash');
		$this->redrawControl('choices');
		$this->redrawControl('list');
	}

	/**
	 * @param int $choiceId
	 * @allow(member)
	 * @throws BadRequestException
	 */
	public function handleVote(int $choiceId) {

		if ($this->survey->locked) {
			throw new BadRequestException('V této anketě nemůžete hlasovat');
		} else {
			if (in_array($choiceId, $this->choices->fetchPairs('id', 'id'))) {
				$this->anketyService->addVote([
					'user_id' => $this->userId, 'anketa_id' => $this->surveyId, 'anketa_odpoved_id' => $choiceId, 'date_add' => new DateTime()
				]);
				$this->flashMessage('Váš hlas byl zaznamenán');
			} else throw new BadRequestException('Pro tuto odpověď nemůžete hlasovat');
		}
		$this->redrawControl('flash');
		$this->redrawControl('choices');
		$this->redrawControl('list');
	}

}