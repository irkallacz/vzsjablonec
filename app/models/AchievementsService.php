<?php


namespace App\Model;


use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

final class AchievementsService extends DatabaseService
{
	const TABLE_BADGES = 'achievement_users';
	const TABLE_ACHIEVEMENT = 'achievements';


	protected function getBadges(): Selection
	{
		return $this->database->table(self::TABLE_BADGES);
	}

	public function getAchievements(): Selection
	{
		return $this->database->table(self::TABLE_ACHIEVEMENT);
	}

	public function getAchievementById(int $id): ActiveRow
	{
		return $this->getAchievements()->get($id);
	}

	public function getBadgesForUser(int $userId): Selection
	{
		return $this->getBadges()->where('user_id', $userId);
	}

	public function getUsersForBadge(int $id): Selection
	{
		return $this->getBadges()->where('achievement_id', $id);
	}

	public function getBadgesCount(): Selection
	{
		return $this->getBadges()->select('achievement_id')
			->where('date_finish IS NOT NULL')
			->group('achievement_id');
	}

	public function getBadgesNews(DateTime $date, int $userId): Selection
	{
		return $this->database->table(self::TABLE_BADGES)
			->where('user_id', $userId)
			->where('date_finish IS NOT NULL')
			->where('date_update > ?', $date);
	}

	public function getBadgesAchievements(int $userId): array
	{
		return $this->database->query('SELECT `achievements`.`id`, `name`, `description`, `threshold`, `code`, `progress`, `event_id`, `date_finish`'.
			'FROM `achievements` LEFT JOIN `achievement_users` ON `achievements`.`id` = `achievement_users`.`achievement_id` '.
			'AND (`achievement_users`.`user_id` = ? OR `achievement_users`.`user_id` IS NULL) '.
			'WHERE enable = 1 '.
			'ORDER BY `date_finish` DESC, `progress` DESC', $userId)
			->fetchAll();

		//return $this->database->table( self::TABLE_ACHIEVEMENT)
		//	->select('id, name, description, code, event_id, :achievement_users.date AS achievement_date')
		//	->where('enable', true)
		//	->where(':achievement_users.user_id = ? OR :achievement_users.user_id IS NULL', $userId)
		//	->order(':achievement_users.date DESC');
	}

	public function getEventMembersForAchievement(int $achievement, array $sequence, array $events = [0]): ResultSet
	{
		return $this->database->query('SELECT user_id, COUNT(user_id) AS progress FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 
		  	AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) 
			GROUP BY user_id',
		$sequence, $events, $achievement);
	}

	public function getEventForAchievement(int $userId, int $count, array $sequence, array $events = [0]): ResultSet
	{
		return $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date_finish` FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? 
			ORDER BY date_start 
			LIMIT 1 OFFSET ?',
		$sequence, $events, $userId, $count - 1);
	}

	public function getEventMemberForActivity(int $achievement, bool $org): ResultSet
	{
		return $this->database->query('SELECT user_id, COUNT(user_id) AS progress FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE deleted_by IS NULL AND enable = 1 AND NOW() > date_end AND organizator = ? 
			AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date_finish` IS NOT NULL) 
			GROUP BY user_id',
				$org,  $achievement);
	}

	public function getEventForActivity(int $userId, int $count, bool $org): ResultSet
	{
		return $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date_finish` FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE deleted_by IS NULL AND enable = 1 AND organizator = ? AND user_id = ? AND NOW() > date_end 
			ORDER BY date_start LIMIT 1 OFFSET ?',
			$org, $userId, $count - 1);
	}

}