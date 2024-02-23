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
			->setDescription('Get documents and dir structure from Google Drive');
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
				$photos = $this->database->query('SELECT created_by AS user_id, id AS summary, created_at AS `date_finish` FROM album_photos WHERE created_by = ? ORDER BY `created_at` LIMIT 1 OFFSET ?', $member->created_by, $achievement->threshold - 1);
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
		$members = $this->database->query('SELECT user_id FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) GROUP BY user_id HAVING COUNT(attendance_id) >= ?', $achievement->id, $achievement->threshold);
		foreach ($members as $member) {
			//$this->writeln($output, $member->user_id);

			$attendances = $this->database->query('SELECT attendance_id, `date` FROM attendance_user JOIN attendance ON attendance_id = attendance.id WHERE user_id = ? ORDER BY `date`', $member->user_id)->fetchAll();

			$success = false;
			$count = 1;
			foreach ($iterator = new CachingIterator($attendances) as $attendance) {
				if ($iterator->hasNext()) {
					if ($count == $achievement->threshold) {
						$success = true;
						break;
					}

					if ($attendance->date->format('N') == '3') {
						$next = $attendance->date->modifyClone('next friday');
					} else {
						$next = $attendance->date->modifyClone('next wednesday');
					}

					if ($iterator->getNextValue()->date->format('Y-m-d') == $next->format('Y-m-d')) {
						$count++;
					} else {
						$count = 1;
					}
				}
			}

			if ($success) {
				$this->saveResult(['user_id' => $member->user_id, 'summary' => $attendance->attendance_id, 'date_finish' => $attendance->date], $achievement, $output ,true);
			}
		}

		//zjistit jaký je den
		if ((date('N') != '3') && (date('N') != '5')) {
			$wednesday = new \DateTime('last wednesday');
			$friday = new \DateTime('last friday');

			//zjistit kdy byl poslední trénink a ověřit že má cenu počítat progress
			if ($last_attendance = $this->database->query('SELECT id, `date` FROM attendance WHERE `date` = ? ', ($friday > $wednesday) ? $friday: $wednesday)->fetch()) {
				//spočítat progress od posledního tréninku zpět
				$members = $this->database->query('SELECT user_id FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ?) AND attendance_id = ? GROUP BY user_id', $achievement->id, $last_attendance->id);
				foreach ($members as $member) {
					$attendances = $this->database->query('SELECT attendance_id, `date` FROM attendance_user JOIN attendance ON attendance_id = attendance.id WHERE user_id = ? ORDER BY `date` DESC', $member->user_id)->fetchAll();
					$count = 1;
					foreach ($iterator = new CachingIterator($attendances) as $attendance) {
						if ($attendance->date->format('N') == '3') {
							$next = $attendance->date->modifyClone('last friday');
						} else {
							$next = $attendance->date->modifyClone('last wednesday');
						}

						if ($iterator->getNextValue()->date->format('Y-m-d') == $next->format('Y-m-d')) {
							$count++;
						} else {
							break;
						}
					}
					$this->saveResult(['user_id' => $member->user_id, 'progress' => $count], $achievement, $output);
				}
			} else {
				//pokud ne, vynulovat progress všem kdo to nemá uzavřeno
				$this->database->query('DELETE FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NULL', $achievement->id);
			}
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

		$this->database->query('INSERT INTO achievement_users ? ON DUPLICATE KEY UPDATE ?', $values, $values);
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
				$events = $this->achievementsService->getEventForAchievement($member->user_id, $achievement->threshold, $sequence, $events);
				//$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE sequence_id = 899 AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? ORDER BY date_start LIMIT 1 OFFSET 2', $member->user_id);
				if ($event = $events->fetch()) {
					$this->saveResult(iterator_to_array($event), $achievement, $output, true);
				}
			} else {
				$this->saveResult(iterator_to_array($member), $achievement, $output);
			}
		}
	}
}