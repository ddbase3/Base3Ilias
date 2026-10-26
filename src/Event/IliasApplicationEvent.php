<?php declare(strict_types=1);

namespace Base3Ilias\Event;

/**
 * Notification emitted after ILIAS delivers an application event to a native
 * component. The native component name and payload are preserved unchanged.
 * Consumers own their event-specific interpretation and side effects.
 */
final class IliasApplicationEvent {

	/**
	 * @param string $component Original ILIAS event source (e.g. components/ILIAS/ILIASObject).
	 * @param string $name Original event name.
	 * @param array<string,mixed> $parameters Original ILIAS event parameters.
	 */
	public function __construct(
		public readonly string $component,
		public readonly string $name,
		public readonly array $parameters
	) {}
}
