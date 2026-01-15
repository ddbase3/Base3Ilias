<?php declare(strict_types=1);

namespace Base3\Base3Ilias\PageComponent;

use Base3\Api\IClassMap;
use ilPluginConfigGUI;
use ilPlugin;
use ilCtrl;
use ilLanguage;
use ilGlobalPageTemplate;
use ilTabsGUI;
use ILIAS\DI\Container;

abstract class AbstractPageComponentConfigGUI extends ilPluginConfigGUI {

	protected Container $dic;
	protected ilPlugin $plugin;
	protected ilCtrl $ctrl;
	protected ilLanguage $lng;
	protected ilGlobalPageTemplate $tpl;
	protected ilTabsGUI $tabs;
	protected IClassMap $classmap;

	public function __construct() {
		$this->dic = $GLOBALS['DIC'];
		$this->classmap = $this->dic[IClassMap::class];
	}

	/**
	 * Basic init: fetch required services from the global DIC.
	 */
	protected function init(): void {
		$this->plugin = $this->getPluginObject();
		$this->ctrl = $this->dic->ctrl();
		$this->lng = $this->dic->language();
		$this->tpl = $this->dic->ui()->mainTemplate();
		$this->tabs = $this->dic->tabs();
	}

	/**
	 * Shortcut for plugin language variables.
	 */
	protected function txt(string $key): string {
		return $this->plugin->txt($key);
	}
}
