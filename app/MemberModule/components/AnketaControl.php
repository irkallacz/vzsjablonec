<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 18:45
 */

namespace App\MemberModule\Components;

use Nette\Application\BadRequestException;
use Nette\Utils\Arrays;
use App\Model\AnketyService;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class AnketaControl extends LayerControl {
	/** @var AnketyService */
	private $anketyService;

	private $id;

	/**
	 * AnketaControl constructor.
	 * @param AnketyService $anketyService
	 * @param int $id
	 */
	public function __construct(int $id, AnketyService $anketyService) {
		parent::__construct();
		$this->anketyService = $anketyService;
		$this->id = $id;
	}


	public function render() {
		$this->template->setFile(__DIR__ . '/AnketaControl.latte');

		$anketa = $this->anketyService->getAnketaById($this->id);
		$userId = $this->presenter->getUser()->getId();

		if ($anketa) {
			$this->template->anketa = $anketa;
			$this->template->odpovedi = $anketa->related('anketa_odpoved')->order('text');

			$this->template->mojeOdpoved = $this->anketyService->getOdpovedIdByAnketaId($this->id, $userId);

			$odpovediCount = $this->anketyService->getOdpovediCountByAnketaId($this->id);
			$this->template->celkem = array_sum($odpovediCount);
			$this->template->max = max($odpovediCount);
			$this->template->odpovediCount = $odpovediCount;

			$this->template->render();
		} else {
			throw new BadRequestException('Anekta nenalezena!');
		}
	}

	/**
	 * @param int $id
	 * @allow(member)
	 */
	public function handleDeleteVote($id) {
		$anketa = $this->anketyService->getAnketaById($id);
		$userId = $this->presenter->getUser()->getId();

		if ((!$anketa) or ($anketa->locked)) {
			throw new BadRequestException('V této anketě nemůžete zrušit hlas');
		} else {
			$vote = $this->anketyService->getMemberVote($id, $userId);

			if ($vote) {
				$this->anketyService->deleteMemberVote($id, $userId);
				$this->flashMessage('Váš hlas byl smazán');
			} else {
				throw new BadRequestException('V této anketě jste nehlasoval');
			}
		}
		$this->redrawControl('flash');
		$this->redrawControl('odpovedi');
//        $this->presenter->redirect('this');
	}

	/**
	 * @param int $odpoved
	 * @allow(member)
	 */
	public function handleVote(int $odpoved) {
		$odpovedId = $odpoved;

		$userId = $this->presenter->getUser()->getId();
		$odpoved = $this->anketyService->getOdpovedById($odpovedId);
		$anketa = $this->anketyService->getAnketaById($odpoved->anketa_id);

		if ((!$anketa) or ($anketa->locked)) {
			throw new BadRequestException('V této anketě nemůžete hlasovat');
		} else {
			$odpovedi = $anketa->related('anketa_odpoved')->fetchPairs('id', 'id');

			if (in_array($odpovedId, $odpovedi)) {
				$this->anketyService->addVote([
					'user_id' => $userId, 'anketa_id' => $odpoved->anketa_id, 'anketa_odpoved_id' => $odpovedId, 'date_add' => new DateTime()
				]);
				$this->flashMessage('Váš hlas byl zaznamenán');
			} else throw new BadRequestException('Pro tuto odpověď nemůžete hlasovat');
		}
		$this->redrawControl('flash');
		$this->redrawControl('odpovedi');
		//$this->presenter->redirect('this');
	}

}