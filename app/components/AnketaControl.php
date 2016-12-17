<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 18:45
 */

use Nette\Utils\Arrays;


class AnketaControl extends Nette\Application\UI\Control{
    /** @var AnketyService  */
    private $anketyService;

    /**
     * AnketaControl constructor.
     * @param AnketyService $anketyService
     * @param Texy $texy
     */
    public function __construct(AnketyService $anketyService){
        parent::__construct();
        $this->anketyService = $anketyService;
    }

    public function render($id){
        $this->template->setFile(__DIR__ . '/AnketaControl.latte');

        $anketa = $this->anketyService->getAnketaById($id);
        $user_id = $this->presenter->getUser()->getId();

        if ($anketa){
            $this->template->anketa = $anketa;
            $this->template->odpovedi = $anketa->related('anketa_odpoved')->order('text');

            $memberList = $this->anketyService->getMemberListByAnketaId($id);

            $this->template->mojeOdpoved = Arrays::get($memberList, $user_id, 0);
            $this->template->celkem = count($memberList);

	        $texy = \TexyFactory::createTexy();
            $this->template->registerHelper('texy', callback($texy, 'process'));
            $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');

            $this->template->render();
        }else{
            $this->flashMessage('Anekta nenalezena!','error');
        }
    }

    public function handleDeleteVote($id){
        $anketa = $this->anketyService->getAnketaById($id);
        $user_id = $this->presenter->getUser()->getId();

        if ((!$anketa)or($anketa->locked)) {
            $this->flashMessage('V této anketě nemůžete zrušit hlas','error');
        }else{
            $vote = $this->anketyService->getMemberVote($id,$user_id);

            if ($vote) {
                $this->anketyService->deleteMemberVote($id,$user_id);
                $this->flashMessage('Váš hlas byl smazán');
            }else {
                $this->flashMessage('V této anketě jste nehlasoval','error');
            }
        }
//        $this->redrawControl();
        $this->presenter->redirect('this');
    }

    public function handleVote($odpoved){
        $odpoved_id = (int) $odpoved;

        $user_id = $this->presenter->getUser()->getId();
        $odpoved = $this->anketyService->getOdpovedById($odpoved_id);
        $anketa = $this->anketyService->getAnketaById($odpoved->anketa_id);

        if ((!$anketa)or($anketa->locked)) {
            $this->flashMessage('V této anketě nemůžete hlasovat','error');
        }else {
            $odpovedi = $anketa->related('anketa_odpoved')->fetchPairs('id', 'id');

            if (in_array($odpoved_id, $odpovedi)) {
                $this->anketyService->addVote([
                    'member_id' => $user_id, 'anketa_id' => $odpoved->anketa_id, 'anketa_odpoved_id' => $odpoved_id, 'date_add' => new Datetime
                ]);
                $this->flashMessage('Váš hlas byl zaznamenán');
            }else $this->flashMessage('Pro tuto odpověď nemůžete hlasovat', 'error');
        }
//        $this->redrawControl();
        $this->presenter->redirect('this');
    }

}