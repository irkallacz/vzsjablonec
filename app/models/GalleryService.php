<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette;
use Nette\Utils\DateTime;
use Nette\Database\Table\Selection;
use Nette\Database\Table\IRow;
use Nette\Database\Table\ActiveRow;

class GalleryService {
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	public $database;

	/**
	 * GalleryService constructor.
	 * @param \Nette\Database\Context $database
	 */
	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
	}

	/**
	 * @return Selection
	 */
	public function getAlbums() {
		return $this->database->table('albums');
	}

	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getAlbumById(int $id) {
		return $this->getAlbums()->get($id);
	}

	/**
	 * @param DateTime $datetime
	 * @return Selection
	 */
	public function getAlbumNews(DateTime $datetime) {
		return $this->getAlbums()->order('created_at DESC')->where('modified_at > ?', $datetime);
	}
}