<?php

namespace App\Model;

use Nette\Database\Context;
use Nette\Database\ResultSet;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class YoutubeService extends DatabaseService {

	const TABLE_VIDEA = 'videa';
	/** @var \Google_Client */
	public $googleClient;

	/** @var string */
	public $channelId;

	/**
	 * YoutubeService constructor.
	 * @param \Google_Client $googleClient
	 * @param string $channelId
	 * @param Context $database
	 */
	public function __construct(\Google_Client $googleClient, string $channelId, Context $database) {
		parent::__construct($database);
		$this->googleClient = $googleClient;
		$this->channelId = $channelId;
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
		return $this->database->query("INSERT INTO ?name ?values ON DUPLICATE KEY UPDATE ?set", self::TABLE_VIDEA, $values, ['publishedAt' => $values['publishedAt'], 'title' => $values['title']]);
	}
}