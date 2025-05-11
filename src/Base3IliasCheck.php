<?php declare(strict_types=1);

namespace Base3Ilias;

use Base3\Api\IOutput;
use Base3\Database\Api\IDatabase;

class Base3IliasCheck implements IOutput {

    public function __construct(
        private readonly IDatabase $database
    ) {}

    // Implementation of IBase

    public function getName() {
        return 'base3iliascheck';
    }

    // Implementation of IOutput

    public function getOutput($out = "html") {
        $start_time = microtime(true);
        $data = [];

        // Aktuelle Datenbank ermitteln
        $sql = 'SELECT DATABASE() AS current_database';
        $data['database'] = $this->database->scalarQuery($sql);

        // Anzahl Tabellen
        $sql = "SELECT COUNT(*) AS table_count
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['table_count'] = $this->database->scalarQuery($sql);

        // Gesamter Speicherplatz (in MB)
        $sql = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_mb
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['total_mb'] = $this->database->scalarQuery($sql);

        // Durchschnittlicher Speicher pro Tabelle (in MB)
        $sql = "SELECT ROUND(AVG(data_length + index_length) / 1024 / 1024, 2) AS avg_mb_per_table
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['avg_mb_per_table'] = $this->database->scalarQuery($sql);

        // Anzahl Zeilen gesamt
        $sql = "SELECT SUM(table_rows) AS total_rows
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['total_rows'] = $this->database->scalarQuery($sql);

        // Durchschnittliche Zeilenanzahl pro Tabelle
        $sql = "SELECT ROUND(AVG(table_rows), 2) AS avg_rows_per_table
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['avg_rows_per_table'] = $this->database->scalarQuery($sql);

        // Anzahl Tabellen ohne Primärschlüssel
        $sql = "SELECT COUNT(*) AS tables_without_pk
            FROM information_schema.tables t
            WHERE t.table_schema = '" . $data['database'] . "'
              AND t.table_type = 'BASE TABLE'
              AND NOT EXISTS (
                  SELECT 1 FROM information_schema.table_constraints tc
                  WHERE tc.table_schema = t.table_schema
                    AND tc.table_name = t.table_name
                    AND tc.constraint_type = 'PRIMARY KEY'
              )";
        $data['tables_without_pk'] = $this->database->scalarQuery($sql);

        // Anzahl Fremdschlüssel
        $sql = "SELECT COUNT(*) AS foreign_key_count
            FROM information_schema.referential_constraints
            WHERE constraint_schema = '" . $data['database'] . "'";
        $data['foreign_key_count'] = $this->database->scalarQuery($sql);

        // Anzahl verschiedener Speicher-Engines
        $sql = "SELECT COUNT(DISTINCT engine) AS engine_count
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['engine_count'] = $this->database->scalarQuery($sql);

        // Anzahl verschiedener Collations
        $sql = "SELECT COUNT(DISTINCT table_collation) AS collation_count
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['collation_count'] = $this->database->scalarQuery($sql);

        // Fragmentierter Speicher (MB)
        $sql = "SELECT ROUND(SUM(data_free) / 1024 / 1024, 2) AS fragmented_mb
            FROM information_schema.tables
            WHERE table_schema = '" . $data['database'] . "'";
        $data['fragmented_mb'] = $this->database->scalarQuery($sql);

        // Anzahl Views
        $sql = "SELECT COUNT(*) AS view_count
            FROM information_schema.views
            WHERE table_schema = '" . $data['database'] . "'";
        $data['view_count'] = $this->database->scalarQuery($sql);

        // Anzahl Indizes
        $sql = "SELECT COUNT(*) AS index_count
            FROM information_schema.statistics
            WHERE table_schema = '" . $data['database'] . "'";
        $data['index_count'] = $this->database->scalarQuery($sql);

        // Anzahl Trigger
        $sql = "SELECT COUNT(*) AS trigger_count
            FROM information_schema.triggers
            WHERE trigger_schema = '" . $data['database'] . "'";
        $data['trigger_count'] = $this->database->scalarQuery($sql);

        // Zeitmessung
        $end_time = microtime(true);
        $data['execution_time_seconds'] = round($end_time - $start_time, 4);

        return json_encode($data);
    }

    public function getHelp() {
        return 'Base3 ILIAS Check';
    }
}
