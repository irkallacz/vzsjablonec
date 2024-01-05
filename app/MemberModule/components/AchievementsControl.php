<?php


namespace App\MemberModule\Components;


use App\Model\AchievementsService;
use App\Model\UserService;

final class AchievementsControl extends LayerControl
{
	/**
	 * @var AchievementsService
	 */
	protected $achievementsService;

	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var int
	 */
	protected $memberId;

	/**
	 * AchievementsControl constructor.
	 * @param AchievementsService $achievementsService
	 * @param UserService $userService
	 * @param $userId
	 */
	public function __construct(AchievementsService $achievementsService, UserService $userService, int $memberId)
	{
		$this->achievementsService = $achievementsService;
		$this->userService = $userService;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$this->template->setFile(__DIR__ . '/AchievementsControl.latte');

		$this->template->badgesCount = $this->achievementsService->getBadges($this->memberId)->count('achievement_id');
		$this->template->badges = $this->achievementsService->getBadgesAchievements($this->memberId);
		$this->template->achievementsCount = $this->achievementsService->getAchievements()->fetchPairs('id', 'pocet');
		$this->template->users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->count('id');

		$this->template->render();
	}
}