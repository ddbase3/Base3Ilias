<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Api\IBootstrap;

class Base3IliasBootstrap implements IBootstrap {

	public function run(): void {
		echo Base3IliasRuntime::bootStandaloneAndDispatch();
	}
}
