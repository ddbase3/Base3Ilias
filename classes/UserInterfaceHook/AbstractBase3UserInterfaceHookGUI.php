<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\UserInterfaceHook;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\Base3Ilias\Base3\Base3IliasRuntime;
use ilCtrl;
use ilUIHookPluginGUI;
use ILIAS\DI\Container;

/**
 * Base class for ILIAS UIHook GUIs that depend on BASE3.
 *
 * UIHooks can be constructed very early in the ILIAS page lifecycle.
 * Therefore this class only initializes services that are expected to be
 * available at that point. Template/UI services must be resolved lazily
 * in concrete hooks when they are actually needed.
 */
abstract class AbstractBase3UserInterfaceHookGUI extends ilUIHookPluginGUI {

	protected Container $dic;
	protected ilCtrl $ctrl;
	protected IClassMap $classmap;

	public function __construct() {
		Base3IliasRuntime::bootOnce(false, true);

		$this->dic = $GLOBALS['DIC'];
		$this->ctrl = $this->dic->ctrl();
		$this->classmap = $this->dic[IClassMap::class];
	}

	protected function keep(): array {
		return [
			'mode' => self::KEEP,
			'html' => ''
		];
	}
}
