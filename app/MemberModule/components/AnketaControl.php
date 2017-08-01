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
use Nette\Application\UI\Control;
use Nette\Utils\DateTime;
use App\Model\AnketyService;

class AnketaControl extends LayerControl {
	/** @var AnketyService */
	private $anketyService;

	private $id;

	/**
	 * AnketaControl constructor.
	 * @param AnketyService $anketyService
	 * @param $id
	 */
	public function __construct($id, AnketyService $anketyService) {
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

			$memberList = $this->anketyService->getMemberListByAnketaId($this->id);

			$this->template->mojeOdpoved = Arrays::get($memberList, $userId, 0);
			$this->template->celkem = count($memberList);

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
	public function handleVote($odpoved) {
		$odpovedId = (int)$odpoved;

		$userId = $this->presenter->getUser()->getId();
		$odpoved = $this->anketyService->getOdpovedById($odpovedId);
		$anketa = $this->anketyService->getAnketaById($odpoved->anketa_id);

		if ((!$anketa) or ($anketa->locked)) {
			throw new BadRequestException('V této anketě nemůžete hlasovat');
		} else {
			$odpovedi = $anketa->related('anketa_odpoved')->fetchPairs('id', 'id');

			if (in_array($odpovedId, $odpovedi)) {
				$this->anketyService->addVote([
					'member_id' => $userId, 'anketa_id' => $odpoved->anketa_id, 'anketa_odpoved_id' => $odpovedId, 'date_add' => new Datetime
				]);
				$this->flashMessage('Váš hlas byl zaznamenán');
			} else throw new BadRequestException('Pro tuto odpověď nemůžete hlasovat');
		}
		$this->redrawControl('flash');
		$this->redrawControl('odpovedi');
		//$this->presenter->redirect('this');
	}

}