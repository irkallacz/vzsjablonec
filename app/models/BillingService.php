<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.12.2016
 * Time: 16:18
 */

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

class BillingService extends DatabaseService {

	/**
	 * @return Selection
	 */
	public function getBilling() {
		return $this->database->table('akce_billing');
	}


	/**
	 * @param array $values
	 * @return bool|int|ActiveRow
	 */
	public function addBilling(array $values) {
		return $this->getBilling()->insert($values);
	}

	/**
	 * @param int $akce_id
	 * @return ActiveRow|IRow
	 */
	public function getBillingByAkceId(int $akce_id) {
		return $this->getBilling()->where('akce_id', $akce_id)->fetch();
	}

	/**
	 * @param ArrayHash $values
	 * @return bool|int|ActiveRow
	 */
	function addBillingItem(ArrayHash $values) {
		return $this->getBillingItems()->insert($values);
	}
	/**
	 * @return Selection
	 */
	public function getBillingItems() {
		return $this->database->table('akce_billing_items');
	}

	/**
	 * @param int $item_id
	 * @return ActiveRow|IRow
	 */
	public function getBillingItemById(int $item_id) {
		return $this->getBillingItems()->get($item_id);
	}

	/**
	 * @param int $billing_id
	 * @return Selection
	 */
	public function getBillingItemsByBillingId(int $billing_id) {
		return $this->getBillingItems()->where('billing_id', $billing_id);
	}

	/**
	 * @param int $billing_id
	 * @return int
	 */
	public function deleteBillingItems(int $billing_id) {
		return $this->getBillingItemsByBillingId($billing_id)->delete();
	}


}