<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3Ilias\Api\IBase3IliasSettings;

/**
 * Class Base3IliasSettings
 *
 * Default Base3Ilias settings implementation.
 */
class Base3IliasSettings implements IBase3IliasSettings {

	public function getAdministrationConfig(): array {
		return [
			[
				'name' => 'system',
				'label' => 'System',
				'displays' => [
					[
						'name' => 'iliasdashboarddisplay',
						'label' => 'Dashboard'
					], [
						'name' => 'iliasconfigadmindisplay',
						'label' => 'ILIAS Config'
					], [
						'name' => 'iliaslogadmindisplay',
						'label' => 'ILIAS Log'
					], [
						'name' => 'iliaserrorlogadmindisplay',
						'label' => 'ILIAS Errors'
					], [
						'name' => 'iliassystemhealthdisplay',
						'label' => 'ILIAS Health'
					], [
						'name' => 'iliasrequestdebugdisplay',
						'label' => 'ILIAS Request'
					], [
						'name' => 'iliasuserdebugdisplay',
						'label' => 'ILIAS User'
					], [
						'name' => 'iliaspermissiondebugdisplay',
						'label' => 'ILIAS Permission'
					], [
						'name' => 'iliasobjectdebugdisplay',
						'label' => 'ILIAS Objects'
					], [
						'name' => 'logadmindisplay',
						'label' => 'BASE3 Log'
					], [
						'name' => 'servicesadmindisplay',
						'label' => 'Services'
					], [
						'name' => 'configurationadmindisplay',
						'label' => 'Configuration'
					], [
						'name' => 'statestoreadmindisplay',
						'label' => 'State Store'
					], [
						'name' => 'jobsadmindisplay',
						'label' => 'Jobs'
					]
				]
			]
		];
	}
}
