<?php declare(strict_types=1);

namespace Base3\Base3Ilias\PageComponent;

use ilPageComponentPlugin;

abstract class AbstractPageComponentPlugin extends ilPageComponentPlugin {

	public function getPluginName(): string {
		return preg_replace('/^il|Plugin$/', '', static::class);
	}

	public function isValidParentType(string $a_type): bool {
		return true;
	}
}
