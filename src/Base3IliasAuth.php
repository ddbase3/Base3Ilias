<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Accesscontrol\AbstractAuth;

class Base3IliasAuth extends AbstractAuth {

	public function __construct(private readonly \ilAuthSession $ilAuthSession) {}

        // Implementation of IBase

        public static function getName(): string {
                return "base3iliasauth";
        }

        // Implementation of IAuthentication

	public function login() {
		$userId = $this->ilAuthSession->getUserId();
		return $userId == 13 ? null : $userId;
        }
}

