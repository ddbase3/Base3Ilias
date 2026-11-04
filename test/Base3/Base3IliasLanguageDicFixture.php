<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

final class Base3IliasLanguageDicFixture {

	public function __construct(private readonly ?Base3IliasLanguageUserFixture $currentUser) {}

	public function user(): ?Base3IliasLanguageUserFixture {
		return $this->currentUser;
	}
}
