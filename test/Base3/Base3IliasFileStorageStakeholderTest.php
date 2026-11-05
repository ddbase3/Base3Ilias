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

	public function testOwnerMatchesUserFromNativeContainer(): void {
		$previousDicExists = array_key_exists('DIC', $GLOBALS);
		$previousDic = $GLOBALS['DIC'] ?? null;

		$user = $this->createMock(\ilObjUser::class);
		$user->method('getId')->willReturn(417);
		$dic = $this->createMock(Container::class);
		$dic->method('isDependencyAvailable')->with('user')->willReturn(true);
		$dic->method('user')->willReturn($user);

		try {
			$GLOBALS['DIC'] = $dic;
			$stakeholder = new Base3IliasFileStorageStakeholder();
			self::assertSame(417, $stakeholder->getOwnerOfNewResources());
			$identification = (new \ReflectionClass(\ILIAS\ResourceStorage\Identification\ResourceIdentification::class))
				->newInstanceWithoutConstructor();
			self::assertSame(417, $stakeholder->getOwnerOfResource($identification));
		} finally {
			if ($previousDicExists) $GLOBALS['DIC'] = $previousDic;
			else unset($GLOBALS['DIC']);
		}
	}

	public function testMissingUserDependencyUsesSystemOwnerWithNativeContainer(): void {
		$previousDicExists = array_key_exists('DIC', $GLOBALS);
		$previousDic = $GLOBALS['DIC'] ?? null;

		$dic = $this->createMock(Container::class);
		$dic->method('isDependencyAvailable')->with('user')->willReturn(false);
		$dic->expects(self::never())->method('user');

		try {
			$GLOBALS['DIC'] = $dic;
			$stakeholder = new Base3IliasFileStorageStakeholder();
			self::assertSame(defined('SYSTEM_USER_ID') ? (int) SYSTEM_USER_ID : 6, $stakeholder->getOwnerOfNewResources());
		} finally {
			if ($previousDicExists) $GLOBALS['DIC'] = $previousDic;
			else unset($GLOBALS['DIC']);
		}
	}

	public function testResourceOwnershipAndConsumerIdentityStayStable(): void {
		$hadDic = array_key_exists('DIC', $GLOBALS);
		$previousDic = $GLOBALS['DIC'] ?? null;
		unset($GLOBALS['DIC']);

		try {
			$stakeholder = new Base3IliasFileStorageStakeholder();
			$identification = (new \ReflectionClass(\ILIAS\ResourceStorage\Identification\ResourceIdentification::class))
				->newInstanceWithoutConstructor();
			$owner = defined('SYSTEM_USER_ID') ? (int) SYSTEM_USER_ID : 6;

			self::assertSame('base3ilias_file_storage', $stakeholder->getId());
			self::assertSame('BASE3 ILIAS File Storage', $stakeholder->getConsumerNameForPresentation());
			self::assertTrue($stakeholder->isResourceInUse($identification));
			self::assertSame($owner, $stakeholder->getOwnerOfResource($identification));
			self::assertSame($owner, $stakeholder->getOwnerOfNewResources());
		} finally {
			if ($hadDic) $GLOBALS['DIC'] = $previousDic;
			else unset($GLOBALS['DIC']);
		}
	}

}
