<?php declare(strict_types=1);

namespace Base3Ilias\Job;

use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;

/** Shared retention configuration and read-only schema inspection for the BASE3 database log cleanup job. */
final class Base3LogRetention {

	public const TABLE = 'base3_log';
	public const DEFAULT_HOURS = 48;
	public const DEFAULT_BATCH = 100000;
	public const DIAGNOSTIC_GRACE_HOURS = 24;
	private const STATE_PREFIX = 'base3ilias.job.base3logcleanup.';

	public static function isCleanupActive(array $settings): bool {
		return ((int)($settings['base3logcleanupjob.active'] ?? 0)) === 1;
	}

	public static function retentionHours(IStateStore $state): int {
		$raw = $state->get(self::STATE_PREFIX . 'retention_hours', self::DEFAULT_HOURS);
		$hours = (int)$raw;
		return $hours > 0 ? $hours : self::DEFAULT_HOURS;
	}

	public static function deleteBatch(IStateStore $state): int {
		$raw = $state->get(self::STATE_PREFIX . 'delete_batch', self::DEFAULT_BATCH);
		$batch = (int)$raw;
		return $batch > 0 ? $batch : self::DEFAULT_BATCH;
	}

	public static function cutoffSqlString(int $hours): string {
		return date('Y-m-d H:i:s', time() - ($hours * 3600));
	}

	public static function tableExists(IDatabase $db): bool {
		return !empty($db->singleQuery("SHOW TABLES LIKE '" . self::TABLE . "'"));
	}

}
