<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Event\Api\IEventManager;
use Base3\Settings\Api\ISettingsStore;
use Base3Ilias\Base3\Base3IliasFileStorageStakeholder;
use Base3Ilias\Base3\Base3IliasManagedFileStorageService;
use ILIAS\DI\Container;
use ILIAS\ResourceStorage\Collection\Collections;
use ILIAS\ResourceStorage\Identification\ResourceCollectionIdentification;
use ILIAS\ResourceStorage\Services;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IFileStorageFactory;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;
use RuntimeException;

final class Base3IliasManagedFileStorageLifecycleTest extends TestCase {

	public function testDeletingUnregisteredOwnerEmitsEventWithoutNativeDeletion(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$settings->method('has')->willReturn(false);
		$settings->expects(self::never())->method('remove');
		$settings->expects(self::never())->method('save');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::isInstanceOf(ManagedFileStorageDeletingEvent::class));
		$container = $this->createMock(Container::class);
		$container->expects(self::never())->method('resourceStorage');

		$this->service($settings, $container, $events)->delete('ui', 'default', 'collection');
	}

	public function testDeletingRegisteredCollectionRemovesNativeResourceBeforeSettings(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['storage_id' => 'existing-rcid']);
		$settings->method('has')->willReturn(true);
		$settings->expects(self::once())->method('remove')->with(
			'base3ilias-filemanager', hash('sha256', "ui\0default\0collection")
		);
		$settings->expects(self::once())->method('save');

		$identification = new ResourceCollectionIdentification('existing-rcid');
		$collections = $this->createMock(Collections::class);
		$collections->expects(self::exactly(2))->method('exists')->with('existing-rcid')
			->willReturnOnConsecutiveCalls(true, false);
		$collections->expects(self::once())->method('id')->with('existing-rcid')->willReturn($identification);
		$collections->expects(self::once())->method('remove')->with(
			$identification, self::isInstanceOf(Base3IliasFileStorageStakeholder::class), true
		);
		$this->service($settings, $this->container($collections))->delete(' ui ', ' default ', ' collection ');
	}

	public function testFailedNativeCollectionRemovalKeepsOwnerRegistrationIntact(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['storage_id' => 'existing-rcid']);
		$settings->expects(self::never())->method('remove');
		$settings->expects(self::never())->method('save');

		$identification = new ResourceCollectionIdentification('existing-rcid');
		$collections = $this->createMock(Collections::class);
		$collections->method('exists')->with('existing-rcid')->willReturn(true);
		$collections->method('id')->with('existing-rcid')->willReturn($identification);
		$collections->expects(self::once())->method('remove');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('could not be removed');
		$this->service($settings, $this->container($collections))->delete('ui', 'default', 'collection');
	}

	private function container(Collections $collections): Container {
		$services = $this->createMock(Services::class);
		$services->method('collection')->willReturn($collections);
		$container = $this->createMock(Container::class);
		$container->method('resourceStorage')->willReturn($services);
		return $container;
	}

	private function service(
		ISettingsStore $settings,
		Container $container,
		?IEventManager $events = null
	): Base3IliasManagedFileStorageService {
		return new Base3IliasManagedFileStorageService(
			$settings,
			$this->createMock(IFileStorageFactory::class),
			$container,
			$events ?? $this->createMock(IEventManager::class)
		);
	}
}
