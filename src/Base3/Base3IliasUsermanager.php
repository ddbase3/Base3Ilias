<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Usermanager\Api\IUsermanager;
use Base3\Api\ICheck;
use Base3\Core\ServiceLocator;

class Base3IliasUsermanager implements IUsermanager, ICheck {

	private $servicelocator;
	private $accesscontrol;
	private $ilObjUser;

	private $user;
	private $groups;

	public function __construct() {
		$this->servicelocator = ServiceLocator::getInstance();
		$this->accesscontrol = $this->servicelocator->get('accesscontrol');
		$this->ilObjUser = $this->servicelocator->get('ilUser');
	}

	// Implementation of IUsermanager

	public function getUser() {
		if ($this->user) return $this->user;

		$userid = $this->accesscontrol->getUserId();
		if (!$userid) return null;

		$this->user = new \Base3\Usermanager\User;
		$this->user->id = $userid;
		$this->user->name = $this->ilObjUser->getFirstname() . ' ' . $this->ilObjUser->getLastname();
		$this->user->role = $userid == 6 ? "admin" : "member";  // 6 == root

		return $this->user;
	}

	public function getGroups() {
		return array();
	}

	public function registUser($userid, $password, $data = null) {
		// no registration
	}

	public function changePassword($oldpassword, $newpassword) {
		// no registration
	}

	public function getAllUsers() {
		$user = $this->getUser();
		if ($user == null || $user->role != 'admin') return array();
		return array( $user );
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			"depending_services" => $this->accesscontrol == null ? "Fail" : "Ok"
		);
	}
}
