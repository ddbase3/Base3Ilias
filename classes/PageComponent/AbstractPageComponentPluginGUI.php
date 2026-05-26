<?php declare(strict_types=1);

namespace Base3\Base3Ilias\PageComponent;

use Base3\Api\IClassMap;
use ilCtrl;
use ilGlobalTemplateInterface;
use ilLanguage;
use ilPageComponentPluginGUI;
use ilPropertyFormGUI;
use ILIAS\DI\Container;
use ILIAS\DI\UIServices;

abstract class AbstractPageComponentPluginGUI extends ilPageComponentPluginGUI {

	protected Container $dic;
	protected ilCtrl $ilCtrl;
	protected ilGlobalTemplateInterface $tpl;
	protected ilLanguage $lng;
	protected UIServices $ui;
	protected ilGlobalTemplateInterface $mainTemplate;
	protected IClassMap $classmap;

	public function __construct() {
		$this->dic = $GLOBALS['DIC'];
		$this->ilCtrl = $this->dic['ilCtrl'];
		$this->tpl = $this->dic['tpl'];
		$this->lng = $this->dic['lng'];
		$this->ui = $this->dic->ui();
		$this->mainTemplate = $this->ui->mainTemplate();
		$this->classmap = $this->dic[IClassMap::class];
	}

	public function executeCommand(): void {
		$cmd = $this->ilCtrl->getCmd();
		if (method_exists($this, $cmd)) $this->$cmd();
	}

	public function insert(): void {
		$form = $this->initForm(true);
		$this->tpl->setContent($form->getHTML());
	}

	public function edit(): void {
		$form = $this->initForm(false);
		$this->tpl->setContent($form->getHTML());
	}

	public function cancel(): void {
		$this->returnToParent();
	}

	public function create(): void {
		$form = $this->initForm(true);

		if ($form->checkInput()) {
			$props = $this->getElementPropsFromForm($form);

			if ($this->beforeCreateElement($form, $props) && $this->createElement($props)) {
				$this->afterCreateElement($form, $props);
				$this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}

	public function update(): void {
		$form = $this->initForm(false);

		if ($form->checkInput()) {
			$props = $this->getElementPropsFromForm($form);

			if ($this->beforeUpdateElement($form, $props) && $this->updateElement($props)) {
				$this->afterUpdateElement($form, $props);
				$this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}

	protected function initForm($a_create = false): ilPropertyFormGUI {
		$props = array_merge($this->getDefaultProps(), $this->getProperties());

		$form = new ilPropertyFormGUI();
		$form->setTitle($this->getPageComponentName());
		$form->setDescription($this->getPageComponentDesc());

		$this->setFormContent($form, $props);

		if ($a_create) {
			$this->addCreationButton($form);
			$form->addCommandButton('cancel', $this->lng->txt('cancel'));
		} else {
			$form->addCommandButton('update', $this->lng->txt('save'));
			$form->addCommandButton('cancel', $this->lng->txt('cancel'));
		}

		$form->setFormAction($this->ilCtrl->getFormAction($this));
		return $form;
	}

	/**
	 * Returns the PageComponent properties that are persisted in the page XML.
	 *
	 * Subclasses may add additional form inputs in setFormContent(). These
	 * inputs are intentionally not stored in the PageComponent XML unless their
	 * keys are part of getDefaultProps(). This allows specialized components to
	 * store only a compact technical reference in the XML and persist larger
	 * configuration payloads elsewhere, e.g. in the BASE3 SettingsStore.
	 */
	protected function getElementPropsFromForm(ilPropertyFormGUI $form): array {
		$props = [];

		foreach ($this->getDefaultProps() as $key => $_) {
			$props[$key] = $form->getInput($key);
		}

		return $props;
	}

	/**
	 * Hook before a new PageComponent element is created.
	 *
	 * Return false to stop element creation and show the form again.
	 */
	protected function beforeCreateElement(ilPropertyFormGUI $form, array &$props): bool {
		return true;
	}

	/**
	 * Hook after a new PageComponent element has been created successfully.
	 */
	protected function afterCreateElement(ilPropertyFormGUI $form, array $props): void {
	}

	/**
	 * Hook before an existing PageComponent element is updated.
	 *
	 * Return false to stop element update and show the form again.
	 */
	protected function beforeUpdateElement(ilPropertyFormGUI $form, array &$props): bool {
		return true;
	}

	/**
	 * Hook after an existing PageComponent element has been updated successfully.
	 */
	protected function afterUpdateElement(ilPropertyFormGUI $form, array $props): void {
	}

	public function getElementHTML(string $a_mode, array $a_properties, string $plugin_version): string {
		switch ($a_mode) {
			case 'edit':
				return $this->getEditHtml($a_properties, $plugin_version);
			case 'presentation':
				return $this->getPresentationHtml($a_properties, $plugin_version);
		}

		return 'Unknown mode';
	}

	protected function getEditHtml(array $a_properties, string $plugin_version): string {
		$html = '<div style="display: flex; align-items: center; justify-content: space-between; background: #f9f9f9; border: 1px solid #ddd; padding: 12px 16px; border-radius: 8px; font-family: sans-serif;">';
		$html .= '<div>';
		$html .= '<div style="font-size: 1.1em; font-weight: bold; color: #333;">' . $this->getPageComponentName() . '</div>';
		$html .= '<div style="font-size: 0.9em; color: #666;"><i>' . $this->getPageComponentDesc() . '</i></div>';
		$html .= '</div>';
		$html .= '<img src="components/Base3/Base3Ilias/logo.svg" style="width:48px; height:auto; margin-left: 16px;" />';
		$html .= '</div>';
		return $html;
	}

	protected function getPresentationHtml(array $a_properties, string $plugin_version): string {
		return $this->getEditHtml($a_properties, $plugin_version);
	}

	abstract protected function getPageComponentName(): string;
	abstract protected function getPageComponentDesc(): string;
	abstract protected function getDefaultProps(): array;
	abstract protected function setFormContent(ilPropertyFormGUI $form, array $props): void;
}
