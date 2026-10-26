<?php declare(strict_types=1);

namespace Base3Ilias\Ilias;

use Base3\Event\Api\IEventManager;
use Base3Ilias\Base3\Base3IliasRuntime;
use Base3Ilias\Event\IliasApplicationEvent;

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
