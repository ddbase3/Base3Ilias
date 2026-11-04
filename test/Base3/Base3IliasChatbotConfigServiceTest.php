<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IDisplay;
use Base3\Configuration\Api\IConfiguration;
use Base3Ilias\Base3\Base3IliasChatbotConfigService;
use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class Base3IliasChatbotConfigServiceTest extends TestCase {

	public function testAbsoluteFileManagerUrlIsUsedWithoutConfigurationLookup(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->expects(self::never())->method('get');
		$data = $this->service($configuration)->getDisplayData(
			'chatbot', 'site', 'Chat', 'Description', 'Save', ' https://files.example.org/upload?token=x '
		);
		self::assertSame('chatbot', $data['group']);
		self::assertSame('site', $data['name']);
		self::assertSame('standalone', $data['mode']);
		self::assertSame('ajax', $data['save_mode']);
		self::assertSame('https://files.example.org/upload?token=x&fm_action=uploadFinish', $data['resources']['endpoints']['uploadFinish']);
	}

	public function testRootRelativeUrlUsesConfiguredHostAndPort(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->with('base')->willReturn(['url' => 'https://example.org:8443/ilias/']);
		$data = $this->service($configuration)->getDisplayData('a', 'b', 'c', 'd', 'e', '/files?name=x');
		self::assertSame('https://example.org:8443/files?name=x&fm_action=list', $data['resources']['endpoints']['list']);
	}

	public function testConfiguredDisplayReceivesExactlyTheDataFromTheSameService(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->with('base')->willReturn(['url' => 'https://example.org/base/']);
		$display = $this->createMock(IDisplay::class);
		$display->expects(self::once())->method('setData')->with(self::callback(static fn(array $data): bool =>
			$data['group'] === 'ui' && $data['name'] === 'default'
			&& $data['resources']['endpoints']['download'] === 'https://example.org/base/files?fm_action=download'
		));
		$this->service($configuration)->configureDisplay($display, 'ui', 'default', 'Chat', 'About', 'Save', 'files');
	}

	public function testBlankFileManagerUrlIsRejectedWithoutConfigurationAccess(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->expects(self::never())->method('get');
		$this->expectException(RuntimeException::class);
		$this->service($configuration)->getDisplayData('ui', 'default', '', '', '', '  ');
	}

	public function testRelativeUrlRequiresConfiguredBaseUrl(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->willReturn([]);
		$this->expectException(RuntimeException::class);
		$this->service($configuration)->getDisplayData('ui', 'default', '', '', '', '/files');
	}

	public function testRootRelativeUrlRequiresRealSchemeAndHost(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->willReturn(['url' => '/local/']);
		$this->expectException(RuntimeException::class);
		$this->service($configuration)->getDisplayData('ui', 'default', '', '', '', '/files');
	}

	private function service(IConfiguration $configuration): Base3IliasChatbotConfigService {
		// The URL tests do not invoke the HTTP handler, as in Base3IliasFileManagerOutputTest.
		$unused = (new ReflectionClass(Base3IliasFileManagerHttpService::class))->newInstanceWithoutConstructor();
		return new Base3IliasChatbotConfigService($unused, $configuration);
	}
}
