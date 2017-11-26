<?php
namespace App\CronModule\Presenters;

use App\Model\UserService;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use App\Model\AkceService;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;

/**
 * Class CronPresenter
 * @package App\MemberModule\presenters
 */
class CalendarPresenter extends Presenter {

	const CALENDAR_ID = 'primary';

	/** @var AkceService @inject */
	public $akceService;

	/** @var UserService @inject */
	public $userService;

	/** @var Google_Service_Calendar @inject */
	public $calendarService;

	public function actionDefault() {
		$eventList = [];

		//Update events
		$updateEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('date_update > NOW() - INTERVAL 1 DAY')
			->where('NOT calendarId', NULL)
			->order('date_add DESC');

		foreach ($updateEvents as $updateEvent) {
			$event = $this->calendarService->events->get(self::CALENDAR_ID, $updateEvent->calendarId);
			$event = $this->setEvent($updateEvent, $event);
			$this->calendarService->events->update(self::CALENDAR_ID, $updateEvent->calendarId, $event);

			$eventList[] = $updateEvent->id;
		}

		//Add new events
		$newEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('calendarId', NULL)
			->order('date_add DESC');

		foreach ($newEvents as $newEvent) {
			$event = $this->setEvent($newEvent, new Google_Service_Calendar_Event);

			$createdEvent = $this->calendarService->events->insert(self::CALENDAR_ID, $event);
			$newEvent->update(['calendarId' => $createdEvent->getId()]);

			$eventList[] = $newEvent->id;
		}

		//Remove deleted events
		$deleteEvents = $this->akceService->getAkce()
			->where('confirm = ? OR enable = ?', FALSE, FALSE)
			->where('NOT calendarId', NULL)
			->order('date_add DESC');

		foreach ($deleteEvents as $deleteEvent) {
			$this->calendarService->events->delete(self::CALENDAR_ID, $deleteEvent->calendarId);
			$deleteEvent->update(['calendarId' => NULL]);

			$eventList[] = $deleteEvent->id;
		}

		//Add members to calendar
		$members = $this->userService->getUsers(UserService::MEMBER_LEVEL)
			->where('mail LIKE ?', '%@gmail.com');

//		foreach ($members as $member){
//			$aclRule = self::createAclRule($member);
//			$this->calendarService->acl->insert(self::CALENDAR_ID, $aclRule);
//		}
	}

	/**
	 * @param ActiveRow $akce
	 * @param Google_Service_Calendar_Event $event
	 * @return Google_Service_Calendar_Event
	 */
	private function setEvent(ActiveRow $akce, Google_Service_Calendar_Event $event) {
		$event->setSummary($akce->name);
		$event->setLocation($akce->place);
		$event->setDescription($akce->perex);
		$start = new Google_Service_Calendar_EventDateTime;
		$start->setDateTime($akce->date_start->format('c'));
		$event->setStart($start);
		$end = new Google_Service_Calendar_EventDateTime;
		$end->setDateTime($akce->date_end->format('c'));
		$event->setEnd($end);
		$event->setVisibility($akce->visible ? 'public' : 'private');

		$attendees = [];
		foreach ($this->userService->getUsersByAkceId($akce->id)->where('NOT role', NULL) as $member) {
			$attendee = new Google_Service_Calendar_EventAttendee();
			$attendee->setDisplayName($member->surname . ' ' . $member->name);
			$attendee->setEmail($member->mail);
			$attendee->setResponseStatus('accepted');
			$attendees[] = $attendee;
		}
		$event->setAttendees($attendees);

		return $event;
	}

	private static function createAclRule(ActiveRow $member) {
		$aclRule = new \Google_Service_Calendar_AclRule();
		$aclRule->setRole('reader');
		$scope = new \Google_Service_Calendar_AclRuleScope();
		$scope->setType('user');
		$scope->setValue($member->mail);
		$aclRule->setScope($scope);
		return $aclRule;
	}

}