<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasConfigAdminDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class IliasConfigAdminDisplayMaskingTest extends TestCase {

	public function testMasksEveryNonEmptySecretToTheSameFixedLength(): void {
		self::assertSame('************', $this->invoke('maskValue', 'short'));
		self::assertSame('************', $this->invoke('maskValue', 'a very long setup password'));
		self::assertSame('', $this->invoke('maskValue', ''));
	}

	public function testTranslationUsesConfiguredValueAndInterpolatesParameters(): void {
		$instance = (new ReflectionClass(IliasConfigAdminDisplay::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(IliasConfigAdminDisplay::class, 'translations'))->setValue($instance, ['item' => 'Value %s']);
		self::assertSame('Value sample', (new ReflectionMethod(IliasConfigAdminDisplay::class, 't'))->invoke($instance, 'item', 'fallback', 'sample'));
	}

	public function testEmptyTranslationFallsBackToTheProvidedLabel(): void {
		self::assertSame('fallback', $this->invoke('t', 'unknown', 'fallback'));
	}


	public function testConfigurationRowsMaskSecretsWithoutMarkingOrdinaryValuesSensitive(): void {
		if (!class_exists(\ilIniFile::class)) {
			self::markTestSkipped('Native ILIAS INI service is unavailable outside ILIAS.');
		}
		$ini = $this->createMock(\ilIniFile::class);
		$ini->method('readVariable')->willReturnCallback(static function(string $section, string $key): string {
			return match ($section . '.' . $key) {
				'setup.pass' => 'a-secret-password',
				'server.timezone' => 'Europe/Berlin',
				default => '',
			};
		});
		$display = (new ReflectionClass(IliasConfigAdminDisplay::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(IliasConfigAdminDisplay::class, 'ilIliasIniFile'))->setValue($display, $ini);
		$method = new ReflectionMethod(IliasConfigAdminDisplay::class, 'row');
		$secret = $method->invoke($display, 'setup', 'pass', 'Password', true);
		self::assertSame('************', $secret['value']);
		self::assertTrue($secret['sensitive']);
		self::assertFalse($secret['empty']);
		$public = $method->invoke($display, 'server', 'timezone', 'Time zone');
		self::assertSame('Europe/Berlin', $public['value']);
		self::assertFalse($public['sensitive']);
	}

	public function testConfigurationLogPathJoinsTrimmedComponentsAndRejectsMissingParts(): void {
		if (!class_exists(\ilIniFile::class)) {
			self::markTestSkipped('Native ILIAS INI service is unavailable outside ILIAS.');
		}
		$values = ['path' => ' /var/log/ ', 'file' => ' /ilias.log '];
		$ini = $this->createMock(\ilIniFile::class);
		$ini->method('readVariable')->willReturnCallback(static function(string $section, string $key) use (&$values): string {
			return $values[$key] ?? '';
		});
		$display = (new ReflectionClass(IliasConfigAdminDisplay::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(IliasConfigAdminDisplay::class, 'ilIliasIniFile'))->setValue($display, $ini);
		$method = new ReflectionMethod(IliasConfigAdminDisplay::class, 'getLogFullPath');
		self::assertSame('/var/log/ilias.log', $method->invoke($display));
		$values['file'] = '';
		self::assertSame('', $method->invoke($display));
		$values['file'] = 'ilias.log';
		$values['path'] = '';
		self::assertSame('', $method->invoke($display));
	}

	public function testCompleteConfigurationOutputContainsExpectedSectionsAndMaskedSetup(): void {
		if (!class_exists(\ilIniFile::class)) {
			self::markTestSkipped('Native ILIAS INI service is unavailable outside ILIAS.');
		}
		$view = $this->createMock(\Base3\Api\IMvcView::class);
		$view->expects(self::once())->method('setTemplate')->with('Display/IliasConfigAdminDisplay.php');
		$view->method('getBricks')->willReturnCallback(static fn(string $set): ?array => match ($set) {
			'base3ilias_common' => ['section_server_title' => 'Common'],
			'ilias_config_admin_display' => ['section_server_title' => 'Localized server'],
			default => null,
		});
		$assigned = [];
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('rendered');
		$ini = $this->createMock(\ilIniFile::class);
		$ini->method('readVariable')->willReturnCallback(static fn(string $section, string $key): string => match ($section . '.' . $key) {
			'setup.pass' => 'secret-password',
			'log.path' => '/var/log',
			'log.file' => 'ilias.log',
			default => '',
		});
		$display = new IliasConfigAdminDisplay($view, $ini);
		self::assertSame('rendered', $display->getOutput());
		self::assertCount(7, $assigned['sections']);
		self::assertSame('Localized server', $assigned['sections'][0]['title']);
		$logRows = $assigned['sections'][2]['rows'];
		self::assertSame('/var/log/ilias.log', $logRows[4]['value']);
		$setupRows = $assigned['sections'][6]['rows'];
		self::assertSame('************', $setupRows[0]['value']);
		self::assertTrue($setupRows[0]['sensitive']);
		self::assertArrayHasKey('generatedAt', $assigned);
	}

	public function testTranslatedFallbackTextIsTrimmedBeforeInterpolation(): void {
		$display = (new ReflectionClass(IliasConfigAdminDisplay::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(IliasConfigAdminDisplay::class, 'translations'))->setValue($display, [
			'number' => '  Total: %d  ',
			'blank' => '   ',
		]);
		$method = new ReflectionMethod(IliasConfigAdminDisplay::class, 't');
		self::assertSame('Total: 5', $method->invoke($display, 'number', 'other', 5));
		self::assertSame('Fallback 5', $method->invoke($display, 'blank', 'Fallback %d', 5));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasConfigAdminDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasConfigAdminDisplay::class, $method))->invoke($instance, ...$args);
	}
}
