<?php declare(strict_types=1);

namespace Base3Ilias\Test\Group;

use Base3Ilias\Group\Base3IliasAdministrationGroup;
use Base3Ilias\Group\Base3IliasPresentationGroup;
use Base3Ilias\Group\Base3IliasTestingGroup;
use PHPUnit\Framework\TestCase;

final class Base3IliasGroupsTest extends TestCase {

	public function testDiscoverableGroupsUseUniqueNamesAndEditableCodeOwnedDefinitions(): void {
		$groups = [
			new Base3IliasAdministrationGroup(),
			new Base3IliasPresentationGroup(),
			new Base3IliasTestingGroup()
		];
		self::assertSame(
			['base3iliasadministrationgroup', 'base3iliaspresentationgroup', 'base3iliastestinggroup'],
			array_map(static fn($group): string => $group::getName(), $groups)
		);
		self::assertSame(['administration', 'presentation', 'testing'],
			array_map(static fn($group): string => (string)$group->getGroup()->name, $groups));
		foreach ($groups as $definition) {
			self::assertTrue($definition->isEditable());
			self::assertSame([], $definition->getCriteria());
			self::assertNotSame('', (string)$definition->getGroup()->info);
		}
	}
}
