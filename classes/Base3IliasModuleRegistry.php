<?php declare(strict_types=1);

namespace Base3\Base3Ilias;

use RuntimeException;

final class Base3IliasModuleRegistry {

	private ?array $modules = null;

	/**
	 * @param string[] $discoveryRoots
	 */
	public function __construct(
		private readonly string $baseRoot,
		private readonly array $discoveryRoots,
		private readonly ?string $artifactFile = null
	) {}

	public function getModules(): array {
		if ($this->modules !== null) {
			return $this->modules;
		}

		if ($this->artifactFile !== null && is_file($this->artifactFile) && filesize($this->artifactFile) > 0) {
			$modules = require $this->artifactFile;
			if (!is_array($modules)) {
				throw new RuntimeException('Invalid Base3 modules artifact: ' . $this->artifactFile);
			}

			$this->modules = $modules;
			return $this->modules;
		}

		$this->modules = $this->discoverModules();
		if ($this->artifactFile !== null) {
			$this->writeArtifact($this->artifactFile, $this->modules);
		}

		return $this->modules;
	}

	public function getModule(string $name): ?array {
		$modules = $this->getModules();
		return $modules[$name] ?? null;
	}

	public function requireModule(string $name): array {
		$module = $this->getModule($name);
		if ($module === null) {
			throw new RuntimeException('Required Base3 module not found: ' . $name);
		}

		return $module;
	}

	public function getModulePath(string $name): ?string {
		$module = $this->getModule($name);
		return $module === null ? null : $this->resolveModulePath($module);
	}

	public function requireModulePath(string $name): string {
		return $this->resolveModulePath($this->requireModule($name));
	}

	public function resolveModulePath(array $module): string {
		$path = $module['path'] ?? null;
		if (!is_string($path) || trim($path) === '') {
			throw new RuntimeException('Base3 module path is missing.');
		}

		return rtrim($this->baseRoot, '/\\')
			. DIRECTORY_SEPARATOR
			. str_replace('/', DIRECTORY_SEPARATOR, trim($path, '/'));
	}

	private function discoverModules(): array {
		$baseRoot = realpath($this->baseRoot);
		if ($baseRoot === false || !is_dir($baseRoot)) {
			throw new RuntimeException('ILIAS root directory could not be resolved.');
		}

		$modules = [];
		$namespaces = [];
		$validRootFound = false;

		foreach ($this->discoveryRoots as $discoveryRoot) {
			if (!is_string($discoveryRoot) || trim($discoveryRoot) === '') continue;

			$resolvedRoot = realpath($discoveryRoot);
			if ($resolvedRoot === false || !is_dir($resolvedRoot)) continue;

			$this->assertInsideBaseRoot($baseRoot, $resolvedRoot);
			$validRootFound = true;
			$this->scanModules(
				$resolvedRoot,
				$baseRoot,
				$modules,
				$namespaces,
				basename($resolvedRoot) === 'components'
			);
		}

		if (!$validRootFound) {
			throw new RuntimeException('No Base3 module discovery root could be resolved.');
		}

		ksort($modules, SORT_NATURAL | SORT_FLAG_CASE);
		return $modules;
	}

	private function scanModules(
		string $directory,
		string $baseRoot,
		array &$modules,
		array &$namespaces,
		bool $componentsLevel = false
	): void {
		$entries = scandir($directory);
		if ($entries === false) {
			throw new RuntimeException('Could not read directory while discovering Base3 modules: ' . $directory);
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') continue;
			if ($entry[0] === '.') continue;
			if ($componentsLevel && $entry === 'ILIAS') continue;
			if (in_array($entry, ['vendor', 'node_modules'], true)) continue;

			$path = $directory . DIRECTORY_SEPARATOR . $entry;
			if (!is_dir($path) || is_link($path)) continue;

			$manifestFile = $path . DIRECTORY_SEPARATOR . 'base3.json';
			if (is_file($manifestFile)) {
				$this->registerModule($manifestFile, $path, $baseRoot, $modules, $namespaces);
			}

			$this->scanModules($path, $baseRoot, $modules, $namespaces);
		}
	}

	private function registerModule(
		string $manifestFile,
		string $moduleRoot,
		string $baseRoot,
		array &$modules,
		array &$namespaces
	): void {
		$json = file_get_contents($manifestFile);
		if ($json === false) {
			throw new RuntimeException('Could not read Base3 manifest: ' . $manifestFile);
		}

		try {
			$manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $e) {
			throw new RuntimeException('Invalid Base3 manifest JSON: ' . $manifestFile . ': ' . $e->getMessage(), 0, $e);
		}

		if (!is_array($manifest)) {
			throw new RuntimeException('Base3 manifest must contain a JSON object: ' . $manifestFile);
		}

		if (($manifest['manifestVersion'] ?? null) !== 1) {
			throw new RuntimeException('Unsupported Base3 manifest version: ' . $manifestFile);
		}

		$name = $this->requireManifestString($manifest, 'name', $manifestFile);
		$namespace = $this->requireManifestString($manifest, 'namespace', $manifestFile);
		$version = $this->requireManifestString($manifest, 'version', $manifestFile);

		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
			throw new RuntimeException('Invalid Base3 module namespace in manifest: ' . $manifestFile);
		}

		$nameKey = strtolower($name);
		foreach ($modules as $existingName => $existingModule) {
			if (strtolower((string) $existingName) !== $nameKey) continue;
			throw new RuntimeException(
				'Duplicate Base3 module name "' . $name . '": '
				. $this->resolveModulePath($existingModule) . ' and ' . $moduleRoot
			);
		}

		$namespaceKey = strtolower($namespace);
		if (isset($namespaces[$namespaceKey])) {
			throw new RuntimeException(
				'Duplicate Base3 module namespace "' . $namespace . '": '
				. $namespaces[$namespaceKey] . ' and ' . $moduleRoot
			);
		}

		$dependencies = $manifest['dependencies'] ?? [];
		if (!is_array($dependencies)) {
			throw new RuntimeException('Base3 module dependencies must be an object: ' . $manifestFile);
		}

		foreach ($dependencies as $dependency => $constraint) {
			if (!is_string($dependency) || trim($dependency) === '') {
				throw new RuntimeException('Invalid Base3 module dependency name: ' . $manifestFile);
			}
			if (!is_string($constraint) || trim($constraint) === '') {
				throw new RuntimeException('Invalid Base3 module dependency constraint: ' . $manifestFile);
			}
		}

		$relativePath = $this->relativePath($baseRoot, $moduleRoot);
		if ($relativePath === null || $relativePath === '') {
			throw new RuntimeException('Invalid Base3 module root: ' . $moduleRoot);
		}

		$modules[$name] = [
			'path' => $relativePath,
			'namespace' => $namespace,
			'version' => $version,
			'dependencies' => $dependencies
		];
		$namespaces[$namespaceKey] = $moduleRoot;
	}

	private function requireManifestString(array $manifest, string $key, string $manifestFile): string {
		$value = $manifest[$key] ?? null;
		if (!is_string($value) || trim($value) === '') {
			throw new RuntimeException('Missing or invalid Base3 manifest field "' . $key . '": ' . $manifestFile);
		}

		return trim($value);
	}

	private function assertInsideBaseRoot(string $baseRoot, string $path): void {
		if ($this->relativePath($baseRoot, $path) === null) {
			throw new RuntimeException('Base3 discovery root is outside the ILIAS root: ' . $path);
		}
	}

	private function relativePath(string $baseRoot, string $path): ?string {
		$baseRoot = rtrim(str_replace('\\', '/', $baseRoot), '/');
		$path = rtrim(str_replace('\\', '/', $path), '/');

		if ($path === $baseRoot) return '';

		$prefix = $baseRoot . '/';
		if (!str_starts_with($path, $prefix)) return null;

		return trim(substr($path, strlen($prefix)), '/');
	}

	private function writeArtifact(string $artifactFile, array $modules): void {
		$artifactDirectory = dirname($artifactFile);
		if (!is_dir($artifactDirectory) && !mkdir($artifactDirectory, 0770, true) && !is_dir($artifactDirectory)) {
			throw new RuntimeException('Could not create Base3 modules artifact directory: ' . $artifactDirectory);
		}

		$content = "<?php return " . var_export($modules, true) . ";\n";
		$tempFile = $artifactFile . '.' . getmypid() . '.tmp';

		if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
			throw new RuntimeException('Could not write Base3 modules artifact: ' . $tempFile);
		}

		if (!rename($tempFile, $artifactFile)) {
			@unlink($tempFile);
			throw new RuntimeException('Could not publish Base3 modules artifact: ' . $artifactFile);
		}
	}
}
