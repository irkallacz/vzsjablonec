<?php

/**
 * MemberService base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class DokumentyService extends DatabaseService {

	const TABLE_DOKUMENTY = 'dokumenty';
	const TABLE_DIRECTORIES = 'dokumenty_directories';

	const DOCUMENT_DIR_ID = '0Bw4dUSMcekaVdklkS0htZWxMeHM';
	const DIR_MIME_TYPE = 'application/vnd.google-apps.folder';

	public function emptyTables() {
		$this->database->query('DELETE FROM ' . self::TABLE_DIRECTORIES);
		$this->database->query('DELETE FROM ' . self::TABLE_DOKUMENTY );
	}

	public function beginTransaction() {
		$this->database->beginTransaction();
	}

	public function commitTransaction() {
		$this->database->commit();
	}

	/**
	 * @return Selection
	 */
	public function getDokumenty() {
		return $this->database->table(self::TABLE_DOKUMENTY);
	}

	/**
	 * @return Selection
	 */
	public function getDirectories() {
		return $this->database->table(self::TABLE_DIRECTORIES);
	}

	/**
	 * @param $parent
	 * @return Selection
	 */
	public function getDirectoriesByParent($parent) {
		return $this->getDirectories()
			->where('parent_id', $parent)
			->order('id');
	}

	/**
	 * @param DateTime $date
	 * @return Selection
	 */
	public function getDokumentyNews(DateTime $date) {
		return $this->getDokumenty()
			->where('modifiedTime > ?', $date)
			->order('modifiedTime DESC');
	}

	/**
	 * @param $id
	 * @return bool|int|IRow
	 */
	public function getDirectoryById($id) {
		return $this->getDirectories()->get($id);
	}

	/**
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addDirectory($values) {
		return $this->getDirectories()->insert($values);
	}

	/**
	 * @param $id
	 * @return bool|int|IRow
	 */
	public function getDokumentById($id) {
		return $this->getDokumenty()->get($id);
	}

	/**
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addFile($values) {
		return $this->getDokumenty()->insert($values);
	}

}