<?php


namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

final class InvoiceService extends DatabaseService
{
	const TABLE_NAME = 'invoices';

	/**
	 * @return Selection
	 */
	public function getInvoices() {
		return $this->database->table(self::TABLE_NAME);
	}

	/**
	 * @param int $id
	 * @return false|ActiveRow
	 */
	public function getInvoiceById(int $id) {
		return $this->getInvoices()->get($id);
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getInvoicesByUserId(int $id) {
		return $this->getInvoices()->where('user_id', $id);
	}

	/**
	 * @param DateTime $date
	 * @return Selection
	 */
	public function getInvoiceNews(DateTime $date, int $user_id) {
		return $this->getInvoicesByUserId($user_id)
			->where('date_update > ?', $date)
			->order('date_update DESC');
	}


	/**
	 * @param array $data
	 */
	public function saveInvoice(array $data) {
		$this->getInvoices()->insert($data);
	}

	/**
	 * @param array $data
	 */
	public function updateInvoice(array $data) {
		$invoice = $this->getInvoices()->get($data['id']);
		unset($data['id']);
		$invoice->update($data);
	}
}