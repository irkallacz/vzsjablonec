<?php

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory{

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter(){
		//Route::$defaultFlags = Route::SECURED;

		$router = new RouteList;

		$member = new RouteList('Member');

		$member[] = new Route('//member.%domain%/forum/view-post/<id>','Forum:post', Route::ONE_WAY);
		$member[] = new Route('//member.%domain%/forum/view/<id>','Forum:topic', Route::ONE_WAY);

		$member[] = new Route('//member.%domain%/forum/<action>/<id>[/page/<vp-page>]','Forum:view');
		$member[] = new Route('//member.%domain%/<presenter>/<action>[/<id>]', 'News:default');

		$photo = new RouteList('Photo');
		$photo[] = new Route('//photo.%domain%/album/<slug \d+-.+>/<action view|edit|add>','Album:view');
		$photo[] = new Route('//photo.%domain%/<presenter>/<action>[/<id>]', 'News:default');

		$cron = new RouteList('Cron');
		$cron[] = new Route('//cron.%domain%/<presenter>/<action>[/<id>]', 'Cron:default');

		$router[] = $member;
		$router[] = $photo;
		$router[] = $cron;

		return $router;
	}
}
