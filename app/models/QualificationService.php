<?php


namespace App\Model;

use Nette\Database\Table\Selection;

final class QualificationService extends DatabaseService
{

	public function getQualificationMembers(): Selection
	{
		return $this->database->table('qualification_members');
	}

	public function getQualificationMemberByQualificationId(int $id): Selection
	{
		return $this->getQualificationMembers()->where('qualification_id', $id);
	}

	public function getQualifications(): Selection
	{
		return $this->database->table('qualifications');
	}

	public function getQualificationMemberByNumber(int $id)
	{
		return $this->getQualificationMembers()->where('evidsoft_id', $id)->fetch();
	}

	public function getQualificationMemberByMemberId(int $id): Selection
	{
		return $this->getQualificationMembers()->where('member_id', $id);
	}
}