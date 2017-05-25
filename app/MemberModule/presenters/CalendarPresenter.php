<?php
namespace App\MemberModule\Presenters;

use App\Model\AkceService;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Nette\Database\Table\ActiveRow;

class CalendarPresenter extends BasePresenter{

	/** @var AkceService @inject */
	public $akceService;

	public function renderDefault(){
	
		$this->template->akce = $this->akceService->getAkce()
			->where('confirm',1)
			->where('visible',1)
			->order('date_start DESC');

		$httpResponse = $this->context->getByType('Nette\Http\Response');

		$this->template->setFile(__DIR__ . '/../templates/Akce.vcal.latte');

    	$httpResponse->setHeader('Content-type','text/calendar; charset=utf-8');
  	}

  	private function setEvent(ActiveRow $akce, Google_Service_Calendar_Event $event){
		$event->setSummary($akce->name);
		$event->setLocation($akce->place);
		$event->setDescription($akce->perex);
		$start = new Google_Service_Calendar_EventDateTime;
		$start->setDateTime($akce->date_start->format('c'));
		$event->setStart($start);
		$end = new Google_Service_Calendar_EventDateTime;
		$end->setDateTime($akce->date_end->format('c'));
		$event->setEnd($end);
		
		return $event;
  	}
  	
  	public function actionGoogle(){
        $privateCalendarId = $this->context->parameters['google']['private_calendar_id'];
        $publicCalendarId = $this->context->parameters['google']['public_calendar_id'];
        $account = $this->context->parameters['google']['account'];

        $client = new Google_Client();
        //putenv('GOOGLE_APPLICATION_CREDENTIALS='.APP_DIR.'/config/google_api_service.json');
        $client->setAuthConfig(APP_DIR.'/config/google_api_service.json');
        //$client->useApplicationDefaultCredentials();

        $client->setSubject($account);

        $client->setScopes([
            'https://www.googleapis.com/auth/calendar'
        ]);

		$service = new Google_Service_Calendar($client);

        $eventList = [];

        $updateEvents = $this->akceService->getAkce()
            ->where('confirm',TRUE)
            ->where('enable',TRUE)
            ->where('date_update > NOW() - INTERVAL 1 DAY')
            ->where('NOT privateId',null);

        foreach ($updateEvents as $updateEvent) {
            $visible = $updateEvent->visible;

            $event = $service->events->get($privateCalendarId, $updateEvent->privateId);
            $event = $this->setEvent($updateEvent,$event);
            $service->events->update($privateCalendarId, $updateEvent->privateId, $event);

            if ($visible) {
                if ($updateEvent->publicId) {
                    $event = $service->events->get($publicCalendarId, $updateEvent->publicId);
                    $event = $this->setEvent($updateEvent,$event);
                    $service->events->update($publicCalendarId, $updateEvent->publicId, $event);
                }
                else {
                    $event = $this->setEvent($updateEvent, new Google_Service_Calendar_Event);
                    $createdEvent = $service->events->insert($publicCalendarId, $event);
                    $updateEvent->update(array('privateId' => $createdEvent->getId()));
                }
            }

            $eventList[] = $updateEvent->id;
        }

        $newEvents = $this->akceService->getAkce()
            ->where('confirm',TRUE)
            ->where('enable',TRUE)
            ->where('privateId',null);

        foreach ($newEvents as $newEvent) {
            $visible = $newEvent->visible;
            $event = $this->setEvent($newEvent, new Google_Service_Calendar_Event);

            $createdEvent = $service->events->insert($privateCalendarId, $event);
            $newEvent->update(['privateId' => $createdEvent->getId()]);

            if ($visible) {
                $createdEvent = $service->events->insert($publicCalendarId, $event);
                $newEvent->update(['publicId' => $createdEvent->getId()]);
            }

            $eventList[] = $newEvent->id;
        }

        $deleteEvents = $this->akceService->getAkce()
            ->where('confirm = ? OR enable = ?',FALSE,FALSE)
            ->where('NOT privateId',null);

        foreach ($deleteEvents as $deleteEvent) {
            $service->events->delete($privateCalendarId, $deleteEvent->privateId);
            $deleteEvent->update(['privateId' => null]);

            if (($deleteEvent->visible)and($deleteEvent->publicId)) {
                $service->events->delete($publicCalendarId, $deleteEvent->publicId);
                $deleteEvent->update(['publicId' => null]);
            }

            $eventList[] = $deleteEvent->id;
        }

        $privateEvents = $this->akceService->getAkce()
            ->where('confirm',TRUE)
            ->where('enable',TRUE)
            ->where('visible',FALSE)
            ->where('NOT publicId',null);

        foreach ($privateEvents as $privateEvent) {
            $service->events->delete($publicCalendarId, $privateEvent->publicId);
            $privateEvent->update(['publicId' => null]);
            $eventList[] = $privateEvent->id;
        }
    }
}