<?php


namespace App\Console;

use App\Model\AkceService;
use App\Model\UserService;
use Symfony\Component\Console\Command\Command;
use Nette\Database\Table\ActiveRow;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;


final class CalendarCommand extends Command
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
		$output->writeln('<info>Events</info>', OutputInterface::VERBOSITY_VERBOSE);

		$output->writeln('Updating events', OutputInterface::VERBOSITY_VERBOSE);

		$updateEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('date_update > NOW() - INTERVAL 1 DAY')
			->where('NOT calendarId', NULL)
			->order('date_add DESC');

		foreach ($updateEvents as $updateEvent) {
			$output->writeln(join("\t", ['Update', $updateEvent->calendarId, $updateEvent->id]), OutputInterface::VERBOSITY_VERBOSE);

			$event = $this->calendarService->events->get(self::CALENDAR_ID, $updateEvent->calendarId);
			$event = $this->setEvent($updateEvent, $event);
			$this->calendarService->events->update(self::CALENDAR_ID, $updateEvent->calendarId, $event);
		}

		$output->writeln('Add new events', OutputInterface::VERBOSITY_VERBOSE);

		$newEvents = $this->akceService->getAkce()
			->where('confirm', TRUE)
			->where('enable', TRUE)
			->where('calendarId', NULL)
			->order('date_add DESC');

		foreach ($newEvents as $newEvent) {
			$event = $this->setEvent($newEvent, new Google_Service_Calendar_Event);

			$createdEvent = $this->calendarService->events->insert(self::CALENDAR_ID, $event);
			$newEvent->update(['calendarId' => $createdEvent->getId()]);

			$output->writeln(join("\t", ['Add', $createdEvent->getId(), $newEvent->id]), OutputInterface::VERBOSITY_VERBOSE);
		}

		$output->writeln('Deleting events', OutputInterface::VERBOSITY_VERBOSE);

		$deleteEvents = $this->akceService->getAkce()
			->where('confirm = ? OR enable = ?', FALSE, FALSE)
			->where('NOT calendarId', NULL)
			->order('date_add DESC');

		foreach ($deleteEvents as $deleteEvent) {
			$output->writeln(join("\t", ['Delete', $deleteEvent->calendarId, $deleteEvent->id]), OutputInterface::VERBOSITY_VERBOSE);

			$this->calendarService->events->delete(self::CALENDAR_ID, $deleteEvent->calendarId);
			$deleteEvent->update(['calendarId' => NULL]);
		}

		$output->writeln('<info>Followers</info>', OutputInterface::VERBOSITY_VERBOSE);

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
			$output->writeln(join("\t", ['Add follower', $mail]), OutputInterface::VERBOSITY_VERBOSE);
			$aclRule = self::createAclRule($mail);
			$this->calendarService->acl->insert(self::CALENDAR_ID, $aclRule);
		}

		//Remove followers from calendar witch are no longer users
		foreach ($differences['delete'] as $mail) {
			if (in_array($mail, $pastFollowers, TRUE)) {
				$output->writeln(join("\t", ['Remove follower', $mail]), OutputInterface::VERBOSITY_VERBOSE);
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
		$attendees = [];
		foreach ($this->userService->getUsersByAkceId($akce->id)->where('NOT role', NULL) as $member) {
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