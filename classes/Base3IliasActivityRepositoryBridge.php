<?php declare(strict_types=1);

namespace Base3\Base3Ilias;

use Closure;
use ILIAS\Component\Activities\Repository;
use RuntimeException;

/**
 * Lazy process-local bridge from the ILIAS 11 component dependency graph into
 * the embedded BASE3 runtime.
 *
 * ILIAS owns the Activities Repository inside its component bootstrap. BASE3 is
 * booted later through the legacy integration runtime. The ILIAS component
 * therefore publishes only a lazy resolver for the component-local bridge.
 * BASE3 registers the exact native Repository instance as a normal shared
 * service when a consumer first requests it.
 *
 * No repository is recreated and no Activity discovery is duplicated here.
 */
final class Base3IliasActivityRepositoryBridge {

	private static ?Closure $resolver = null;

	public function __construct(
		private readonly Repository $repository
	) {}

	public function getRepository(): Repository {
		return $this->repository;
	}

	public static function publishResolver(callable $resolver): void {
		self::$resolver = Closure::fromCallable($resolver);
	}

	public static function hasResolver(): bool {
		return self::$resolver !== null;
	}

	public static function current(): self {
		if (self::$resolver === null) {
			throw new RuntimeException(
				'ILIAS Activities Repository resolver has not been published by the Base3Ilias component bootstrap.'
			);
		}

		$bridge = (self::$resolver)();
		if (!$bridge instanceof self) {
			throw new RuntimeException('ILIAS Activities Repository bridge resolver returned an invalid value.');
		}

		return $bridge;
	}
}
