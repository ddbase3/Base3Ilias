<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasFileStorageStakeholder;
use ILIAS\DI\Container;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use PHPUnit\Framework\TestCase;

final class Base3IliasFileStorageStakeholderTest extends TestCase {

	public function testCanBeInstantiatedWithoutAvailableIliasContainer(): void {
		$hadDic = array_key_exists('DIC', $GLOBALS);
		$previousDic = $GLOBALS['DIC'] ?? null;
		unset($GLOBALS['DIC']);

		try {
			$stakeholder = new Base3IliasFileStorageStakeholder();
			self::assertInstanceOf(ResourceStakeholder::class, $stakeholder);
			self::assertSame('base3ilias_file_storage', $stakeholder->getId());
			self::assertSame(
				defined('SYSTEM_USER_ID') ? (int)SYSTEM_USER_ID : 6,
				$stakeholder->getOwnerOfNewResources()
			);
		} finally {
			if ($hadDic) {
				$GLOBALS['DIC'] = $previousDic;
			} else {
				unset($GLOBALS['DIC']);
			}
		}
	}

	public function testOwnerMatchesCurrentIliasUserWhenAvailable(): void {
		global $DIC;
		if (!$DIC instanceof Container || !$DIC->isDependencyAvailable('user')) {
			self::markTestSkipped('An initialized ILIAS user is required for this test.');
		}

		$stakeholder = new Base3IliasFileStorageStakeholder();
		self::assertSame((int)$DIC->user()->getId(), $stakeholder->getOwnerOfNewResources());
	}
}
