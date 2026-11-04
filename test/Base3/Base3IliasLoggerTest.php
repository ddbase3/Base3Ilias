<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3Ilias\Base3\Base3IliasLogger;
use Base3Ilias\Test\Fixture\Base3IliasLogWriterFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/Base3IliasLogWriterFixture.php';

final class Base3IliasLoggerTest extends TestCase {

	private bool $hadDic;
	private mixed $originalDic;

	protected function setUp(): void {
		parent::setUp();
		$this->hadDic = array_key_exists('DIC', $GLOBALS);
		$this->originalDic = $GLOBALS['DIC'] ?? null;
	}

	protected function tearDown(): void {
		if ($this->hadDic) $GLOBALS['DIC'] = $this->originalDic;
		else unset($GLOBALS['DIC']);
		parent::tearDown();
	}

	public function testPsrStyleLoggingDelegatesToNativeLogWriter(): void {
		$writer = new Base3IliasLogWriterFixture();
		$GLOBALS['DIC'] = ['ilLog' => $writer];
		$logger = new Base3IliasLogger();
		$logger->info('Hello', ['scope' => 'test', 'id' => 42]);
		$logger->warning('Be careful');
		self::assertCount(2, $writer->lines);
		self::assertStringStartsWith('[BASE3][INFO][test] Hello ', $writer->lines[0]);
		self::assertStringContainsString('"id":42', $writer->lines[0]);
		self::assertSame('[BASE3][WARNING][default] Be careful', $writer->lines[1]);
	}

	public function testLegacyLoggingAndReadOnlyScopeMetadata(): void {
		$writer = new Base3IliasLogWriterFixture();
		$GLOBALS['DIC'] = ['ilLog' => $writer];
		$logger = new Base3IliasLogger();
		self::assertTrue($logger->log('import', 'updated', 0));
		self::assertCount(1, $writer->lines);
		self::assertStringContainsString('[BASE3][import][' . date('Y-m-d H:i:s', 0) . '] updated', $writer->lines[0]);
		self::assertSame(['ilias'], $logger->getScopes());
		self::assertSame(1, $logger->getNumOfScopes());
		self::assertSame([], $logger->getLogs('ilias', 5));
	}
}
