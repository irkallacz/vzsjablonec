<?php

namespace App\Console;

use App\Model\EvidsoftService;
use App\Model\Qualification;
use App\Model\UserService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class QualificationCommand extends BaseCommand
{
	const QUALIFICATIONS = [
		 140 => Qualification::ZVZS,
		 149 => Qualification::MVV,
		 150 => Qualification::VZP,
		 154 => Qualification::SPP,
		 155 => Qualification::ZM,
		 157 => Qualification::Z7,
		 158 => Qualification::Z6,
		 159 => Qualification::Z5,
		 65 => Qualification::Z3,
		 66 => Qualification::Z2,
		 70 => Qualification::IVZS,
	];

	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var EvidsoftService
	 */
	protected $evidsoftService;

	/**
	 * @param UserService $userService
	 * @param EvidsoftService $evidsoftService
	 */
	public function __construct(UserService $userService, EvidsoftService $evidsoftService)
	{
		parent::__construct();

		$this->userService = $userService;
		$this->evidsoftService = $evidsoftService;
	}

	protected function configure() {
		$this->setName('evidsoft:qualifications')
			->setDescription('Sync actual qualifications with evidsoft');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeln($output, '<info>Evidsoft qualifications command</info>');

		$this->evidsoftService->authorize();

		$members = $this->userService->getUsers(UserService::DELETED_LEVEL)->where('evidsoft_id IS NOT NULL')->fetchPairs('evidsoft_id', 'id');
		$vzs = $this->userService->getUsers(UserService::DELETED_LEVEL)->where('vzs_id IS NOT NULL')->fetchPairs('id', 'vzs_id');
		$courses = $this->evidsoftService->expertiseCourseList();

		foreach ($courses->items as $course) {
			$persons = $this->evidsoftService->expertiseCoursePersonList($course->ID);
			foreach ($persons->items as $person) {
				$this->writeln($output, $person->ID, $course->Expertise_ID, $course->ValidityFrom, $course->ValidityTo ?: '0000-00-00', $person->FullName);

				preg_match('~data-person-expertise-id="(\d+)"~', $person->CertificateNumber, $matches);
				$validityFrom = new \DateTimeImmutable($course->ValidityFrom);
				$validityTo = $course->ValidityTo ? new \DateTimeImmutable($course->ValidityTo) : null;

				if (!($memberId = $members[$person->ID] ?? null)) {
					continue;
				}

				if (!($qualificationId = self::QUALIFICATIONS[$course->Expertise_ID])) {
					continue;
				}

				if (!($id = $matches[1] ?? null)) {
					continue;
				} elseif ($this->userService->getQualificationMemberByNumber($id)) {
					continue;
				}

				if (!$validityTo) {
					if (in_array($qualificationId, [Qualification::Z7, Qualification::Z6, Qualification::Z5])) {
						$validityTo = new \DateTimeImmutable($person->BirthDate);

						switch ($qualificationId) {
							case Qualification::Z7:
								$validityTo = $validityTo->add(new \DateInterval('P10Y'));
								break;
							case Qualification::Z6:
								$validityTo = $validityTo->add(new \DateInterval('P14Y'));
								break;
							case Qualification::Z5:
								$validityTo = $validityTo->add(new \DateInterval('P18Y'));
								break;
						}
					}

					if (in_array($qualificationId, [Qualification::ZM])) {
						$validityTo = $validityFrom->add(new \DateInterval('P2Y'));
					}

					if (in_array($qualificationId, [Qualification::SPP, Qualification::SBK])) {
						$validityTo = $validityFrom->add(new \DateInterval('P4Y'));
					}

					if (in_array($qualificationId, [Qualification::IVZS])) {
						$validityTo = $validityFrom->add(new \DateInterval('P6Y'));
					}
				}

				$this->userService->getQualificationMembers()->insert([
					'member_id' => $memberId,
					'qualification_id' => $qualificationId,
					'evidsoft_id' => $id,
					'number' => $vzs[$memberId] ?? null,
					'date_start' => $validityFrom,
					'date_end' => $validityTo,
					'date_add' => new \DateTimeImmutable(),
				]);

				$this->writeln($output, $memberId, $qualificationId, $validityFrom->format('Y-m-d'), $validityTo ? $validityTo->format('Y-m-d') : '0000-00-00', $id);
			}
		}
	}

}