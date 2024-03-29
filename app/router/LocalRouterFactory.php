<?php

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class LocalRouterFactory {

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter() {
		$router = new RouteList;

		$member = new RouteList('Member');
		$member[] = new Route('member/forum/view-post/<id>','Forum:post', Route::ONE_WAY);
		$member[] = new Route('member/forum/view/<id>','Forum:topic', Route::ONE_WAY);

		$member[] = new Route('member/akce/[year/<yp-year>]','Akce:default');
		$member[] = new Route('member/forum/<action>/<id>[/page/<vp-page>]','Forum:topic');
		$member[] = new Route('member/<presenter>/<action>[/<id>]', 'News:default');

		$account = new RouteList('Account');
		$account[] = new Route('account/<presenter>/<action>[/<id>]', 'Sign:default');

		$router[] = $member;
		$router[] = $account;

		return $router;
	}

}
