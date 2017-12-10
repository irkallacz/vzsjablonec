<?php
namespace App\CronModule\Presenters;

use App\Model\UserService;
use App\Model\AkceService;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Tracy\Debugger;

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

	/**
	 * Perform all actions
	 */
	public function actionDefault() {
		$this->actionEvents();
		$this->actionFollowers();
	}

	/**
	 * Sync calendar with events database
	 */
	public function actionEvents() {
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
	}

	/**
	 * Sync all calendar followess with all users with Gmail accounts
	 */
	public function actionFollowers() {
		$members = $this->userService->getUsers(UserService::MEMBER_LEVEL)
			->where('mail LIKE ?', '%@gmail.com')
			->fetchPairs('id', 'mail');

		$rules = [];
		$alcRules = $this->calendarService->acl->listAcl(self::CALENDAR_ID)->getItems();

		//Remove followers from calendar with are not users
		foreach ($alcRules as $rule) {
			$ruleId = $rule->getId();
			$mail = $rule->getScope()->getValue();

			if (!in_array($mail, $members)) {
				$this->calendarService->acl->delete(self::CALENDAR_ID, $ruleId);
			} else {
				$rules[$ruleId] = $mail;
			}
		}

		//Set new users follows calendar
		foreach (array_diff($members, $rules) as $mail) {
			$aclRule = self::createAclRule($mail);
			$this->calendarService->acl->insert(self::CALENDAR_ID, $aclRule);
		}
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

		//Add attendees to event
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

	/**
	 * @param string $mail
	 * @return \Google_Service_Calendar_AclRule
	 */
	private static function createAclRule($mail) {
		$aclRule = new \Google_Service_Calendar_AclRule();
		$aclRule->setRole('reader');
		$scope = new \Google_Service_Calendar_AclRuleScope();
		$scope->setType('user');
		$scope->setValue($mail);
		$aclRule->setScope($scope);
		return $aclRule;
	}

}