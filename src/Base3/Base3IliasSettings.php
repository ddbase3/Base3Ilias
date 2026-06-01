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
				'name' => 'base3',
				'label' => 'BASE3',
				'displays' => [
					[
						'name' => 'logadmindisplay',
						'label' => 'Log'
					], [
						'name' => 'servicesadmindisplay',
						'label' => 'Services'
					], [
						'name' => 'configurationadmindisplay',
						'label' => 'Configuration'
					], [
						'name' => 'jobsadmindisplay',
						'label' => 'Jobs'
					]
				]
			]
		];
	}
}
