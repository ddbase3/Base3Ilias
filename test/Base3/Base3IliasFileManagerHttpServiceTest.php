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


namespace Base3\Base3Ilias\Test\Base3;

use Base3\Base3Ilias\Base3\Base3IliasFileManagerHttpService;
use PHPUnit\Framework\TestCase;

final class Base3IliasFileManagerHttpServiceTest extends TestCase {

	public function testAllFileManagerActionsUseTheSamePublicDispatchBoundary(): void {
		$baseUrl = 'index.php?name=base3iliasfilemanageroutput&out=php';
		$endpoints = Base3IliasFileManagerHttpService::buildEndpoints($baseUrl);
		self::assertSame([
			'list', 'stat', 'mkdir', 'delete', 'rmdir', 'copy', 'move',
			'uploadStart', 'uploadChunk', 'uploadStatus', 'uploadFinish', 'uploadAbort', 'download'
		], array_keys($endpoints));
		foreach ($endpoints as $action => $url) {
			parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
			self::assertSame('base3iliasfilemanageroutput', $params['name']);
			self::assertSame('php', $params['out']);
			self::assertSame($action, $params['fm_action']);
		}
	}

	public function testEndpointParametersAreUrlEncodedAndOnlyOneQuestionMarkIsAppended(): void {
		$endpoints = Base3IliasFileManagerHttpService::buildEndpoints('/files', 'action name');
		self::assertSame('/files?action%20name=uploadChunk', $endpoints['uploadChunk']);
		$withQuery = Base3IliasFileManagerHttpService::buildEndpoints('/files?token=abc');
		self::assertSame('/files?token=abc&fm_action=download', $withQuery['download']);
	}
}
