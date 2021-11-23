<?php

namespace App\Model;

use Nette\Database\ResultSet;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class YoutubeService extends DatabaseService {

	const TABLE_VIDEA = 'videa';

	public function emptyTable() {
		$this->database->query('DELETE FROM ' . self::TABLE_VIDEA );
	}

	/**
	 * @return Selection
	 */
	public function getVideo() {
		return $this->database->table(self::TABLE_VIDEA);
	}

	/**
	 * @param DateTime $date
	 * @return Selection
	 */
	public function getVideoNews(DateTime $date) {
		return $this->getVideo()
			->where('publishedAt > ?', $date)
			->order('publishedAt DESC');
	}

	/**
	 * @param array $values
	 * @return ResultSet
	 */
	public function addFile(array $values) {
		return $this->database->query("INSERT INTO ?name ?values", self::TABLE_VIDEA, $values);
	}
}