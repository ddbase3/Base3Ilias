<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Configuration\Api\IConfiguration;

class Base3IliasConfiguration implements IConfiguration {

	public function get($configuration = '') {
		// TODO config
    
		if ($configuration == 'base') return [
			'url' => 'https://ddahme.qualitus.net/ilias10/public/',
			'intern' => ''
		];
    
		if ($configuration == 'manager') return [
			'stdscope' => 'others',
			'layout' => 'simple'
		];
    
		return [];
	}

	public function set($data, $configuration = "") {
		// TODO: Implement set() method.
	}

	public function save() {
		// TODO: Implement save() method.
	}
}
