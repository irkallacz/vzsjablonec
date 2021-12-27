<?php


namespace App\Console;

use App\Model\AkceService;
use App\Model\UserService;
use Nette\Database\Table\ActiveRow;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;


final class CalendarCommand extends BaseCommand
{
	const CALENDAR_ID = 'primary';

	/** @var AkceService */
	private $akceService;

	/** @var UserService */
	private $userService;

	/** @var Google_Service_Calendar */
	private $calendarService;

	/**
	 * CalendarCommand constructor.
	 * @param AkceService $akceService
	 * @param UserService $userService
	 * @param Google_Service_Calendar $calendarService
	 */
	public function __construct(AkceService $akceService, UserService $userService, Google_Service_Calendar $calendarService)
	{
		parent::__construct();
		$this->akceService = $akceService;
		$this->userService = $userService;
		$this->calendarService = $calendarService;
	}

	protected function configure() {
		$this->setName('cron:calendar')
			->setDescription('Sync events from database with Google Calendar and sync non domain users as calendar followers');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeln($output,'<info>Events</info>');

		$this->writeln($output, 'Updating events');

		$updateEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('date_update > NOW() - INTERVAL 1 DAY')
			->where('NOT calendarId', NULL)
			->order('date_add DESC')
			->fetchPairs('id');

		foreach ($updateEvents as $updateEvent) {
			$this->writeln($output, 'Update', $updateEvent->calendarId, $updateEvent->id);

			$event = $this->calendarService->events->get(self::CALENDAR_ID, $updateEvent->calendarId);
			$event = $this->setEvent($updateEvent, $event);
			$this->calendarService->events->update(self::CALENDAR_ID, $updateEvent->calendarId, $event);
		}

		//Attendees
		$output->writeln('Attendees');

		$attendeesEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('akce.id NOT', array_keys($updateEvents))
			->where('NOT calendarId', NULL)
			->where(':akce_member.date_add > NOW() - INTERVAL 1 DAY');

		foreach ($attendeesEvents as $attendeesEvent) {
			$this->writeln($output, 'Attendees event', $attendeesEvent->calendarId, $attendeesEvent->id);

			$event = $this->calendarService->events->get(self::CALENDAR_ID, $attendeesEvent->calendarId);
			$event = $this->setAttendees($attendeesEvent->id, $event);
			$this->calendarService->events->update(self::CALENDAR_ID, $updateEvent->calendarId, $event);
		}

		$this->writeln($output,'Add new events');

		$newEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('calendarId', NULL)
			->order('date_add DESC');

		foreach ($newEvents as $newEvent) {
			$event = $this->setEvent($newEvent, new Google_Service_Calendar_Event);

			$createdEvent = $this->calendarService->events->insert(self::CALENDAR_ID, $event);
			$newEvent->update(['calendarId' => $createdEvent->getId()]);

			$this->writeln($output, 'Add', $createdEvent->getId(), $newEvent->id);
		}

		$this->writeln($output, 'Deleting events');

		$deleteEvents = $this->akceService->getAkce()
			->where('confirm = ? OR enable = ?', FALSE, FALSE)
			->where('NOT calendarId', NULL)
			->order('date_add DESC');

		foreach ($deleteEvents as $deleteEvent) {
			$this->writeln($output, 'Delete', $deleteEvent->calendarId, $deleteEvent->id);

			$this->calendarService->events->delete(self::CALENDAR_ID, $deleteEvent->calendarId);
			$deleteEvent->update(['calendarId' => NULL]);
		}

		//Followers

		$this->writeln($output, '<info>Followers</info>');

		$futureFollowers = $this->userService->getUsers(UserService::MEMBER_LEVEL)
			->where('mail LIKE ?', '%@gmail.com')
			->fetchPairs('id', 'mail');

		$pastFollowers = $this->userService->getUsers(UserService::DELETED_LEVEL)
			->where('mail LIKE ?', '%@gmail.com')
			->fetchPairs('id', 'mail');

		$alcRules = $this->calendarService->acl->listAcl(self::CALENDAR_ID)->getItems();

		$currentFollowers = [];
		foreach ($alcRules as $rule) {
			$ruleId = $rule->getId();
			$mail = $rule->getScope()->getValue();
			$currentFollowers[$ruleId] = $mail;
		}

		$differences = UserService::getDifferences($futureFollowers, $currentFollowers);

		//Set new users to follow calendar
		foreach ($differences['add'] as $mail) {
			$this->writeln($output, 'Add follower', $mail);
			$aclRule = self::createAclRule($mail);
			$this->calendarService->acl->insert(self::CALENDAR_ID, $aclRule);
		}

		//Remove followers from calendar witch are no longer users
		foreach ($differences['delete'] as $mail) {
			if (in_array($mail, $pastFollowers, TRUE)) {
				$this->writeln($output, 'Remove follower', $mail);
				$ruleId = array_search($mail, $currentFollowers);
				$this->calendarService->acl->delete(self::CALENDAR_ID, $ruleId);;
			}
		}

		return 0;
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
		$event = $this->setAttendees($akce->id, $event);

		return $event;
	}

	private function setAttendees(int $akceId, Google_Service_Calendar_Event $event): Google_Service_Calendar_Event
	{
		$attendees = [];
		foreach ($this->userService->getUsersByAkceId($akceId)->where('NOT role', NULL) as $member) {
			$attendee = new Google_Service_Calendar_EventAttendee();
			$attendee->setDisplayName(UserService::getFullName($member));
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