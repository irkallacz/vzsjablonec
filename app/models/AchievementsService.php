<?php


namespace App\Model;


use Nette\Database\ResultSet;
use Nette\Database\Table\Selection;

final class AchievementsService extends DatabaseService
{
	const TABLE_BADGES = 'achievement_users';
	const TABLE_ACHIEVEMENT = 'achievements';

	/**
	 * @return Selection
	 */
	public function getBadges(int $userId) {
		return $this->database->table(self::TABLE_BADGES)->where('user_id', $userId);
	}

	/**
	 * @return Selection
	 */
	public function getAchievements() {
		return $this->database->table(self::TABLE_BADGES)->select('achievement_id AS id, COUNT(user_id) AS pocet')->group('achievement_id');
	}

	public function getBadgesAchievements(int $userId) {
		return $this->database->query('SELECT `id`, `name`, `description`, `code`, `event_id`, `'.self::TABLE_BADGES.'`.`date` AS `achievement_date`'.
			'FROM `'.self::TABLE_ACHIEVEMENT.'` LEFT JOIN `'.self::TABLE_BADGES.'` ON `achievements`.`id` = `'.self::TABLE_BADGES.'`.`achievement_id` '.
			'AND (`'.self::TABLE_BADGES.'`.`user_id` = ? OR `'.self::TABLE_BADGES.'`.`user_id` IS NULL) '.
			'WHERE enable = 1 '.
			'ORDER BY `'.self::TABLE_BADGES.'`.`date` DESC', $userId)
			->fetchAll();

		//return $this->database->table( self::TABLE_ACHIEVEMENT)
		//	->select('id, name, description, code, event_id, :achievement_users.date AS achievement_date')
		//	->where('enable', true)
		//	->where(':achievement_users.user_id = ? OR :achievement_users.user_id IS NULL', $userId)
		//	->order(':achievement_users.date DESC');
	}

	public function getMembersForAchievement(int $achievement, array $sequence, array $events = [0], int $count = 3): ResultSet
	{
		return $this->database->query('SELECT user_id FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 
		  	AND user_id NOT IN (SELECT user_id FROM ' . self::TABLE_BADGES . ' WHERE achievement_id = ?) 
			GROUP BY user_id 
			HAVING COUNT(user_id) >= ?',
		$sequence, $events, $achievement, $count);
	}

	public function getEventForAchievement(int $userId, array $sequence, array $events = [0], int $offset = 2): ResultSet
	{
		return $this->database->query('SELECT user_id, akce_id AS event_id, date_end AS `date` FROM akce_member 
    		JOIN akce ON akce_id = akce.id 
			WHERE (sequence_id IN (?) OR akce_id IN (?)) AND NOW() > date_end AND deleted_by IS NULL AND enable = 1 AND user_id = ? 
			ORDER BY date_start 
			LIMIT 1 OFFSET ?',
		$sequence, $events, $userId, $offset);
	}

}