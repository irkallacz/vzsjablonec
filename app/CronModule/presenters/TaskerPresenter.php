<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 25.4.2018
 * Time: 23:18
 */

namespace App\CronModule\Presenters;


class TaskerPresenter extends BasePresenter {

	const TASKS = [
		0 => 'Calendar:default',
		1 => 'Drive:default',
		2 => 'People:update',
		3 => 'Idoklad:update',
		4 => ':Photo:Thumbs:default',
	];

	public function actionDefault() {
		$hour = date('G');

		if (array_key_exists($hour, self::TASKS)) {
			$task = self::TASKS[$hour];
			$this->forward($task);
		}
	}
}