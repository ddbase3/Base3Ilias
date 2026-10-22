<?php declare(strict_types=1);

namespace Base3Ilias\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;
use Base3\Worker\Api\IPolicyControlledJob;
use Base3\Worker\Policy\PolicyControlledJobTrait;

/**
 * Base3LogCleanupJob
 *
 * Deletes old rows from "base3_log" based on a retention window.
 *
 * Scheduling:
 * - Controlled by DailyWindowJobPolicy.
 * - Runs at most once per day, only within 02:00-04:00.
 *
 * Retention:
 * - Default: 48 hours (configurable via state key "retention_hours").
 *
 * Behavior:
 * - If the log table does not exist, the job skips silently (nothing to clean).
 * - The job does not create or migrate schema (no magic).
 */
final class Base3LogCleanupJob implements IPolicyControlledJob {

	use PolicyControlledJobTrait;

	private const DEFAULT_PRIORITY = 1;

	private ?array $missionbayIliasConf = null;

	public function __construct(
		private readonly IDatabase $db,
		private readonly IConfiguration $configuration,
		private readonly IStateStore $state
	) {}

	public static function getName(): string {
		return 'base3logcleanupjob';
	}

	public function isActive() {
		return Base3LogRetention::isCleanupActive($this->getMissionbayIliasConf());
	}

	public function getPriority() {
		$conf = $this->getMissionbayIliasConf();
		return (int)($conf['base3logcleanupjob.priority'] ?? self::DEFAULT_PRIORITY);
	}

	public function getPolicyDefinition(): array {
		return [
			'policy' => 'dailywindowjobpolicy',
			'data' => [
				'from' => '02:00',
				'to' => '04:00'
			]
		];
	}

	public function go() {
		$this->db->connect();
		if (!$this->db->connected()) {
			return 'DB not connected';
		}

		if (!Base3LogRetention::tableExists($this->db)) {
			return 'Skip (base3_log does not exist)';
		}

		$retentionHours = Base3LogRetention::retentionHours($this->state);
		$deleteBatch = Base3LogRetention::deleteBatch($this->state);
		$cutoff = Base3LogRetention::cutoffSqlString($retentionHours);

		$this->deleteOldLogs($cutoff, $deleteBatch);

		$this->markRun();

		return 'Log cleanup done (cutoff: ' . $cutoff . ', limit: ' . $deleteBatch . ')';
	}

	private function getMissionbayIliasConf(): array {
		if ($this->missionbayIliasConf === null) {
			$this->missionbayIliasConf = (array)$this->configuration->get('job');
		}
		return $this->missionbayIliasConf;
	}

	/* ---------- Cleanup ---------- */

	private function deleteOldLogs(string $cutoff, int $limit): void {
		// Delete oldest first for predictable range deletes.
		$this->exec(
			"DELETE FROM base3_log
			WHERE `timestamp` < '" . $this->esc($cutoff) . "'
			ORDER BY id ASC
			LIMIT " . (int)$limit
		);
	}

	/* ---------- DB helpers ---------- */

	private function exec(string $sql): void {
		$this->db->nonQuery($sql);
	}

	private function esc(string $value): string {
		return (string)$this->db->escape($value);
	}
}
