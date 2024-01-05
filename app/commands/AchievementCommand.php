<?php


namespace App\Console;


use App\Model\AchievementsService;
use Nette\Database\Context;
use Nette\Iterators\CachingIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AchievementCommand extends BaseCommand
{
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
		$members = $this->database->query('SELECT user_id FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND NOW() > date_end AND organizator = 1 AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 1) GROUP BY user_id HAVING COUNT(user_id) >= 30');
		foreach ($members as $member) {
			$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND organizator = 1 AND user_id = ? AND NOW() > date_end ORDER BY date_start LIMIT 1 OFFSET 29', $member->user_id);
			if ($event = $events->fetch()) {
				$values = iterator_to_array($event);
				$values['achievement_id'] = 1;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//activist
		$this->writeln($output, '<info>Aktivista</info>');
		$members = $this->database->query('SELECT user_id FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND NOW() > date_end AND organizator = 0 AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 2) GROUP BY user_id HAVING COUNT(user_id) >= 100');
		foreach ($members as $member) {
			$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE deleted_by IS NULL AND enable = 1 AND organizator = 0 AND user_id = ? AND NOW() > date_end ORDER BY date_start LIMIT 1 OFFSET 99', $member->user_id);
			if ($event = $events->fetch()) {
				$values = iterator_to_array($event);
				$values['achievement_id'] = 2;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//freeze
		$this->writeln($output, '<info>Omrzlík</info>');
		$members = $this->achievementsService->getMembersForAchievement(3, [899]);
		//$members = $this->database->query('SELECT user_id FROM akce_member JOIN akce ON akce_id = akce.id WHERE sequence_id = 899 AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 3) GROUP BY user_id HAVING COUNT(user_id) >= 3');
		foreach ($members as $member) {
			$events = $this->achievementsService->getEventForAchievement($member->user_id, [899]);
			//$events = $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member JOIN akce ON akce_id = akce.id WHERE sequence_id = 899 AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? ORDER BY date_start LIMIT 1 OFFSET 2', $member->user_id);
			if ($event = $events->fetch()) {
				$values = iterator_to_array($event);
				$values['achievement_id'] = 3;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//statue
		$this->writeln($output, '<info>Chlouba spolku</info>');
		$members = $this->achievementsService->getMembersForAchievement(8, [6,10,13,16,73,278], [694, 1069]);
		foreach ($members as $member) {
			$events = $this->achievementsService->getEventForAchievement($member->user_id, [6,10,13,16,73,278], [694, 1069]);
			if ($event = $events->fetch()) {
				$values = iterator_to_array($event);
				$values['achievement_id'] = 8;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//skull
		$this->writeln($output, '<info>Lovec lebek</info>');
		$members = $this->achievementsService->getMembersForAchievement(15, [495, 616, 18]);
		foreach ($members as $member) {
			$events = $this->achievementsService->getEventForAchievement($member->user_id, [495, 616, 18]);
			if ($event = $events->fetch()) {
				$values = iterator_to_array($event);
				$values['achievement_id'] = 15;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}


		//photo
		$this->writeln($output, '<info>Paparazzi</info>');
		$members = $this->database->query('SELECT created_by FROM album_photos WHERE created_by NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 11) GROUP BY created_by HAVING COUNT(id) >= 1000');
		foreach ($members as $member) {
			$photos = $this->database->query('SELECT created_by AS user_id, id AS summary, created_at AS `date` FROM album_photos WHERE created_by = ? ORDER BY `created_at` LIMIT 1 OFFSET 999', $member->created_by);
			if ($photo = $photos->fetch()) {
				$values = iterator_to_array($photo);
				$values['achievement_id'] = 11;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//swimmer
		$this->writeln($output, '<info>Plavec</info>');
		$members = $this->database->query('SELECT user_id FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 13) GROUP BY user_id HAVING COUNT(attendance_id) >= 100');
		foreach ($members as $member) {
			$attendances = $this->database->query('SELECT user_id, attendance_id AS summary, `date` FROM attendance_user JOIN attendance ON attendance_id = attendance.id WHERE user_id = ? ORDER BY `date` LIMIT 1 OFFSET 99', $member->user_id);
			if ($attendance = $attendances->fetch()) {
				$values = iterator_to_array($attendance);
				$values['achievement_id'] = 13;
				$values['date_add'] = new \DateTime();
				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//watch
		$this->writeln($output, '<info>Přesný jako hodinky</info>');
		$members = $this->database->query('SELECT user_id FROM attendance_user WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 12) GROUP BY user_id HAVING COUNT(attendance_id) >= 10');
		foreach ($members as $member) {
			//$this->writeln($output, $member->user_id);

			$attendances = $this->database->query('SELECT attendance_id, `date` FROM attendance_user JOIN attendance ON attendance_id = attendance.id WHERE user_id = ? ORDER BY `date`', $member->user_id);

			$success = false;
			$count = 1;
			foreach ($iterator = new CachingIterator($attendances) as $attendance) {
				if ($iterator->hasNext()) {
					if ($count == 10) {
						$success = true;
						break;
					}

					$date = \DateTimeImmutable::createFromMutable($attendance->date);

					//$this->writeln($output, $count, $date->format('D Y-m-d'));

					if ($date->format('N') == '3') {
						$next = $date->add(new \DateInterval('P2D'));
					} else {
						$next = $date->add(new \DateInterval('P5D'));
					}

					if ($iterator->getNextValue()->date->format('Y-m-d') == $next->format('Y-m-d')) {
						$count++;
					} else {
						$count = 1;
					}
				}
			}

			if ($success) {
				$values = [
					'user_id' => $member->user_id,
					'summary' => $attendance->attendance_id,
					'date' => $attendance->date,
					'achievement_id' => 12,
					'date_add' => new \DateTime(),
				];

				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//heart
		$this->writeln($output, '<info>Srdcař</info>');
		$members = $this->database->query('SELECT user_id FROM user_registration WHERE user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 17) GROUP BY user_id HAVING COUNT(`year`) > 10');
		foreach ($members as $member) {
			$attendances = $this->database->query('SELECT `year` FROM user_registration WHERE user_id = ? ORDER BY `year` LIMIT 1 OFFSET 10', $member->user_id);
			if ($attendance = $attendances->fetch()) {
				$values = [
					'user_id' => $member->user_id,
					'date' => new \DateTime($attendance->year . '-01-01'),
					'achievement_id' => 17,
					'date_add' => new \DateTime(),
				];

				$this->database->query('INSERT INTO achievement_users ?', $values);
				$this->writeln($output, ...array_values($values));
			}
		}

		//cloak
		$this->writeln($output, '<info>Zasvětcený</info>');
		$members = $this->database->query('SELECT id AS user_id, proper_from AS `date` FROM `user` WHERE proper_from IS NOT NULL AND id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = 18)');
		foreach ($members as $member) {
			$values = iterator_to_array($member);
			$values['achievement_id'] = 18;
			$values['date_add'] = new \DateTime();

			$this->database->query('INSERT INTO achievement_users ?', $values);
			$this->writeln($output, ...array_values($values));

		}

		$this->database->commit();
	}
}