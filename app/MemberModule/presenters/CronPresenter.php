<?php
namespace App\MemberModule\Presenters;

use Nette\Database\Table\ActiveRow;
use App\Model\AkceService;
use App\Model\DokumentyService;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Google_Service_Drive;
use Nette\Http\Response;

class CronPresenter extends BasePresenter {

	/** @var DokumentyService @inject */
	public $dokumentyService;

	/** @var Google_Service_Drive @inject */
	public $driveService;

	/** @var AkceService @inject */
	public $akceService;

	/** @var Google_Service_Calendar @inject */
	public $calendarService;

	public function actionDrive(){
		$this->dokumentyService->beginTransaction();
		$this->dokumentyService->emptyTables();

		$this->dokumentyService->addDirectory([
			'id' => DokumentyService::DOCUMENT_DIR_ID,
			'name' => 'Web',
			'parent' => NULL,
			'level' => 0,
		]);

		$files = $this->driveService->files->listFiles(self::getFileSearchQuery(DokumentyService::DOCUMENT_DIR_ID));
		$this->parseFiles($files->getFiles(), DokumentyService::DOCUMENT_DIR_ID, 1);

		$this->dokumentyService->commitTransaction();

		$this->template->files = $this->dokumentyService->getDokumenty()->order('directory,name');
	}

	/**
	 * @param $dir
	 * @return array
	 */
	private static function getFileSearchQuery($dir){
		if (!is_array($dir)) $dir = [$dir];

		$string = join("' or parents in '",$dir);
		$string = "parents in '".$string."'";

		return [
			'q' => $string,
			'fields' => 'files(id, name, description, mimeType, modifiedTime, parents, webContentLink, webViewLink, iconLink)',
			'orderBy' => 'folder,name'
		];
	}

	/**
	 * @param array $files
	 * @param null $parent
	 * @param int $level
	 */
	private function parseFiles(array $files, $parent = NULL, $level = 0){
		foreach($files as $file){
			if ($file->mimeType == DokumentyService::DIR_MIME_TYPE) {
				$this->dokumentyService->addDirectory([
					'id' => $file->id,
					'name' => $file->name,
					'parent' => $parent,
					'level' => $level,
				]);

				$result = $this->driveService->files->listFiles(self::getFileSearchQuery($file->id));
				$this->parseFiles($result->getFiles(), $file->id, $level+1);
			} else {
				$this->dokumentyService->addFile([
					'id' => $file->id,
					'name' => $file->name,
					'directory' => $file->parents[0],
					'description' => $file->description,
					'modifiedTime' => new DateTime($file->modifiedTime),
					'mimeType' => $file->mimeType,
					'webContentLink' => $file->webContentLink,
					'webViewLink' => $file->webViewLink,
					'iconLink' => $file->iconLink,
				]);
			}
		}
	}

	/**
	 * @param ActiveRow $akce
	 * @param Google_Service_Calendar_Event $event
	 * @return Google_Service_Calendar_Event
	 */
	private static function setEvent(ActiveRow $akce, Google_Service_Calendar_Event $event){
        $event->setSummary($akce->name);
        $event->setLocation($akce->place);
        $event->setDescription($akce->perex);
        $start = new Google_Service_Calendar_EventDateTime;
        $start->setDateTime($akce->date_start->format('c'));
        $event->setStart($start);
        $end = new Google_Service_Calendar_EventDateTime;
        $end->setDateTime($akce->date_end->format('c'));
        $event->setEnd($end);
        $event->setVisibility($akce->public ? 'public' : 'private');

        $attendees = [];
        foreach ($akce->related('akce_member') as $member){
            $attendee = new Google_Service_Calendar_EventAttendee();
            $attendee->setDisplayName($member->surname.' '.$member->name);
            $attendee->setEmail($member->mail);
            $attendee->setResponseStatus('accepted');
            $attendees[] = $attendee;
        }
        $event->setAttendees($attendees);

        return $event;
    }
  	
  	public function actionCalendar(){
        $privateCalendarId = $this->context->parameters['google']['private_calendar_id'];
        $publicCalendarId = $this->context->parameters['google']['public_calendar_id'];

        $eventList = [];

        $updateEvents = $this->akceService->getAkce()
            ->where('confirm',TRUE)
            ->where('enable',TRUE)
            ->where('date_update > NOW() - INTERVAL 1 DAY')
            ->where('NOT privateId',null);

        foreach ($updateEvents as $updateEvent) {
            $visible = $updateEvent->visible;

            $event = $this->calendarService->events->get($privateCalendarId, $updateEvent->privateId);
            $event = self::setEvent($updateEvent, $event);
            $this->calendarService->events->update($privateCalendarId, $updateEvent->privateId, $event);

            if ($visible) {
                if ($updateEvent->publicId) {
                    $event = $this->calendarService->events->get($publicCalendarId, $updateEvent->publicId);
                    $event = self::setEvent($updateEvent, $event);
                    $this->calendarService->events->update($publicCalendarId, $updateEvent->publicId, $event);
                }
                else {
                    $event = self::setEvent($updateEvent, new Google_Service_Calendar_Event);
                    $createdEvent = $this->calendarService->events->insert($publicCalendarId, $event);
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
            $event = self::setEvent($newEvent, new Google_Service_Calendar_Event);

            $createdEvent = $this->calendarService->events->insert($privateCalendarId, $event);
            $newEvent->update(['privateId' => $createdEvent->getId()]);

            if ($visible) {
                $createdEvent = $this->calendarService->events->insert($publicCalendarId, $event);
                $newEvent->update(['publicId' => $createdEvent->getId()]);
            }

            $eventList[] = $newEvent->id;
        }

        $deleteEvents = $this->akceService->getAkce()
            ->where('confirm = ? OR enable = ?',FALSE,FALSE)
            ->where('NOT privateId',null);

        foreach ($deleteEvents as $deleteEvent) {
            $this->calendarService->events->delete($privateCalendarId, $deleteEvent->privateId);
            $deleteEvent->update(['privateId' => null]);

            if (($deleteEvent->visible)and($deleteEvent->publicId)) {
                $this->calendarService->events->delete($publicCalendarId, $deleteEvent->publicId);
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
            $this->calendarService->events->delete($publicCalendarId, $privateEvent->publicId);
            $privateEvent->update(['publicId' => null]);
            $eventList[] = $privateEvent->id;
        }
    }
}