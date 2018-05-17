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
use Nette\Utils\ArrayHash;

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
		return $this->database->table('album');
	}

	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getAlbumById(int $id) {
		return $this->getAlbums()->get($id);
	}

	/**
	 * @return Selection
	 */
	public function getAlbumsPhotosCount() {
		return $this->getAlbums()->select('album.id, COUNT(:album_photo.id)AS pocet')->group('album.id');
	}


	/**
	 * @param DateTime $datetime
	 * @return Selection
	 */
	public function getAlbumNews(DateTime $datetime) {
		return $this->getAlbums()->order('date_add DESC')->where('date_update > ?', $datetime);
	}

	/**
	 * @param DateTime $datetime
	 * @return Selection
	 */
	public function getPhotoNews(DateTime $datetime) {
		return $this->getAlbums()->group('album.id')->where(':album_photo.date_add > ?', $datetime);
	}

	/**
	 * @param ArrayHash $value
	 * @return bool|int|IRow|ActiveRow
	 */
	public function addAlbum(ArrayHash $value) {
		return $this->getAlbums()->insert($value);
	}

	/**
	 * @return Selection
	 */
	public function getPhotos() {
		return $this->database->table('album_photo');
	}

	/**
	 * @param int $id
	 * @return IRow
	 */
	public function getPhotoById(int $id) {
		return $this->getPhotos()->get($id);
	}

	/**
	 * @param array $value
	 * @return bool|int|IRow
	 */
	public function addPhoto(array $value) {
		return $this->getPhotos()->insert($value);
	}

	/**
	 * @param int $id
	 * @param array $values
	 * @return Nette\Database\ResultSet
	 */
	public function updatePhoto(int $id, array $values) {
		return $this->database->query('UPDATE album_photo SET', $values, 'WHERE id = ?', $id);
	}

	/**
	 * @param array $values
	 * @return int
	 */
	public function deletePhotos(array $values) {
		return $this->getPhotos()->where('id', $values)->delete();
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getPhotosByAlbumId(int $id) {
		return $this->getPhotos()->select('*')->where('album_id', $id);
	}
}