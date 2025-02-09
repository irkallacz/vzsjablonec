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
	 * AchievementsControl constructor.
	 * @param AchievementsService $achievementsService
	 * @param UserService $userService
	 * @param $userId
	 */
	public function __construct(AchievementsService $achievementsService, UserService $userService)
	{
		$this->achievementsService = $achievementsService;
		$this->userService = $userService;
	}

	public function render(int $memberId)
	{
		$this->template->setFile(__DIR__ . '/AchievementsControl.latte');

		$this->template->badgesCount = $this->achievementsService->getBadgesForUser($memberId)->count('achievement_id');
		$this->template->badges = $this->achievementsService->getBadgesAchievements($memberId);

		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->count('id');
		$this->template->goldies = $this->achievementsService->getBadgesCount()->having('COUNT(`id`) < ?', $users / 10)->fetchPairs(null, 'achievement_id');

		$this->template->render();
	}
}