<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IRequest;
use Base3Ilias\Base3\Base3IliasRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class Base3IliasRuntimeRequestSnapshotTest extends TestCase {

	private array $globalsBefore = [];

	protected function setUp(): void {
		parent::setUp();
		foreach (['_GET', '_POST', '_REQUEST', '_COOKIE', '_SESSION', '_SERVER', '_FILES'] as $key) {
			$this->globalsBefore[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
		}
	}

	protected function tearDown(): void {
		foreach ($this->globalsBefore as $key => [$existed, $value]) {
			if ($existed) $GLOBALS[$key] = $value;
			else unset($GLOBALS[$key]);
		}
		parent::tearDown();
	}

	public function testSnapshotRequestPreservesIndependentSourcesAndPostPrecedence(): void {
		$request = $this->request([
			'get' => ['shared' => 'get', 'onlyGet' => 'yes'],
			'post' => ['shared' => 'post', 'onlyPost' => 12],
			'request' => ['shared' => 'untrusted'],
			'cookie' => ['session' => 'cookie'],
			'session' => ['id' => 7],
			'server' => ['REQUEST_METHOD' => 'POST'],
			'files' => ['document' => ['name' => 'test.pdf']]
		]);

		self::assertInstanceOf(IRequest::class, $request);
		self::assertSame('get', $request->get('shared'));
		self::assertSame('post', $request->post('shared'));
		self::assertSame('post', $request->request('shared'));
		self::assertSame('fallback', $request->request('missing', 'fallback'));
		self::assertSame('cookie', $request->cookie('session'));
		self::assertSame(7, $request->session('id'));
		self::assertSame('POST', $request->server('REQUEST_METHOD'));
		self::assertSame(['name' => 'test.pdf'], $request->files('document'));
		self::assertSame(['shared' => 'post', 'onlyGet' => 'yes', 'onlyPost' => 12], $request->allRequest());
	}

	public function testSnapshotRequestReturnsCopiesOfEachSource(): void {
		$values = [
			'get' => ['g' => 1], 'post' => ['p' => 2], 'cookie' => ['c' => 3],
			'session' => ['s' => 4], 'server' => ['v' => 5], 'files' => ['f' => 6]
		];
		$request = $this->request($values);
		self::assertSame($values['get'], $request->allGet());
		self::assertSame($values['post'], $request->allPost());
		self::assertSame($values['cookie'], $request->allCookie());
		self::assertSame($values['session'], $request->allSession());
		self::assertSame($values['server'], $request->allServer());
		self::assertSame($values['files'], $request->allFiles());
	}

	public function testNestedKeysUsePhpBracketSyntaxAndKeepLiteralKeys(): void {
		$request = $this->request(['get' => [
			'items' => ['scope' => ['id' => 9]],
			'filters[scope][id]' => 'literal',
			'flag' => null,
			'number' => 5
		]]);
		self::assertSame('literal', $request->get('filters[scope][id]'));
		self::assertSame(9, $request->get('items[scope][id]'));
		self::assertSame(null, $request->get('flag', 'fallback'));
		self::assertSame('default', $request->get('missing[child]', 'default'));
		self::assertSame('default', $request->get('number[child]', 'default'));
		self::assertSame('default', $request->get('[][]', 'default'));
	}

	public function testJsonBodyAcceptsObjectsAndArraysWithoutThrowingOnInvalidInput(): void {
		self::assertSame(['text' => 'abc'], $this->request(['raw_input' => " {\"text\":\"abc\"} "])->getJsonBody());
		self::assertSame([1, 2], $this->request(['raw_input' => '[1,2]'])->getJsonBody());
		self::assertSame([], $this->request(['raw_input' => ''])->getJsonBody());
		self::assertSame([], $this->request(['raw_input' => 'null'])->getJsonBody());
		self::assertSame([], $this->request(['raw_input' => 'true'])->getJsonBody());
		self::assertSame([], $this->request(['raw_input' => '{bad'])->getJsonBody());
		self::assertSame([], $this->request(['raw_input' => ['wrong']])->getJsonBody());
	}

	public function testMalformedSnapshotSourceTypesAreTreatedAsEmpty(): void {
		$request = $this->request([
			'get' => null, 'post' => 'bad', 'cookie' => false,
			'session' => new \stdClass(), 'server' => 3, 'files' => 'invalid'
		]);
		self::assertSame([], $request->allRequest());
		self::assertSame([], $request->allGet());
		self::assertSame([], $request->allPost());
		self::assertSame([], $request->allCookie());
		self::assertSame([], $request->allSession());
		self::assertSame([], $request->allServer());
		self::assertSame([], $request->allFiles());
		self::assertSame('none', $request->get('name', 'none'));
	}

	public function testRestoreRequestSnapshotReplacesOnlySuppliedArraySources(): void {
		$GLOBALS['_SESSION'] = ['existing' => 'keep'];
		$GLOBALS['_SERVER'] = ['EXISTING' => 'keep'];
		$this->invoke('restoreRequestSnapshot', [
			'get' => ['new' => 1], 'post' => 'invalid',
			'request' => ['new' => 2], 'cookie' => null,
			'files' => ['new' => 3], 'session' => 'invalid', 'server' => null
		]);
		self::assertSame(['new' => 1], $GLOBALS['_GET']);
		self::assertSame([], $GLOBALS['_POST']);
		self::assertSame(['new' => 2], $GLOBALS['_REQUEST']);
		self::assertSame([], $GLOBALS['_COOKIE']);
		self::assertSame(['new' => 3], $GLOBALS['_FILES']);
		self::assertSame(['existing' => 'keep'], $GLOBALS['_SESSION']);
		self::assertSame(['EXISTING' => 'keep'], $GLOBALS['_SERVER']);
	}

	public function testRestoreSnapshotSupportsExplicitEmptySessionAndServer(): void {
		$GLOBALS['_SESSION'] = ['previous' => 1];
		$GLOBALS['_SERVER'] = ['PREVIOUS' => 1];
		$this->invoke('restoreRequestSnapshot', ['session' => [], 'server' => []]);
		self::assertSame([], $GLOBALS['_SESSION']);
		self::assertSame([], $GLOBALS['_SERVER']);
	}

	public function testCaptureRequestSnapshotUsesCurrentGlobals(): void {
		$GLOBALS['_GET'] = ['route' => 'read'];
		$GLOBALS['_POST'] = ['payload' => 'value'];
		$GLOBALS['_REQUEST'] = ['route' => 'read', 'payload' => 'value'];
		$GLOBALS['_COOKIE'] = ['token' => 'cookie'];
		$GLOBALS['_SESSION'] = ['uid' => 55];
		$GLOBALS['_SERVER'] = ['REQUEST_METHOD' => 'POST'];
		$GLOBALS['_FILES'] = ['file' => ['error' => 0]];

		$snapshot = $this->invoke('captureRequestSnapshot');
		self::assertSame(['route' => 'read'], $snapshot['get']);
		self::assertSame(['payload' => 'value'], $snapshot['post']);
		self::assertSame(['route' => 'read', 'payload' => 'value'], $snapshot['request']);
		self::assertSame(['token' => 'cookie'], $snapshot['cookie']);
		self::assertSame(['uid' => 55], $snapshot['session']);
		self::assertSame(['REQUEST_METHOD' => 'POST'], $snapshot['server']);
		self::assertSame(['file' => ['error' => 0]], $snapshot['files']);
		self::assertIsString($snapshot['raw_input']);
	}

	public function testSnapshotRequestUsesCliContextWithoutChangingHostSession(): void {
		$request = $this->request(['server' => ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json']]);
		self::assertSame(PHP_SAPI === 'cli', $request->isCli());
		if (PHP_SAPI === 'cli') self::assertSame(IRequest::CONTEXT_CLI, $request->getContext());
	}

	private function request(array $snapshot): IRequest {
		return $this->invoke('createRequestFromSnapshot', $snapshot);
	}

	private function invoke(string $name, mixed ...$arguments): mixed {
		return (new ReflectionMethod(Base3IliasRuntime::class, $name))->invoke(null, ...$arguments);
	}
}
