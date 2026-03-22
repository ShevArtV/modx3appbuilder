<?php

namespace ComponentBuilder;

use MODX\Revolution\modX;
use PDO;

class SchemaExtractor
{
    private PDO $pdo;
    private string $dbName;
    private string $tablePrefix;

    public function __construct(private readonly modX $modx)
    {
        $this->pdo = $modx->getConnection()->getResource();
        $this->tablePrefix = $modx->config['table_prefix'] ?? 'modx_';

        $stmt = $this->pdo->query('SELECT DATABASE()');
        $this->dbName = $stmt->fetchColumn();
    }

    public function extract(string $packageName, string $outputPath): int
    {
        $prefix = $this->tablePrefix . strtolower($packageName) . '_';
        $tables = $this->findTables($prefix);

        if (empty($tables)) {
            echo "No tables found with prefix: {$prefix}\n";
            return 0;
        }

        $className = $this->toPascalCase($packageName);
        $xml = $this->generateSchema($tables, $prefix, $className);

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $xml);

        return count($tables);
    }

    private function findTables(string $prefix): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? ORDER BY TABLE_NAME"
        );
        $stmt->execute([$this->dbName, $prefix . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateSchema(array $tables, string $prefix, string $className): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<model package=\"{$className}\\Model\" baseClass=\"xPDO\\Om\\xPDOObject\" platform=\"mysql\" defaultEngine=\"InnoDB\" version=\"3.0\">\n";

        foreach ($tables as $tableInfo) {
            $xml .= $this->generateObject($tableInfo['TABLE_NAME'], $tableInfo['TABLE_COMMENT'] ?? '', $prefix, $className);
        }

        $xml .= "</model>\n";

        return $xml;
    }

    private function generateObject(string $table, string $comment, string $prefix, string $className): string
    {
        $shortName = substr($table, strlen($prefix));

        if (!empty($comment) && preg_match('/^[A-Z][a-zA-Z0-9]+$/', trim($comment))) {
            $objectClass = trim($comment);
        } else {
            $objectClass = $this->toPascalCase($shortName);
        }

        $tableName = substr($table, strlen($this->tablePrefix));

        $columns = $this->getColumns($table);
        $indexes = $this->getIndexes($table);

        $hasAutoIncrement = false;
        foreach ($columns as $col) {
            if (!empty($col['EXTRA']) && str_contains($col['EXTRA'], 'auto_increment')) {
                $hasAutoIncrement = true;
                break;
            }
        }

        $extends = $hasAutoIncrement ? 'xPDO\\Om\\xPDOSimpleObject' : 'xPDO\\Om\\xPDOObject';

        $xml = "\n    <object class=\"{$objectClass}\" table=\"{$tableName}\" extends=\"{$extends}\">\n";

        foreach ($columns as $col) {
            if ($hasAutoIncrement && $col['COLUMN_NAME'] === 'id') {
                continue;
            }

            $xml .= $this->generateField($col);
        }

        foreach ($indexes as $index) {
            if ($index['INDEX_NAME'] === 'PRIMARY') {
                continue;
            }

            $xml .= $this->generateIndex($index, $table);
        }

        $xml .= "    </object>\n";

        return $xml;
    }

    private function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([$this->dbName, $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIndexes(string $table): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT INDEX_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME"
        );
        $stmt->execute([$this->dbName, $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIndexColumns(string $table, string $indexName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME, COLLATION FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX"
        );
        $stmt->execute([$this->dbName, $table, $indexName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateField(array $col): string
    {
        $attrs = [
            'key' => $col['COLUMN_NAME'],
            'dbtype' => $this->extractDbType($col['COLUMN_TYPE']),
        ];

        $precision = $this->extractPrecision($col['COLUMN_TYPE']);
        if ($precision !== null) {
            $attrs['precision'] = $precision;
        }

        if (str_contains(strtoupper($col['COLUMN_TYPE']), 'UNSIGNED')) {
            $attrs['attributes'] = 'unsigned';
        }

        $attrs['phptype'] = $this->mapPhpType($col['DATA_TYPE'], $col['COLUMN_TYPE']);
        $attrs['null'] = $col['IS_NULLABLE'] === 'YES' ? 'true' : 'false';

        if ($col['COLUMN_DEFAULT'] !== null) {
            $attrs['default'] = $col['COLUMN_DEFAULT'];
        }

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= " {$k}=\"{$v}\"";
        }

        return "        <field{$attrStr}/>\n";
    }

    private function generateIndex(array $index, string $table): string
    {
        $name = $index['INDEX_NAME'];
        $unique = $index['NON_UNIQUE'] == '0' ? 'true' : 'false';

        $columns = $this->getIndexColumns($table, $name);

        $xml = "        <index alias=\"{$name}\" name=\"{$name}\" primary=\"false\" unique=\"{$unique}\" type=\"BTREE\">\n";

        foreach ($columns as $col) {
            $collation = $col['COLLATION'] ?? 'A';
            $xml .= "            <column key=\"{$col['COLUMN_NAME']}\" length=\"\" collation=\"{$collation}\" null=\"false\"/>\n";
        }

        $xml .= "        </index>\n";

        return $xml;
    }

    private function extractDbType(string $columnType): string
    {
        $type = preg_replace('/\(.*\)/', '', $columnType);
        $type = preg_replace('/\s+unsigned/i', '', $type);
        return strtolower(trim($type));
    }

    private function extractPrecision(string $columnType): ?string
    {
        if (preg_match('/\(([^)]+)\)/', $columnType, $m)) {
            return $m[1];
        }
        return null;
    }

    private function mapPhpType(string $dataType, string $columnType): string
    {
        $dataType = strtolower($dataType);

        if (in_array($dataType, ['tinyint']) && str_contains($columnType, '(1)')) {
            return 'boolean';
        }

        $map = [
            'int' => 'integer',
            'bigint' => 'integer',
            'smallint' => 'integer',
            'mediumint' => 'integer',
            'tinyint' => 'integer',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'float',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'string',
            'mediumtext' => 'string',
            'longtext' => 'string',
            'tinytext' => 'string',
            'blob' => 'string',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'string',
            'enum' => 'string',
            'set' => 'string',
            'json' => 'json',
        ];

        return $map[$dataType] ?? 'string';
    }

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
