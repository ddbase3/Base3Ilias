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


namespace Base3\Base3Ilias\Base3;

use Base3\Core\PluginClassMap;
use RuntimeException;

class Base3IliasClassMap extends PluginClassMap {

	protected function getScanTargets(): array {
		$targets = [];

		foreach (Base3IliasRuntime::getBase3Modules() as $name => $module) {
			$namespace = $module['namespace'] ?? null;
			if (!is_string($namespace) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
				throw new RuntimeException('Invalid ILIAS module namespace: ' . $name);
			}

			$srcPath = Base3IliasRuntime::resolveBase3ModulePath($module) . DIRECTORY_SEPARATOR . 'src';
			if (!is_dir($srcPath)) continue;

			$targets[] = [
				'module' => $name,
				'srcPath' => $srcPath,
				'namespace' => $namespace
			];
		}

		return $targets;
	}

	public function generate($regenerate = false): void {
		if (!$regenerate && is_file($this->classMapFile) && filesize($this->classMapFile) > 0) return;

		if (!is_writable(DIR_TMP)) die('Directory /tmp has to be writable.');

		$this->map = [];

		foreach ($this->getScanTargets() as $target) {
			$classes = [];
			$this->scanModuleClasses($classes, $target['srcPath'], $target['namespace']);
			$this->fillClassMap($target['module'], $classes);
		}

		$this->writeClassMap();
		$this->generateConstructorCache();
	}

	protected function scanModuleClasses(
		array &$classes,
		string $srcPath,
		string $namespace,
		string $path = ''
	): void {
		$fullPath = rtrim($srcPath, '/\\');
		if ($path !== '') $fullPath .= DIRECTORY_SEPARATOR . $path;

		foreach ($this->getEntries($fullPath) as $entry) {
			$fullEntry = $fullPath . DIRECTORY_SEPARATOR . $entry;

			if (is_dir($fullEntry)) {
				$childPath = $path === '' ? $entry : $path . DIRECTORY_SEPARATOR . $entry;
				$this->scanModuleClasses($classes, $srcPath, $namespace, $childPath);
				continue;
			}

			if (substr($entry, -4) !== '.php' || substr_count($entry, '.') !== 1) continue;

			require_once $fullEntry;

			$namespaceParts = [trim($namespace, '\\')];
			foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
				if ($part !== '') $namespaceParts[] = $part;
			}

			$className = implode('\\', $namespaceParts) . '\\' . substr($entry, 0, -4);
			if (!class_exists($className, false)) continue;

			$reflection = new \ReflectionClass($className);
			if ($reflection->isAbstract()) continue;

			$classes[] = [
				'file' => $fullEntry,
				'class' => $className,
				'interfaces' => class_implements($className)
			];
		}
	}
}
