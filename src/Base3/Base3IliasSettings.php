<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Translation\Api\ITranslation;
use Base3Ilias\Api\IBase3IliasSettings;

/**
 * Class Base3IliasSettings
 *
 * Default Base3Ilias settings implementation.
 */
class Base3IliasSettings implements IBase3IliasSettings {

	public function __construct(
		private readonly ITranslation $translation
	) {}

	public function getAdministrationConfig(): array {
		return [
			[
				'name' => 'system',
				'label' => $this->t('base3_admin_tab_system', 'System'),
				'displays' => [
					[
						'name' => 'iliasdashboarddisplay',
						'label' => $this->t('base3_admin_subtab_iliasdashboarddisplay', 'Dashboard')
					], [
						'name' => 'iliasconfigadmindisplay',
						'label' => $this->t('base3_admin_subtab_iliasconfigadmindisplay', 'ILIAS Config')
					], [
						'name' => 'iliaslogadmindisplay',
						'label' => $this->t('base3_admin_subtab_iliaslogadmindisplay', 'ILIAS Log')
					], [
						'name' => 'iliaserrorlogadmindisplay',
						'label' => $this->t('base3_admin_subtab_iliaserrorlogadmindisplay', 'ILIAS Errors')
					], [
						'name' => 'iliassystemhealthdisplay',
						'label' => $this->t('base3_admin_subtab_iliassystemhealthdisplay', 'ILIAS Health')
					], [
						'name' => 'iliasrequestdebugdisplay',
						'label' => $this->t('base3_admin_subtab_iliasrequestdebugdisplay', 'ILIAS Request')
					], [
						'name' => 'iliasuserdebugdisplay',
						'label' => $this->t('base3_admin_subtab_iliasuserdebugdisplay', 'ILIAS User')
					], [
						'name' => 'iliaspermissiondebugdisplay',
						'label' => $this->t('base3_admin_subtab_iliaspermissiondebugdisplay', 'ILIAS Permissions')
					], [
						'name' => 'iliasobjectdebugdisplay',
						'label' => $this->t('base3_admin_subtab_iliasobjectdebugdisplay', 'ILIAS Objects')
					], [
						'name' => 'logadmindisplay',
						'label' => $this->t('base3_admin_subtab_logadmindisplay', 'BASE3 Log')
					], [
						'name' => 'servicesadmindisplay',
						'label' => $this->t('base3_admin_subtab_servicesadmindisplay', 'Services')
					], [
						'name' => 'configurationadmindisplay',
						'label' => $this->t('base3_admin_subtab_configurationadmindisplay', 'Configuration')
					], [
						'name' => 'usermanagerdebugdisplay',
						'label' => $this->t('base3_admin_subtab_usermanagerdebugdisplay', 'User Manager')
					], [
						'name' => 'statestoreadmindisplay',
						'label' => $this->t('base3_admin_subtab_statestoreadmindisplay', 'State Store')
					], [
						'name' => 'jobsadmindisplay',
						'label' => $this->t('base3_admin_subtab_jobsadmindisplay', 'Jobs')
					], [
						'name' => 'databaseworkbenchdisplay',
						'label' => $this->t('base3_admin_subtab_databaseworkbenchdisplay', 'Database workbench')
					]
				]
			]
		];
	}

	private function t(string $key, string $fallback): string {
		return $this->translation->translate('Administration', 'administration', $key, $fallback);
	}
}
