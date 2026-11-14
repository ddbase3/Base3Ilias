<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Base3Ilias for BASE3 Framework.
 *
 * Base3Ilias integrates BASE3 with the ILIAS learning platform.
 * It provides native identity, services, assets and lifecycle adapters.
 * Application modules use the shared BASE3 Foundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Base3Ilias\Event;

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
