<?php declare(strict_types=1);

namespace Base3Ilias\Base3;

use Base3\Core\PluginClassMap;

class Base3IliasClassMap extends PluginClassMap {

	protected function getScanTargets(): array {
		return [
			["basedir" => DIR_SRC, "subdir" => "", "subns" => "Base3"]
		];
	}

	public function generate($regenerate = false): void {
		if (!$regenerate && file_exists($this->classMapFile) && filesize($this->classMapFile) > 0) return;

		if (!is_writable(DIR_TMP)) die('Directory /tmp has to be writable.');

		$this->map = [];

		foreach ($this->getScanTargets() as $target) {
			$basedir = $target['basedir'];
			$subdir = $target['subdir'] ?? '';
			$subns = $target['subns'] ?? '';

			$apps = isset($target['app'])
				? [$target['app']]
				: $this->getEntries($basedir);

			foreach ($apps as $app) {
				$apppath = $basedir . DIRECTORY_SEPARATOR . $app;
				if (!empty($subdir)) $apppath .= DIRECTORY_SEPARATOR . $subdir;
				if (!is_dir($apppath)) continue;

				$classes = [];
				$this->scanClasses($classes, $basedir, $app, $subdir, $subns);
				$this->fillClassMap($app, $classes);
			}
		}

		foreach (Base3IliasRuntime::getBase3Modules() as $name => $module) {
			if ($name === 'Base3Framework') continue;

			$srcPath = Base3IliasRuntime::resolveBase3ModulePath($module) . DIRECTORY_SEPARATOR . 'src';
			if (!is_dir($srcPath)) continue;

			$classes = [];
			$this->scanModuleClasses($classes, $srcPath, (string) $module['namespace']);
			$this->fillClassMap($name, $classes);
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
