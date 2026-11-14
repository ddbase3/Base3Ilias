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


namespace Base3\Base3Ilias\Test\Criterion;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\Base3Ilias\Criterion\Base3IliasRoleCriterion;
use PHPUnit\Framework\TestCase;

final class Base3IliasRoleCriterionTest extends TestCase {

	public function testSchemaLabelsUseExistingTranslationService(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects(self::exactly(2))->method('translate')
			->willReturnCallback(static function(string $set, string $section, string $key, string $fallback, array $replacements = []): string {
				self::assertSame('Criterion', $set);
				self::assertSame('base3ilias_role_criterion', $section);
				self::assertSame([], $replacements);
				return match ($key) {
					'title' => 'ILIAS-Rolle',
					'role_ids' => 'Rollen-IDs',
				};
			});
		$criterion = new Base3IliasRoleCriterion($translation);
		$schema = $criterion->getSchema();
		self::assertSame('base3iliasrolecriterion', $criterion::getName());
		self::assertSame('ILIAS-Rolle', $schema['title']);
		self::assertSame('Rollen-IDs', $schema['properties']['role_ids']['title']);
		self::assertSame(['role_ids'], $schema['required']);
	}
}
