<?php declare(strict_types=1);

namespace Base3Ilias\Test\Fixture;

/** Captures only the native logger's write boundary; no global service registration. */
final class Base3IliasLogWriterFixture {

	/** @var list<string> */
	public array $lines = [];

	public function write(string $line): void {
		$this->lines[] = $line;
	}
}
