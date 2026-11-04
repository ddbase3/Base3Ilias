<?php declare(strict_types=1);

namespace Base3Ilias\Test\Base3;

use Base3\Api\IMvcView;
use Base3Ilias\Base3\Base3IliasRichTextEditorDisplay;
use PHPUnit\Framework\TestCase;

final class Base3IliasRichTextEditorDisplayTest extends TestCase {

	public function testConfiguredEditorForwardsNormalizedFieldsToSingleView(): void {
		$assigned = [];
		$view = $this->view($assigned);
		$editor = new Base3IliasRichTextEditorDisplay($view);
		$editor->setData([
			'id' => 'note-42', 'name' => 'content', 'value' => '<p>example</p>',
			'class' => ' extra ', 'rows' => 1500, 'readonly' => 'yes',
			'disabled' => 'off', 'spellcheck' => '0', 'aria_label' => 'Body',
			'placeholder' => 'Type here'
		]);
		self::assertSame('rendered', $editor->getOutput('html'));
		self::assertSame('base3iliasrichtexteditordisplay', $editor::getName());
		self::assertSame('note-42', $assigned['id']);
		self::assertSame('content', $assigned['name']);
		self::assertSame('<p>example</p>', $assigned['value']);
		self::assertSame('base3-rich-text-editor base3-ilias-rich-text-editor-source  extra', $assigned['className']);
		self::assertSame(1000, $assigned['rows']);
		self::assertSame(24000, $assigned['minimumHeight']);
		self::assertSame('Type here', $assigned['placeholder']);
		self::assertTrue($assigned['readonly']);
		self::assertFalse($assigned['disabled']);
		self::assertFalse($assigned['spellcheck']);
		self::assertSame('Body', $assigned['ariaLabel']);
		self::assertSame('node_modules/tinymce/tinymce.min.js', $assigned['tinyMceScriptUrl']);
	}

	public function testNonArrayInputRestoresDefaultsAndGeneratesValidId(): void {
		$assigned = [];
		$editor = new Base3IliasRichTextEditorDisplay($this->view($assigned));
		$editor->setData(['rows' => -10, 'disabled' => 1]);
		$editor->setData('invalid');
		self::assertSame('rendered', $editor->getOutput());
		self::assertMatchesRegularExpression('/\Abase3-rich-text-editor-[a-f0-9]{12}\z/', $assigned['id']);
		self::assertSame(12, $assigned['rows']);
		self::assertSame(288, $assigned['minimumHeight']);
		self::assertSame('', $assigned['value']);
		self::assertTrue($assigned['spellcheck']);
		self::assertFalse($assigned['readonly']);
		self::assertFalse($assigned['disabled']);
	}

	public function testRowsAndBooleanFallbackAreClampedWithoutTreatingUnknownStringsAsTrue(): void {
		$assigned = [];
		$editor = new Base3IliasRichTextEditorDisplay($this->view($assigned));
		$editor->setData(['rows' => '1', 'spellcheck' => 'unrecognized', 'readonly' => 'on', 'disabled' => []]);
		$editor->getOutput();
		self::assertSame(2, $assigned['rows']);
		self::assertSame(180, $assigned['minimumHeight']);
		self::assertTrue($assigned['spellcheck']);
		self::assertTrue($assigned['readonly']);
		self::assertFalse($assigned['disabled']);
		self::assertNotSame('', $editor->getHelp());
	}

	private function view(array &$assigned): IMvcView {
		$view = $this->createMock(IMvcView::class);
		$view->expects(self::once())->method('setPath')->with(dirname(__DIR__, 2));
		$view->expects(self::once())->method('setTemplate')->with('Base3/Base3IliasRichTextEditorDisplay.php');
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->expects(self::once())->method('loadTemplate')->willReturn('rendered');
		return $view;
	}
}
