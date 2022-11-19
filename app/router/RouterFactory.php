<?php

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory {

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter() {
		//Route::$defaultFlags = Route::SECURED;

		$router = new RouteList;

		$member = new RouteList('Member');

		$member[] = new Route('//member.%domain%/forum/view-post/<id>','Forum:post', Route::ONE_WAY);
		$member[] = new Route('//member.%domain%/forum/view/<id>','Forum:topic', Route::ONE_WAY);

		$member[] = new Route('//member.%domain%/akce/[year/<yp-year>]','Akce:default');
		$member[] = new Route('//member.%domain%/attendance/[year/<yp-year>]','Attendance:default');
		$member[] = new Route('//member.%domain%/forum/<action>/<id>[/page/<vp-page>]','Forum:view');
		$member[] = new Route('//member.%domain%/<presenter>/<action>[/<id>]', 'News:default');

		$account = new RouteList('Account');
		$account[] = new Route('//account.%domain%/<presenter>/<action>[/<id>]', 'Sign:default');

		$router[] = $member;
		$router[] = $account;

		return $router;
	}
}
