<?php

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory{

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter(){
		$router = new RouteList;

		$member = new RouteList('Member');
		$member[] = new Route('//member.%domain%/forum/<action>/<id>[/page/<vp-page>]','Forum:view');
		$member[] = new Route('//member.%domain%/<presenter>/<action>[/<id>]', 'News:default');

		$photo = new RouteList('Photo');
		$photo[] = new Route('//photo.%domain%/album/<slug \d+-.+>/<action view|edit|add>','Album:view');
		$photo[] = new Route('//photo.%domain%/<presenter>/<action>[/<id>]', 'News:default');

		$router[] = $member;
		$router[] = $photo;

		$router[] = new Route('<presenter>/<action>[/<id>]', 'News:default');
		return $router;
	}

}
