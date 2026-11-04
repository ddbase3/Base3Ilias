<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasUserDebugDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasUserDebugDisplayProfileTest extends TestCase {

	public function testMissingProfileFieldsAreDisplayedAsEmpty(): void {
		self::assertSame('', $this->invoke('profileValue', [], 'email'));
		self::assertSame('', $this->invoke('boolProfileValue', [], 'active'));
		self::assertSame('', $this->invoke('timestampProfileValue', [], 'created'));
	}

	public function testBooleanProfileValuesAreMappedToTheDisplayLabels(): void {
		self::assertSame('yes', $this->invoke('boolProfileValue', ['active' => '1'], 'active'));
		self::assertSame('no', $this->invoke('boolProfileValue', ['active' => '0'], 'active'));
		self::assertSame('', $this->invoke('boolProfileValue', ['active' => ''], 'active'));
	}

	public function testPositiveTimestampsIncludeRawTimeAndReadableDate(): void {
		self::assertSame('', $this->invoke('timestampProfileValue', ['created' => 0], 'created'));
		self::assertSame('', $this->invoke('timestampProfileValue', ['created' => null], 'created'));
		$value = $this->invoke('timestampProfileValue', ['created' => 1609459200], 'created');
		self::assertStringContainsString('1609459200 (', $value);
		self::assertSame(')', substr($value, -1));
	}

	public function testProfileValuePreservesScalarsAndSerializesArrays(): void {
		self::assertSame('0', $this->invoke('profileValue', ['id' => 0], 'id'));
		$formatted = $this->invoke('profileValue', ['groups' => ['one', 'two']], 'groups');
		self::assertSame(['one', 'two'], json_decode($formatted, true, 512, JSON_THROW_ON_ERROR));
	}


	public function testMissingUserAvoidsNativeLookupsForDependentDetails(): void {
		self::assertFalse($this->invoke('userExists', 0));
		self::assertSame([], $this->invoke('getStatusRows', 0));
		self::assertSame([], $this->invoke('getPreferenceRows', 0));
		self::assertSame([], $this->invoke('getRoleRows', 0));
		self::assertSame([], $this->invoke('getGlobalRoleRows', 0));
		self::assertSame([], $this->invoke('getProfileData', 0));
		self::assertSame(['base3_user_id', 'user'], array_column($this->invoke('getUserRows', 0), 'key'));
	}

	public function testProfileValuesRespectNullBooleanZeroAndObjectTypes(): void {
		self::assertSame('', $this->invoke('profileValue', ['given' => null], 'given'));
		self::assertSame('0', $this->invoke('profileValue', ['given' => 0], 'given'));
		self::assertSame('yes', $this->invoke('profileValue', ['given' => true], 'given'));
		self::assertSame('stdClass', $this->invoke('profileValue', ['given' => new \stdClass()], 'given'));
		self::assertSame('', $this->invoke('profileValue', ['given' => []], 'given'));
	}

	public function testBooleanProfileValuesDistinguishMissingFalseAndPositive(): void {
		self::assertSame('no', $this->invoke('boolProfileValue', ['active' => 0], 'active'));
		self::assertSame('no', $this->invoke('boolProfileValue', ['active' => '-1'], 'active'));
		self::assertSame('yes', $this->invoke('boolProfileValue', ['active' => 5], 'active'));
		self::assertSame('', $this->invoke('boolProfileValue', [], 'active'));
	}

	public function testNonPositiveAndMalformedTimestampsDoNotProduceDateDetails(): void {
		self::assertSame('', $this->invoke('timestampProfileValue', ['created' => -1], 'created'));
		self::assertSame('', $this->invoke('timestampProfileValue', ['created' => 'invalid'], 'created'));
		$value = $this->invoke('timestampProfileValue', ['created' => '1609459200'], 'created');
		self::assertStringStartsWith('1609459200 (', $value);
		self::assertStringEndsWith(')', $value);
	}

	public function testCurrentUserSelectionAcceptsPositiveRequestedUserId(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$request->method('request')->willReturnCallback(static function(string $key, mixed $default = null): mixed {
			return match ($key) {
				'base3_user_login' => '',
				'base3_user_id' => '23',
				default => $default
			};
		});
		$display = (new ReflectionClass(IliasUserDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasUserDebugDisplay::class, 'request'))->setValue($display, $request);
		self::assertSame(['user_id' => 23, 'login' => '', 'message' => ''], (new ReflectionMethod(IliasUserDebugDisplay::class, 'getUserSelection'))->invoke($display));
	}

	public function testUserRowAndFormatterPreserveScalarAndNestedData(): void {
		self::assertSame(['label' => 'Login', 'key' => 'login', 'value' => 'alice'], $this->invoke('row', 'Login', 'login', 'alice'));
		self::assertSame('no', $this->invoke('formatValue', false));
		self::assertSame('yes', $this->invoke('formatValue', true));
		self::assertSame('', $this->invoke('formatValue', []));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
		self::assertSame(['roles' => [1, 2]], json_decode($this->invoke('formatValue', ['roles' => [1, 2]]), true, 512, JSON_THROW_ON_ERROR));
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasUserDebugDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasUserDebugDisplay::class, $method))->invoke($instance, ...$args);
	}
}
