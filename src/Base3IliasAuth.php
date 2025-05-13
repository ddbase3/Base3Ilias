<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Accesscontrol\AbstractAuth;

class Base3IliasAuth extends AbstractAuth {

        // Implementation of IBase

        public function getName() {
                return "base3iliasauth";
        }

        // Implementation of IAuthentication

        public function login() {
		return 'test';
        }
}

