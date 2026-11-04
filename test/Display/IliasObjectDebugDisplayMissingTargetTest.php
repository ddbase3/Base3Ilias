<?php declare(strict_types=1);

namespace Base3Ilias\Test\Display;

use Base3Ilias\Display\IliasObjectDebugDisplay;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IliasObjectDebugDisplayMissingTargetTest extends TestCase {

	public function testMissingTargetProducesAnInformativeRowWithoutNativeObjectLookup(): void {
		$rows = $this->invoke('getObjectRows', 0);
		self::assertCount(2, $rows);
		self::assertSame('', $rows[0]['value']);
		self::assertSame('target', $rows[1]['key']);
		self::assertStringContainsString('No target ref_id', $rows[1]['value']);
	}

	public function testMissingTargetDoesNotQueryRepositoryPathsOrChildren(): void {
		self::assertSame([], $this->invoke('getPathRows', 0));
		self::assertSame([], $this->invoke('getChildRows', -1));
	}

	public function testObjectRowValueFormattingPreservesValidScalarValues(): void {
		self::assertSame('', $this->invoke('value', [], 'title'));
		self::assertSame('42', $this->invoke('value', ['obj_id' => 42], 'obj_id'));
		self::assertSame('yes', $this->invoke('value', ['valid' => true], 'valid'));
	}


	public function testTargetSelectionAcceptsOnlyPositiveNumericRequestValues(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$value = '45';
		$request->method('request')->willReturnCallback(static function(string $key, mixed $default = null) use (&$value): mixed {
			self::assertSame('base3_object_ref_id', $key);
			return $value;
		});
		$display = (new ReflectionClass(IliasObjectDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'request'))->setValue($display, $request);
		$method = new ReflectionMethod(IliasObjectDebugDisplay::class, 'getRequestTargetRefId');
		self::assertSame(45, $method->invoke($display));
		foreach (['', '-5', 'invalid', [], 0, null] as $value) {
			self::assertSame(0, $method->invoke($display));
		}
	}

	public function testFormattingObjectRowsPreservesScalarsAndHandlesUnknownTypes(): void {
		self::assertSame(['label' => 'Active', 'key' => 'active', 'value' => 'no'], $this->invoke('row', 'Active', 'active', false));
		self::assertSame('stdClass', $this->invoke('formatValue', new \stdClass()));
		self::assertSame('', $this->invoke('formatValue', []));
		self::assertSame('', $this->invoke('formatValue', null));
		self::assertSame('yes', $this->invoke('formatValue', true));
		self::assertSame(['a' => 1], json_decode($this->invoke('formatValue', ['a' => 1]), true, 512, JSON_THROW_ON_ERROR));
		self::assertSame('stdClass', $this->invoke('value', ['x' => new \stdClass()], 'x'));
	}

	public function testMissingTargetIsRenderedWithoutNativeObjectOrTreeLookups(): void {
		$request = $this->createMock(\Base3\Api\IRequest::class);
		$request->method('request')->with('base3_object_ref_id')->willReturn(null);
		$links = $this->createMock(\Base3\LinkTarget\Api\ILinkTargetService::class);
		$links->expects(self::once())->method('getLink')->with([
			'name' => IliasObjectDebugDisplay::getName(), 'out' => 'html'
		])->willReturn('/object-debug');
		$view = $this->createMock(\Base3\Api\IMvcView::class);
		$view->expects(self::once())->method('setTemplate')->with('Display/IliasObjectDebugDisplay.php');
		$view->method('getBricks')->willReturn(null);
		$view->method('loadTemplate')->willReturn('rendered');
		$assignments = [];
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assignments): void {
			$assignments[$key] = $value;
		});
		$display = (new ReflectionClass(IliasObjectDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'request'))->setValue($display, $request);
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'linkTargetService'))->setValue($display, $links);
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'view'))->setValue($display, $view);
		self::assertSame('rendered', $display->getOutput());
		self::assertSame(0, $assignments['targetRefId']);
		self::assertSame('base3_object_ref_id', $assignments['targetParamName']);
		self::assertSame('/object-debug', $assignments['endpoint']);
		self::assertCount(2, $assignments['objectRows']);
		self::assertSame([], $assignments['pathRows']);
		self::assertSame([], $assignments['childRows']);
		self::assertSame(100, $assignments['maxChildren']);
	}

	public function testTreePathRowsAndChildLimitUseNativeTreeResultWithoutObjectLookups(): void {
		if (!class_exists(\ilTree::class)) {
			self::markTestSkipped('Native ILIAS tree is unavailable outside ILIAS.');
		}
		$tree = $this->createMock(\ilTree::class);
		$tree->method('getPathFull')->willReturn([
			['depth' => 0, 'child' => 1, 'obj_id' => 10, 'type' => 'root', 'title' => 'Home'],
			['depth' => 1, 'child' => 23, 'obj_id' => 50, 'type' => 'crs', 'title' => 'Course'],
		]);
		$children = [];
		for ($i = 1; $i <= 105; $i++) {
			$children[] = ['child' => $i, 'obj_id' => 100 + $i, 'type' => 'file', 'title' => 'File ' . $i, 'description' => 'Example'];
		}
		$tree->method('getChilds')->willReturn($children);
		$display = (new ReflectionClass(IliasObjectDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'tree'))->setValue($display, $tree);
		$path = (new ReflectionMethod(IliasObjectDebugDisplay::class, 'getPathRows'))->invoke($display, 23);
		self::assertSame(['0', '1'], array_column($path, 'depth'));
		self::assertSame(['Home', 'Course'], array_column($path, 'title'));
		$rows = (new ReflectionMethod(IliasObjectDebugDisplay::class, 'getChildRows'))->invoke($display, 23);
		self::assertCount(100, $rows);
		self::assertSame('1', $rows[0]['ref_id']);
		self::assertSame('100', $rows[99]['ref_id']);
	}

	public function testNativeTreeFailuresAreReportedInDisplayRows(): void {
		if (!class_exists(\ilTree::class)) {
			self::markTestSkipped('Native ILIAS tree is unavailable outside ILIAS.');
		}
		$tree = $this->createMock(\ilTree::class);
		$tree->method('getPathFull')->willThrowException(new \RuntimeException('path unavailable'));
		$tree->method('getChilds')->willThrowException(new \RuntimeException('children unavailable'));
		$display = (new ReflectionClass(IliasObjectDebugDisplay::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(IliasObjectDebugDisplay::class, 'tree'))->setValue($display, $tree);
		$path = (new ReflectionMethod(IliasObjectDebugDisplay::class, 'getPathRows'))->invoke($display, 4);
		self::assertCount(1, $path);
		self::assertStringContainsString('path unavailable', $path[0]['title']);
		$children = (new ReflectionMethod(IliasObjectDebugDisplay::class, 'getChildRows'))->invoke($display, 4);
		self::assertCount(1, $children);
		self::assertStringContainsString('children unavailable', $children[0]['title']);
	}

	private function invoke(string $method, mixed ...$args): mixed {
		$instance = (new ReflectionClass(IliasObjectDebugDisplay::class))->newInstanceWithoutConstructor();
		return (new ReflectionMethod(IliasObjectDebugDisplay::class, $method))->invoke($instance, ...$args);
	}
}
