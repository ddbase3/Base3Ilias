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


namespace Base3\Base3Ilias\Diagnostics;

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Diagnostics\Api\IDiagnosticCheck;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Core\Diagnostics\Scope\PrivacyDiagnosticScope;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\Base3Ilias\Job\Base3LogRetention;
use RuntimeException;

/** Read-only check of the optional BASE3 database logger's scheduled retention. */
final class Base3LogRetentionDiagnosticCheck implements IDiagnosticCheck {

	public function __construct(
		private readonly IDatabase $database,
		private readonly IConfiguration $configuration,
		private readonly IStateStore $state
	) {}

	public static function getName(): string {
		return 'base3logretentiondiagnosticcheck';
	}

	public function getScope(): string {
		return PrivacyDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$this->database->connect();
		if(!$this->database->connected()) {
			throw new RuntimeException('BASE3 log diagnostic database unavailable.');
		}
		if(!Base3LogRetention::tableExists($this->database)) {
			return new DiagnosticCheckResult(DiagnosticCheckResult::NOT_APPLICABLE, 'not_applicable');
		}

		$retention = Base3LogRetention::retentionHours($this->state);
		$threshold = $retention + Base3LogRetention::DIAGNOSTIC_GRACE_HOURS;
		$cutoff = Base3LogRetention::cutoffSqlString($threshold);
		$count = $this->database->scalarQuery(
			"SELECT COUNT(*) FROM `" . Base3LogRetention::TABLE . "` WHERE `timestamp` < '" . $this->database->escape($cutoff) . "'"
		);
		if(!is_numeric($count) || (float)$count < 0 || (float)$count > PHP_INT_MAX) {
			throw new RuntimeException('BASE3 log diagnostic returned an invalid count.');
		}

		$overdue = (int)$count;
		$active = Base3LogRetention::isCleanupActive((array)$this->configuration->get('job'));
		$status = !$active || $overdue > 0 ? DiagnosticCheckResult::FAILED : DiagnosticCheckResult::PASSED;
		return new DiagnosticCheckResult(
			$status,
			!$active ? 'cleanup_disabled' : ($overdue > 0 ? 'failed' : 'passed'),
			['count' => $overdue, 'hours' => $threshold],
			$status === DiagnosticCheckResult::FAILED ? 'remediation' : '',
			['overdue' => $overdue, 'retentionHours' => $retention, 'graceHours' => Base3LogRetention::DIAGNOSTIC_GRACE_HOURS]
		);
	}

}
