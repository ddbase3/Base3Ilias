<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IRequest;
use Base3\Api\IClassMap;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Permission;
use Base3\Configuration\Api\IConfiguration;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Chatbot\SettingsTab\ChatbotconfigSettingsTab;
use UiFoundation\Api\ISettingsTab;
use Base3Ilias\Base3\Base3IliasChatbotConfigService;
use Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use Base3Ilias\Base3\Base3IliasFileManagerOutput;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Base3IliasFileManagerOutputTest extends TestCase {

	protected function tearDown(): void {
		http_response_code(200);
		parent::tearDown();
	}

	public function testAnonymousFileManagerRequestIsRejectedBeforeOwnerLookup(): void {
		$usermanager = $this->createMock(IUsermanager::class);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('ilias');
		$authorization->method('decide')->willReturn(AuthorizationDecision::deny());
		$classmap = $this->createMock(IClassMap::class);
		$classmap->expects(self::never())->method('getInstanceByInterfaceName');

		$output = new Base3IliasFileManagerOutput(
			$this->createMock(IRequest::class),
			$usermanager,
			$authorization,
			$classmap,
			$this->unusedHttpService()
		);

		self::assertSame(['message' => 'FileManager access denied.'], json_decode($output->getOutput('php', true), true));
		self::assertSame(403, http_response_code());
	}

	public function testUnconfiguredOwnerIsRejectedEvenForAdministrator(): void {
		$usermanager = $this->createMock(IUsermanager::class);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('ilias');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		$request = $this->createMock(IRequest::class);
		$request->method('get')->willReturnCallback(
			fn(string $key, $default = null) => ['fm_group' => 'other', 'fm_name' => 'target'][$key] ?? $default
		);
		$tab = $this->createMock(ISettingsTab::class);
		$tab->method('isVisible')->willReturn(true);
		$tab->method('getDisplayData')->willReturn([
			'group' => 'uihk-chatbot', 'name' => 'default',
			'resources' => ['enabled' => true]
		]);
		$classmap = $this->createMock(IClassMap::class);
		$classmap->method('getInstanceByInterfaceName')->with(
			ISettingsTab::class, ChatbotconfigSettingsTab::getName()
		)->willReturn($tab);

		$output = new Base3IliasFileManagerOutput($request, $usermanager, $authorization, $classmap, $this->unusedHttpService());

		self::assertSame(['message' => 'FileManager configuration not found.'], json_decode($output->getOutput('php', true), true));
		self::assertSame(404, http_response_code());
	}

	public function testChatbotDisplayDataKeepsExistingResourceEndpoints(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->method('get')->with('base')->willReturn(['url' => 'https://example.org/ilias/']);
		$service = new Base3IliasChatbotConfigService($this->unusedHttpService(), $configuration);

		$data = $service->getDisplayData(
			'uihk-chatbot', 'default', 'Chatbot', 'Description', 'Save',
			'index.php?name=base3iliasfilemanageroutput&out=php&fm_group=uihk-chatbot&fm_name=default'
		);

		self::assertSame('uihk-chatbot', $data['group']);
		self::assertSame('default', $data['name']);
		self::assertTrue($data['resources']['enabled']);
		self::assertSame(50 * 1024 * 1024, $data['resources']['max_file_size']);
		self::assertSame('application/pdf,.pdf', $data['resources']['accept']);
		self::assertSame(
			'https://example.org/ilias/index.php?name=base3iliasfilemanageroutput&out=php&fm_group=uihk-chatbot&fm_name=default&fm_action=uploadChunk',
			$data['resources']['endpoints']['uploadChunk']
		);
	}

	private function unusedHttpService(): Base3IliasFileManagerHttpService {
		// Denial and endpoint-generation tests do not execute the HTTP service.
		return (new ReflectionClass(Base3IliasFileManagerHttpService::class))->newInstanceWithoutConstructor();
	}
}
