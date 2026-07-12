<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of BASE3 Framework.
 *
 * BASE3 Framework is a lightweight, modular PHP framework for scalable
 * and maintainable web applications. Built for extensibility,
 * performance, and modern development, it can run standalone or
 * integrate as a subsystem within a host system.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Base3Framework
 **********************************************************************/

namespace Base3Ilias\Base3;

use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;

class Base3IliasStateStore implements IStateStore {

        private IDatabase $db;
        private string $tableName;
        private bool $initialized = false;

        public function __construct(IDatabase $db, string $tableName = 'base3_statestore') {
                $this->db = $db;
                $this->tableName = $tableName;
        }

        public function get(string $key, mixed $default = null): mixed {
                $this->ensureReady();

                $k = $this->esc($key);
                $row = $this->db->singleQuery(
                        "SELECT `value`, `expires_at`
                         FROM `{$this->tableName}`
                         WHERE `key` = '{$k}'
                         LIMIT 1"
                );

                if (!$row) {
                        return $default;
                }

                if ($this->isExpiredRow($row)) {
                        $this->delete($key);
                        return $default;
                }

                return $this->decode((string)$row['value'], $default);
        }

        public function has(string $key): bool {
                $this->ensureReady();

                $k = $this->esc($key);
                $row = $this->db->singleQuery(
                        "SELECT `expires_at`
                         FROM `{$this->tableName}`
                         WHERE `key` = '{$k}'
                         LIMIT 1"
                );

                if (!$row) {
                        return false;
                }

                if ($this->isExpiredRow($row)) {
                        $this->delete($key);
                        return false;
                }

                return true;
        }

        public function set(string $key, mixed $value, ?int $ttlSeconds = null): void {
                $this->ensureReady();

                $k = $this->esc($key);
                $v = $this->esc($this->encode($value));
                $expiresSql = $this->expiresSql($ttlSeconds);

                $this->db->nonQuery(
                        "INSERT INTO `{$this->tableName}` (`key`, `value`, `updated_at`, `expires_at`)
                         VALUES ('{$k}', '{$v}', NOW(), {$expiresSql})
                         ON DUPLICATE KEY UPDATE
                                `value` = VALUES(`value`),
                                `updated_at` = NOW(),
                                `expires_at` = VALUES(`expires_at`)"
                );
        }

        public function delete(string $key): bool {
                $this->ensureReady();

                $k = $this->esc($key);
                $existing = $this->db->singleQuery(
                        "SELECT `key`
                         FROM `{$this->tableName}`
                         WHERE `key` = '{$k}'
                         LIMIT 1"
                );

                if (!$existing) {
                        return false;
                }

                $this->db->nonQuery(
                        "DELETE FROM `{$this->tableName}` WHERE `key` = '{$k}'"
                );

                if ($this->db->isError()) {
                        return false;
                }

                $remaining = $this->db->singleQuery(
                        "SELECT `key`
                         FROM `{$this->tableName}`
                         WHERE `key` = '{$k}'
                         LIMIT 1"
                );

                return !$remaining;
        }

	/**
	 * Stores a value only when no active value exists for the key.
	 *
	 * ILIAS does not provide reliable affected-row or insert-id information.
	 * The method therefore checks the current value first and returns true after
	 * the write call completed without an exception.
	 */
        public function setIfNotExists(string $key, mixed $value, ?int $ttlSeconds = null): bool {
                $this->ensureReady();

                $k = $this->esc($key);
                $row = $this->db->singleQuery(
                        "SELECT `expires_at`
                         FROM `{$this->tableName}`
                         WHERE `key` = '{$k}'
                         LIMIT 1"
                );

                if ($row && !$this->isExpiredRow($row)) {
                        return false;
                }

                $v = $this->esc($this->encode($value));
                $expiresSql = $this->expiresSql($ttlSeconds);

                if ($row) {
                        $this->db->nonQuery(
                                "UPDATE `{$this->tableName}`
                                 SET `value` = '{$v}',
                                        `updated_at` = NOW(),
                                        `expires_at` = {$expiresSql}
                                 WHERE `key` = '{$k}'"
                        );
                }
                else {
                        $this->db->nonQuery(
                                "INSERT INTO `{$this->tableName}` (`key`, `value`, `updated_at`, `expires_at`)
                                 VALUES ('{$k}', '{$v}', NOW(), {$expiresSql})"
                        );
                }

                return true;
        }

        public function listKeys(string $prefix): array {
                $this->ensureReady();

                $p = $this->esc($prefix);
                $keys = $this->db->listQuery(
                        "SELECT `key`
                         FROM `{$this->tableName}`
                         WHERE `key` LIKE '{$p}%'
                         ORDER BY `key` ASC"
                );

                return is_array($keys) ? $keys : [];
        }

        public function flush(): void {
                // Database writes are immediate.
        }

        private function ensureReady(): void {
                $this->db->connect();
                if ($this->initialized) {
                        return;
                }

                $this->ensureTable();
                $this->initialized = true;
        }

        private function ensureTable(): void {
                $this->db->nonQuery(
                        "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                                `key` VARCHAR(255) NOT NULL,
                                `value` MEDIUMTEXT NOT NULL,
                                `updated_at` DATETIME NOT NULL,
                                `expires_at` DATETIME NULL,
                                PRIMARY KEY (`key`),
                                INDEX `idx_expires_at` (`expires_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
        }

        private function isExpiredRow(array $row): bool {
                if (!isset($row['expires_at']) || $row['expires_at'] === null || $row['expires_at'] === '') {
                        return false;
                }

                $timestamp = strtotime((string)$row['expires_at']);
                if ($timestamp === false) {
                        return false;
                }

                return $timestamp <= time();
        }

        private function expiresSql(?int $ttlSeconds): string {
                if ($ttlSeconds === null) {
                        return 'NULL';
                }

                if ($ttlSeconds <= 0) {
                        return 'NOW()';
                }

                $ttl = (int)$ttlSeconds;
                return "DATE_ADD(NOW(), INTERVAL {$ttl} SECOND)";
        }

        private function encode(mixed $value): string {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json !== false) {
                        return $json;
                }

                return json_encode([
                        '__error' => 'json_encode_failed',
                        '__type' => gettype($value)
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"__error":"json_encode_failed"}';
        }

        private function decode(string $json, mixed $default): mixed {
                $value = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                        return $default;
                }

                return $value;
        }

        private function esc(string $str): string {
                return $this->db->escape($str);
        }
}
