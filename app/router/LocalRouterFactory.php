<?php

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class LocalRouterFactory{

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter(){
		$router = new RouteList;

		$member = new RouteList('Member');
		$member[] = new Route('member/forum/view-post/<id>','Forum:post', Route::ONE_WAY);
		$member[] = new Route('member/forum/view/<id>','Forum:topic', Route::ONE_WAY);

		$member[] = new Route('member/forum/<action>/<id>[/page/<vp-page>]','Forum:view');
		$member[] = new Route('member/<presenter>/<action>[/<id>]', 'News:default');

		$photo = new RouteList('Photo');
		$photo[] = new Route('photo/album/<slug \d+-.+>/<action view|edit|add>','Album:view');
		$photo[] = new Route('photo/<presenter>/<action>[/<id>]', 'News:default');

		$router[] = $member;
		$router[] = $photo;

		return $router;
	}

}
