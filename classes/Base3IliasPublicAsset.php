<?php declare(strict_types=1);

namespace Base3\Base3Ilias;

use ILIAS\Component\Resource\PublicAsset;

class Base3IliasPublicAsset implements PublicAsset {

	public function __construct(
		private readonly string $source,
		private readonly string $target
	) {}

	public function getSource(): string {
		return $this->source;
	}

	public function getTarget(): string {
		return $this->target;
	}
}
