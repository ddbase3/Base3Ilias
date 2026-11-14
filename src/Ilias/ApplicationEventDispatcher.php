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


namespace Base3\Base3Ilias\Ilias;

use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\Base3Ilias\Base3\Base3IliasRuntime;
use Base3\Base3Ilias\Event\IliasApplicationEvent;

require_once dirname(__DIR__) . '/Base3/Base3IliasRuntime.php';

/** Single native-component boundary for application events in the BASE3 runtime. */
final class ApplicationEventDispatcher {

	/**
	 * Delivers an ILIAS event to all registered BASE3 listeners.
	 *
	 * @param string $component Native ILIAS event source identifier.
	 * @param string $name Native ILIAS event identifier.
	 * @param array<string,mixed> $parameters Native ILIAS event parameters.
	 * @return void
	 * @throws \Throwable If the BASE3 runtime cannot initialize or a listener fails.
	 * @sideEffect Boots BASE3 once in the current ILIAS request and notifies listeners synchronously.
	 */
	public static function handleEvent(string $component, string $name, array $parameters): void {
		$container = Base3IliasRuntime::bootOnce(false, false);
		$container->get(IEventManager::class)->fire(new IliasApplicationEvent($component, $name, $parameters));
	}
}
