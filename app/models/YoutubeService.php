<?php

namespace App\Model\YoutubeService;

namespace App\Model;

use Nette\Database\Context;
use Nette\Database\ResultSet;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class YoutubeService extends DatabaseService {

	const TABLE_VIDEA = 'videa';
	const CHANNEL_ID = 'UCR9cGiK9bpjsOBB3OjBOkDg';

	/** @var \Google_Client */
	public $googleClient;

	/**
	 * YoutubeService constructor.
	 * @param \Google_Client $googleClient
	 * @param Context $database
	 */
	public function __construct(\Google_Client $googleClient, Context $database) {
		parent::__construct($database);
		$this->googleClient = $googleClient;
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
		return $this->database->query("INSERT INTO " . self::TABLE_VIDEA, $values, "ON DUPLICATE KEY UPDATE publishedAt = ", $values['publishedAt']);
	}
}