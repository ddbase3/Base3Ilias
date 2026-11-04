<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

final class Base3IliasLanguageUserFixture {

	public function __construct(private readonly string $language) {}

	public function getLanguage(): string {
		return $this->language;
	}
}
