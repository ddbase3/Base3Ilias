<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Session\Api\ISession;

/**
 * Class Base3IliasSession
 *
 * Adapter to use the active ILIAS session as BASE3 session backend.
 * BASE3 does not own the ILIAS session lifecycle.
 */
class Base3IliasSession implements ISession {

	private const PREFIX = 'base3_';

	private mixed $dic = null;

	private bool $available = false;

	public function __construct() {
		global $DIC;
		$this->dic = $DIC ?? null;
		$this->available = class_exists('ilSession');
	}

	public function started(): bool {
		return session_status() === PHP_SESSION_ACTIVE;
	}

	public function getId(): string {
		$id = session_id();

		return is_string($id) ? $id : '';
	}

	public function start(): bool {
		return $this->started();
	}

	public function destroy(): bool {
		if (!$this->available) {
			return false;
		}

		foreach (array_keys($_SESSION ?? []) as $key) {
			if (str_starts_with((string) $key, self::PREFIX)) {
				\ilSession::clear((string) $key);
			}
		}

		return true;
	}

	public function get(string $key, mixed $default = null): mixed {
		if (!$this->available) {
			return $default;
		}

		$value = \ilSession::get($this->getSessionKey($key));

		return $value ?? $default;
	}

	public function set(string $key, mixed $value): void {
		if (!$this->available) {
			return;
		}

		\ilSession::set($this->getSessionKey($key), $value);
	}

	public function has(string $key): bool {
		if (!$this->available) {
			return false;
		}

		return \ilSession::has($this->getSessionKey($key));
	}

	public function remove(string $key): void {
		if (!$this->available) {
			return;
		}

		\ilSession::clear($this->getSessionKey($key));
	}

	private function getSessionKey(string $key): string {
		return self::PREFIX . $key;
	}
}
