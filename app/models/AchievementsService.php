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

	public function getBadgesCount(): Selection
	{
		return $this->getBadges()->select('achievement_id AS id, COUNT(user_id) AS pocet')->group('achievement_id');
	}

	public function getBadgesNews(DateTime $date, int $userId): Selection
	{
		return $this->database->table(self::TABLE_BADGES)
			->where('user_id', $userId)
			->where('date_add > ?', $date);
	}

	public function getBadgesAchievements(int $userId): array
	{
		return $this->database->query('SELECT `achievements`.`id`, `name`, `description`, `threshold`, `code`, `event_id`, `achievement_users`.`date` AS `achievement_date`'.
			'FROM `achievements` LEFT JOIN `achievement_users` ON `achievements`.`id` = `achievement_users`.`achievement_id` '.
			'AND (`achievement_users`.`user_id` = ? OR `achievement_users`.`user_id` IS NULL) '.
			'WHERE enable = 1 '.
			'ORDER BY `achievement_users`.`date` DESC', $userId)
			->fetchAll();

		//return $this->database->table( self::TABLE_ACHIEVEMENT)
		//	->select('id, name, description, code, event_id, :achievement_users.date AS achievement_date')
		//	->where('enable', true)
		//	->where(':achievement_users.user_id = ? OR :achievement_users.user_id IS NULL', $userId)
		//	->order(':achievement_users.date DESC');
	}

	public function getMembersForAchievement(int $achievement, int $count, array $sequence, array $events = [0]): ResultSet
	{
		return $this->database->query('SELECT user_id FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 
		  	AND user_id NOT IN (SELECT user_id FROM achievement_users WHERE achievement_id = ? AND `date` IS NOT NULL) 
			GROUP BY user_id 
			HAVING COUNT(user_id) >= ?',
		$sequence, $events, $achievement, $count);
	}

	public function getEventForAchievement(int $userId, int $count, array $sequence, array $events = [0]): ResultSet
	{
		return $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? 
			ORDER BY date_start 
			LIMIT 1 OFFSET ?',
		$sequence, $events, $userId, $count - 1);
	}

}