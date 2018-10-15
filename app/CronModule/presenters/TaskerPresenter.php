<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 25.4.2018
 * Time: 23:18
 */

namespace App\CronModule\Presenters;


class TaskerPresenter extends BasePresenter {

	/**
	 * @var array
	 */
	private $tasks;

	/**
	 * TaskerPresenter constructor.
	 * @param array $tasks
	 */
	public function __construct(array $tasks) {
		parent::__construct();
		$this->tasks = $tasks;
	}

	public function actionDefault() {
		$hour = date('G');

		if (array_key_exists($hour, $this->tasks)) {
			$task = $this->tasks[$hour];
			$this->forward($task);
		}
	}
}