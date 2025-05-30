<?php


namespace App\Console;


use App\Model\AchievementsService;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Iterators\CachingIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AchievementCommand extends BaseCommand
{
	const YEAR = 365;

	/** @var Context */
	public $database;

	/** @var AchievementsService */
	public $achievementsService;

	/**
	 * AchievementCommand constructor.
	 * @param Context $database
	 * @param AchievementsService $achievementsService
	 */
	public function __construct(Context $database, AchievementsService $achievementsService)
	{
		parent::__construct();
		$this->database = $database;
		$this->achievementsService = $achievementsService;
	}

	protected function configure() {
		$this->setName('cron:achievement')
			->setDescription('Count requirements and progress for members achievements');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeln($output, '<info>Achievement Commnand</info>');
		$this->database->beginTransaction();

		//org
		$this->writeln($output, '<info>Ogr</info>');
		$this->eventMemberActivity(1, true, $output);

		//activist
		$this->writeln($output, '<info>Aktivista</info>');
		$this->eventMemberActivity(2, false, $output);

		//freeze
		$this->writeln($output, '<info>Omrzlík</info>');
		$this->eventMember(3, $output, [899]);

		//statue
		$this->writeln($output, '<info>Chlouba spolku</info>');
		$this->eventMember(8, $output, [6,10,13,16,73,278], [694, 1069]);

		//skull
		$this->writeln($output, '<info>Lovec lebek</info>');
		$this->eventMember(15, $output, [495, 616, 18]);

		//agent
		$this->writeln($output, '<info>Agent</info>');
		$this->eventMember(27, $output, [34]);

		//diver
		$this->writeln($output, '<info>Podvodník</info>');
		$achievement = $this->achievementsService->getAchievementById(9);
		$members = $this->database->query('SELECT member_id AS user_id, date_start AS `date_finish` FROM `qualification_members` WHERE `qualification_id` IN (2,3) AND `member_id` NOT IN (SELECT `user_id` FROM `achievement_users` WHERE `achievement_id` = ? AND `date_finish` IS NOT NULL) GROUP BY member_id', $achievement->id);
		foreach ($members as $member) {
			$this->saveResult(iterator_to_array($member), $achievement, $output, true);
		}

		//boat
		$this->writeln($output, '<info>Lodník</info>');
		$achievement = $this->achievementsService->getAchievementById(10);
		$members = $this->database->query('SELECT member_id AS user_id, date_start AS `date_finish` FROM `qualification_members` WHERE `qualification_id` = 1 AND `type` LIKE "%M%" AND `type` NOT LIKE "%M20%" AND `member_id` NOT IN (SELECT `user_id` FROM `achievement_users` WHERE `achievement_id` = ? AND `date_finish` IS NOT NULL) GROUP BY member_id', $achievement->id);
		foreach ($members as $member) {
			$this->saveResult(iterator_to_array($member), $achievement, $output, true);
		}

		//sail
		$this->writeln($output, '<info>Plachťák</info>');
		$achievement = $this->achievementsService->getAchievementById(14);
		$members = $this->database->query('SELECT member_id AS user_id, date_start AS `date_finish` FROM `qualification_members` WHERE `qualification_id` = 1 AND `type` LIKE "%S%" AND `type` NOT LIKE "%S20%" AND `member_id` NOT IN (SELECT `user_id` FROM `achievement_users` WHERE `achievement_id` = ? AND `date_finish` IS NOT NULL) GROUP BY member_id', $achievement->id);
		foreach ($members as $member) {
			$this->saveResult(iterator_to_array($member), $achievement, $output, true);
		}

		//truck
		$this->writeln($output, '<info>Tahoun</info>');
		$achievement = $this->achievementsService->getAchievementById(28);
		$members = $this->database->query('SELECT member_id AS user_id, date_start AS `date_finish` FROM `qualification_members` WHERE `qualification_id` = 4 AND `type` LIKE "%E" AND `member_id` NOT IN (SELECT `user_id` FROM `achievement_users` WHERE `achievement_id` = ? AND `date_finish` IS NOT NULL) GROUP BY member_id', $achievement->id);
		foreach ($members as $member) {
			$this->saveResult(iterator_to_array($member), $achievement, $output, true);
		}

		//officer
		$this->writeln($output, '<info>Vyšší šarže</info>');
		$achievement = $this->achievementsService->getAchievementById(4);
		$members = $this->database->query('SELECT `user_id`, FLOOR(SUM(DATEDIFF(IF(`date_end` IS NULL, CURDATE(), `date_end`), `date_start`)) / ?) AS progress FROM `user_member_function` WHERE `user_id` NOT IN (SELECT `user_id` FROM `achievement_users` WHERE `achievement_id` = ? AND `date_finish` IS NOT NULL) GROUP BY `user_id`', self::YEAR, $achievement->id);
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$intervals = $this->database->query('SELECT `date_start`, DATEDIFF(IF(`date_end` IS NULL, CURDATE(), `date_end`), `date_start`) AS `days` FROM `user_member_function` WHERE `user_id` = ? ORDER BY `date_start`', $member->user_id);
				$sum = 0;
				foreach ($intervals as $interval) {
					if ($sum + $interval->days > $achievement->threshold * self::YEAR) {
						$days = ($sum) ? self::YEAR * $achievement->threshold - $sum : $interval->days - self::YEAR * $achievement->threshold;
						$date = $interval->date_start->modifyClone("+ $days days");
						$this->saveResult(['user_id' => $member->user_id, 'date_finish' => $date], $achievement, $output, true);
						break;
					}
					$sum += $interval->days;
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}

		//photo
		$this->writeln($output, '<info>Paparazzi</info>');
		$achievement = $this->achievementsService->getAchievementById(11);
		$members = $this->database->query('SELECT created_by AS user_id, COUNT(id) AS progress FROM album_photos WHERE created_by IS NOT NULL AND created_by NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) GROUP BY created_by', $achievement->id);
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$photos = $this->database->query('SELECT created_by AS user_id, id AS summary, created_at AS `date_finish` FROM album_photos WHERE created_by = ? ORDER BY `created_at` LIMIT 1 OFFSET ?', $member->user_id, $achievement->threshold - 1);
				if ($photo = $photos->fetch()) {
					$this->saveResult(iterator_to_array($photo), $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}

		//swimmer
		$this->writeln($output, '<info>Plavec</info>');
		$achievement = $this->achievementsService->getAchievementById(13);
		$members = $this->database->query('SELECT user_id, COUNT(attendance_id) AS progress FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) GROUP BY user_id', $achievement->id);
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$attendances = $this->database->query('SELECT user_id, attendance_id AS summary, `date` AS `date_finish` FROM attendance_user JOIN attendance ON attendance_id = attendance.id WHERE user_id = ? ORDER BY `date` LIMIT 1 OFFSET ?', $member->user_id, $achievement->threshold - 1);
				if ($attendance = $attendances->fetch()) {
					$this->saveResult(iterator_to_array($attendance), $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}

		//watch
		$this->writeln($output, '<info>Přesný jako hodinky</info>');
		$achievement = $this->achievementsService->getAchievementById(12);

		//vytáhneme seznam uživatelů kteří mají alespoň 10 účastí na tréninku a nemají odznak
		$members = $this->database->query('SELECT user_id FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) GROUP BY user_id HAVING COUNT(attendance_id) >= ?', $achievement->id, $achievement->threshold);
		foreach ($members as $member) {
			//pro každého z nich vytáhneme seznam termínů kdy byli na tréninku jako pole
			$query = $this->database->query('SELECT `attendance`.`id`, DATE_FORMAT(`date`, "%Y-%m-%d") AS `datetime` FROM `attendance` INNER JOIN `attendance_user` ON `attendance`.`id` = `attendance_id` WHERE `user_id` = ? ORDER BY `date`', $member->user_id);
			$dates = $query->fetchPairs('datetime', 'id');

			if (!count($dates)) {
				continue;
			}

			//vybereme min a max
			$interval = new \DateInterval('P1D');
			$datesStart = new \DateTimeImmutable(array_key_first($dates));
			$datesEnd = new \DateTimeImmutable(array_key_last($dates));
			$datesEnd = $datesEnd->add($interval);

			$count = 0;
			$max = 0;

			foreach (new \DatePeriod($datesStart, $interval, $datesEnd) as $date) {
				if (!in_array($date->format('N'), [3, 5])) {
					continue;
				}

				if (array_key_exists($date->format('Y-m-d'), $dates)) {
					$count++;
				} else {
					if ($count > $max) {
						$max = $count;
					}
					$count = 0;
				}

				if ($count == 10) {
					$this->saveResult(['user_id' => $member->user_id, 'summary' => $dates[$date->format('Y-m-d')], 'date_finish' => $date], $achievement, $output,true);
					continue 2;
				}
			}

			$this->saveResult(['user_id' => $member->user_id, 'progress' => $max], $achievement, $output);
		}

		//heart
		$this->writeln($output, '<info>Srdcař</info>');
		$achievement = $this->achievementsService->getAchievementById(17);
		$members = $this->database->query('SELECT user_id, COUNT(`year`) AS progress FROM user_registration WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) GROUP BY user_id HAVING COUNT(`year`) > 1', $achievement->id);
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$attendances = $this->database->query('SELECT `year` FROM user_registration WHERE user_id = ? ORDER BY `year` LIMIT 1 OFFSET 10', $member->user_id);
				if ($attendance = $attendances->fetch()) {
					$this->saveResult(['user_id' => $member->user_id, 'date_finish' => new \DateTime($attendance->year . '-01-01')], $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}

		//cloak
		$this->writeln($output, '<info>Zasvětcený</info>');
		$achievement = $this->achievementsService->getAchievementById(18);
		$members = $this->database->query('SELECT id AS user_id, proper_from AS `date_finish` FROM `user` WHERE proper_from IS NOT NULL AND id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL)', $achievement->id);
		foreach ($members as $member) {
			$this->saveResult(iterator_to_array($member), $achievement, $output, true);
		}

		$this->database->commit();
	}

	protected function saveResult(array $values, ActiveRow $achievement, OutputInterface $output, bool $finish = false)
	{
		$values['achievement_id'] = $achievement->id;
		$values['date_update'] = new \DateTime();

		if ($finish) {
			$values['progress'] = $achievement->threshold;
		}

		if ($row = $this->database->query('SELECT id FROM achievement_users WHERE achievement_id = ? AND user_id = ?', $achievement->id, $values['user_id'])->fetch()) {
			$this->database->query('UPDATE achievement_users SET', $values, 'WHERE id = ?', $row->id);
		} else {
			$this->database->query('INSERT INTO achievement_users ?', $values);
		}

		$this->writeln($output, ...array_values($values));
	}

	protected function eventMemberActivity(int $achievement, bool $org, OutputInterface $output)
	{
		$achievement = $this->achievementsService->getAchievementById($achievement);
		$members = $this->achievementsService->getEventMemberForActivity($achievement->id, $org);
		//$members = $this->database->query('SELECT user_id FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND NOW() > date_end AND organizator = 1 AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date` IS NOT NULL) GROUP BY user_id HAVING COUNT(user_id) >= ?', $achievement->id, $achievement->threshold);
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$events = $this->achievementsService->getEventForActivity($member->user_id, $achievement->threshold, $org);
				//$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND organizator = 1 AND user_id = ? AND NOW() > date_end ORDER BY date_start LIMIT 1 OFFSET ?', $member->user_id, $achievement->threshold - 1);
				if ($event = $events->fetch()) {
					$this->saveResult(iterator_to_array($event), $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}
	}

	protected function eventMember(int $achievement, OutputInterface $output, array $sequence, array $events = [0])
	{
		$achievement = $this->achievementsService->getAchievementById($achievement);
		$members = $this->achievementsService->getEventMembersForAchievement($achievement->id, $sequence, $events);
		//$members = $this->database->query('SELECT user_id FROM akce_member JOIN akce ON akce_id = akce.id WHERE sequence_id = 899 AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 3) GROUP BY user_id HAVING COUNT(user_id) >= 3');
		foreach ($members as $member) {
			if ($member->progress >= $achievement->threshold) {
				$items = $this->achievementsService->getEventForAchievement($member->user_id, $achievement->threshold, $sequence, $events);
				//$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE sequence_id = 899 AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? ORDER BY date_start LIMIT 1 OFFSET 2', $member->user_id);
				if ($event = $items->fetch()) {
					$this->saveResult(iterator_to_array($event), $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}
	}
}