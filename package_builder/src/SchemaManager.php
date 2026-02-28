<?php

namespace ComponentBuilder;

use MODX\Revolution\modX;
use DOMDocument;
use DOMXPath;

class SchemaManager
{
    public function __construct(private readonly modX $modx, private readonly array $config)
    {
    }

    /**
     * @param string $schemaFile
     * @param string $outputDir
     * @return bool
     */
    public function generateClasses(string $schemaFile, string $outputDir): bool
    {
        if (!file_exists($schemaFile)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Schema file not found: {$schemaFile}");
            return false;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Generating classes from schema: {$schemaFile}");

        $manager = $this->modx->getManager();
        $generator = $manager->getGenerator();

        $namespacePrefix = $this->config['name'] ?? '';
        if (!empty($this->config['namespace_prefix'])) {
            $namespacePrefix = $this->config['namespace_prefix'];
        }

        $generator->parseSchema(
            $schemaFile,
            $outputDir,
            [
                'compile' => 0,
                'update' => 0,
                'regenerate' => 1,
                'namespacePrefix' => rtrim($namespacePrefix, '\\') . '\\',
            ]
        );

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Model classes generated');

        return true;
    }

    /**
     * @param string $schemaFile
     * @param string $packageName
     * @return bool
     */
    public function updateDatabase(string $schemaFile, string $packageName): bool
    {
        if (!file_exists($schemaFile)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Schema file not found: {$schemaFile}");
            return false;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Updating database from schema: {$schemaFile}");

        $this->modx->addPackage($packageName, dirname($schemaFile, 2));

        $manager = $this->modx->getManager();
        $dom = new DOMDocument();
        $dom->load($schemaFile);

        $xpath = new DOMXPath($dom);
        $objects = $xpath->query('//object');

        foreach ($objects as $object) {
            $className = $object->getAttribute('class');

            if ($manager->createObjectContainer($className)) {
                $this->modx->log(modX::LOG_LEVEL_INFO, "Created table for class: {$className}");
            } else {
                $this->modx->log(modX::LOG_LEVEL_WARN, "Table already exists for class: {$className}");
            }
        }

        return true;
    }

    /**
     * @param string $schemaFile
     * @return array
     */
    public function validateSchema(string $schemaFile): array
    {
        $errors = [];

        if (!file_exists($schemaFile)) {
            $errors[] = "Schema file not found: {$schemaFile}";
            return $errors;
        }

        $dom = new DOMDocument();
        if (!$dom->load($schemaFile)) {
            $errors[] = "Invalid XML in schema file: {$schemaFile}";
            return $errors;
        }

        $xpath = new DOMXPath($dom);
        $objects = $xpath->query('//object');

        if ($objects->length === 0) {
            $errors[] = "No objects found in schema";
        }

        foreach ($objects as $object) {
            $className = $object->getAttribute('class');
            $tableName = $object->getAttribute('table');

            if (empty($className)) {
                $errors[] = "Object missing class attribute";
            }

            if (empty($tableName)) {
                $errors[] = "Object missing table attribute";
            }

            $fields = $object->getElementsByTagName('field');
            foreach ($fields as $field) {
                $key = $field->getAttribute('key');
                $dbtype = $field->getAttribute('dbtype');
                $phptype = $field->getAttribute('phptype');

                if (empty($key)) {
                    $errors[] = "Field missing key attribute in object {$className}";
                }

                if (empty($dbtype)) {
                    $errors[] = "Field {$key} missing dbtype attribute in object {$className}";
                }

                if (empty($phptype)) {
                    $errors[] = "Field {$key} missing phptype attribute in object {$className}";
                }
            }
        }

        return $errors;
    }

    /**
     * @param string $oldSchema
     * @param string $newSchema
     * @return array
     */
    public function compareSchemas(string $oldSchema, string $newSchema): array
    {
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => [],
        ];

        if (!file_exists($oldSchema) || !file_exists($newSchema)) {
            return $changes;
        }

        $oldDom = new DOMDocument();
        $oldDom->load($oldSchema);

        $newDom = new DOMDocument();
        $newDom->load($newSchema);

        $oldXpath = new DOMXPath($oldDom);
        $newXpath = new DOMXPath($newDom);

        $oldClasses = $this->extractClasses($oldXpath);
        $newClasses = $this->extractClasses($newXpath);

        foreach ($newClasses as $class => $object) {
            if (!isset($oldClasses[$class])) {
                $changes['added'][] = $class;
            } elseif ($oldClasses[$class]->C14N() !== $object->C14N()) {
                $changes['modified'][] = $class;
            }
        }

        foreach ($oldClasses as $class => $object) {
            if (!isset($newClasses[$class])) {
                $changes['removed'][] = $class;
            }
        }

        return $changes;
    }

    /**
     * @param DOMXPath $xpath
     * @return array
     */
    private function extractClasses(DOMXPath $xpath): array
    {
        $classes = [];
        $objects = $xpath->query('//object');

        foreach ($objects as $object) {
            $className = $object->getAttribute('class');
            if ($className) {
                $classes[$className] = $object;
            }
        }

        return $classes;
    }
}
