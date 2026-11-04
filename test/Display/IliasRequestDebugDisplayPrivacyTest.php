<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasRequestDebugDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasRequestDebugDisplayPrivacyTest extends TestCase {

	public function testMasksSensitiveParametersRecursivelyWithoutDiscardingSafeValues(): void {
		$data = [
			'page' => 7,
			'Password' => 'secret-1',
			'filters' => [
				'api_token' => 'secret-2',
				'csrfKey' => 'secret-3',
				'items' => ['count' => 3, 'client_secret' => 'secret-4']
			]
		];
		$filtered = $this->invoke('filterSensitiveParams', $data);
		self::assertSame(7, $filtered['page']);
		self::assertSame('************', $filtered['Password']);
		self::assertSame('************', $filtered['filters']['api_token']);
		self::assertSame('************', $filtered['filters']['csrfKey']);
		self::assertSame(3, $filtered['filters']['items']['count']);
		self::assertSame('************', $filtered['filters']['items']['client_secret']);
		self::assertStringNotContainsString('secret-1', json_encode($filtered, JSON_THROW_ON_ERROR));
	}

	public function testRecognizesPasswordTokenSecretAndCsrfKeyPatternsCaseInsensitively(): void {
		foreach (['passphrase', 'PWD', 'ACCESS_TOKEN', 'ClientSecret', 'csrfKey'] as $name) {
			self::assertTrue($this->invoke('isSensitiveKey', $name), $name);
		}
		foreach (['limit', 'page', 'category'] as $name) {
			self::assertFalse($this->invoke('isSensitiveKey', $name), $name);
		}
	}

	public function testRequestRowsAreSortedAndComplexValuesAreFormatted(): void {
		$rows = $this->invoke('paramsToRows', [
			'z' => [1, true],
			'a' => false
		]);
		self::assertSame(['a', 'z'], array_column($rows, 'key'));
		self::assertSame('no', $rows[0]['value']);
		self::assertSame([1, true], json_decode($rows[1]['value'], true, 512, JSON_THROW_ON_ERROR));
	}

	public function testNestedValueNormalizationLimitsExcessiveDepth(): void {
		$value = 'safe';
		for ($i = 0; $i < 8; $i++) $value = ['child' => $value];
		$normalized = $this->invoke('normalizeValue', $value);
		$json = json_encode($normalized, JSON_THROW_ON_ERROR);
		self::assertStringContainsString('...', $json);
		self::assertStringNotContainsString('safe', $json);
	}

	public function testBooleanNullAndObjectValuesHaveStableDisplayFormats(): void {
		self::assertSame('yes', $this->invoke('formatValue', true));
		self::assertSame('no', $this->invoke('formatValue', false));
		self::assertSame('', $this->invoke('formatValue', null));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
	}


	public function testSensitivityFilteringKeepsNonSensitiveNestedValuesAndMasksEmptySecrets(): void {
		$filtered = $this->invoke('filterSensitiveParams', [
			'page' => 0,
			'client_password' => '',
			'nested' => [
				'csrf_token' => ['value' => 'unsafe'],
				'public' => ['enabled' => true, 'list' => [1, 'two']]
			],
		]);
		self::assertSame(0, $filtered['page']);
		self::assertSame('************', $filtered['client_password']);
		self::assertSame('************', $filtered['nested']['csrf_token']);
		self::assertSame(['enabled' => true, 'list' => [1, 'two']], $filtered['nested']['public']);
	}

	public function testRequestParameterRowsHandleEmptyValuesAndNumericKeys(): void {
		self::assertSame([], $this->invoke('paramsToRows', []));
		$rows = $this->invoke('paramsToRows', ['last' => null, 7 => [], 'first' => 0]);
		self::assertSame(['7', 'first', 'last'], array_column($rows, 'key'));
		self::assertSame(['', '0', ''], array_column($rows, 'value'));
	}

	public function testDeepNormalizationConvertsObjectsAndRetainsBasicScalarTypes(): void {
		$input = ['name' => 'Grün', 'active' => false, 'count' => 2, 'unknown' => null, 'object' => new \stdClass()];
		self::assertSame([
			'name' => 'Grün', 'active' => false, 'count' => 2, 'unknown' => null, 'object' => 'stdClass'
		], $this->invoke('normalizeValue', $input));
		self::assertSame('...', $this->invoke('normalizeValue', 'secret', 6));
		self::assertSame('stdClass', $this->invoke('normalizeValue', new \stdClass()));
	}

	public function testListAndRowFormattingHandleEmptyAndMixedControllerValues(): void {
		self::assertSame('', $this->invoke('joinList', []));
		self::assertSame('root → course → 7', $this->invoke('joinList', ['root', 'course', 7]));
		self::assertSame(['label' => 'Result', 'key' => 'valid', 'value' => 'yes'], $this->invoke('row', 'Result', 'valid', true));
		self::assertSame('0', $this->invoke('formatValue', 0));
		self::assertSame('', $this->invoke('formatValue', []));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
	}

	public function testServerValueDoesNotAssumeServerGlobalsAreArrays(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = null;
			self::assertSame('', $this->invoke('serverValue', 'BASE3_DISPLAY_TEST_UNKNOWN'));
			$_SERVER = ['BASE3_DISPLAY_TEST_UNKNOWN' => false];
			self::assertSame('no', $this->invoke('serverValue', 'BASE3_DISPLAY_TEST_UNKNOWN'));
			$_SERVER = ['BASE3_DISPLAY_TEST_UNKNOWN' => ['nested' => 1]];
			self::assertSame(['nested' => 1], json_decode($this->invoke('serverValue', 'BASE3_DISPLAY_TEST_UNKNOWN'), true, 512, JSON_THROW_ON_ERROR));
		} finally {
			$_SERVER = $original;
		}
	}

	public function testGetRowsDecodeAndMaskSensitiveQueryParameters(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = ['QUERY_STRING' => 'page=3&access_token=sensitive&filters%5Bcsrf%5D=secret&filters%5Bsort%5D=up'];
			$rows = $this->invoke('getGetRows');
			$values = array_column($rows, 'value', 'key');
			self::assertSame('3', $values['page']);
			self::assertSame('************', $values['access_token']);
			$nested = json_decode($values['filters'], true, 512, JSON_THROW_ON_ERROR);
			self::assertSame('************', $nested['csrf']);
			self::assertSame('up', $nested['sort']);
		} finally {
			$_SERVER = $original;
		}
	}

	public function testServerRowsOnlyIncludeDeclaredAndNonEmptyFields(): void {
		$original = $_SERVER ?? null;
		try {
			$_SERVER = ['HTTP_ACCEPT_LANGUAGE' => 'de-DE', 'CONTENT_LENGTH' => 0, 'UNLISTED_SECRET' => 'secret'];
			$rows = $this->invoke('getServerRows');
			$values = array_column($rows, 'value', 'key');
			self::assertSame('de-DE', $values['HTTP_ACCEPT_LANGUAGE']);
			self::assertSame('0', $values['CONTENT_LENGTH']);
			self::assertArrayNotHasKey('UNLISTED_SECRET', $values);
		} finally {
			$_SERVER = $original;
		}
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasRequestDebugDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasRequestDebugDisplay::class, $method))->invoke($instance, ...$args);
	}
}
