#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ComponentBuilder\CLI;
use ComponentBuilder\ConfigManager;
use ComponentBuilder\ComponentBuilder;
use ComponentBuilder\SchemaManager;
use ComponentBuilder\LexiconExtractor;
use ComponentBuilder\SettingsExtractor;
use ComponentBuilder\ElementManager;

$cli = new CLI($argv);

$command = $cli->getCommand();

if (!$command || $command === 'help') {
    $cli->showHelp();
    exit(0);
}

$configManager = new ConfigManager();

try {
    switch ($command) {
        case 'build':
            $packageName = $cli->getPackageName();
            if (!$packageName) {
                $cli->showError('Package name is required for build command');
            }

            $packageConfig = $configManager->loadPackageConfig($packageName);
            if (!$packageConfig) {
                $cli->showError("Package config not found: {$packageName}");
            }

            $buildOptions = [
                'install' => $cli->hasOption('install'),
                'download' => $cli->hasOption('download'),
                'encrypt' => $cli->hasOption('encrypt'),
            ];

            $builder = new ComponentBuilder($packageConfig);
            $success = $builder->build($packageName, $buildOptions);

            if ($success) {
                echo "SUCCESS: Package {$packageName} built successfully\n";
            } else {
                $cli->showError("Failed to build package {$packageName}");
            }
            break;

        case 'create':
            $userConfig = $cli->loadUserConfig();

            if ($cli->hasOption('interactive')) {
                $cli->showInteractiveHeader();

                $packageName = $cli->getPackageName();
                if (!$packageName) {
                    $packageName = $cli->promptRequired('Package name (lowercase, no spaces)');
                }

                $author = $cli->prompt('Author name', $userConfig['author'] ?? 'Your Name');
                $email = $cli->prompt('Author email', $userConfig['email'] ?? 'your-email@example.com');
                $gitlogin = $cli->prompt('Git login (GitHub/GitLab username)', $userConfig['gitlogin'] ?? 'your-username');
                $shortName = $cli->prompt('Short component name (for lexicons and settings)', substr($packageName, 0, 3));
                $phpVersion = $cli->prompt('Minimum PHP version', $userConfig['phpVersion'] ?? '8.1');
                $repository = $cli->prompt('Repository URL', $userConfig['repository'] ?? '');

                $generateElements = $cli->promptYesNo('Generate elements files?', $userConfig['generateElements'] ?? true);

                $options = [
                    'generateElements' => $generateElements,
                    'author' => $author,
                    'email' => $email,
                    'phpVersion' => $phpVersion,
                    'gitlogin' => $gitlogin,
                    'repository' => $repository,
                    'shortName' => $shortName,
                ];

                $cli->saveUserConfig($options);
            } else {
                $packageName = $cli->getPackageName();
                if (!$packageName) {
                    $cli->showError('Package name is required for create command');
                }

                $options = [
                    'generateElements' => $cli->hasOption('elements'),
                    'author' => $cli->getOption('author', $userConfig['author'] ?? 'Your Name'),
                    'email' => $cli->getOption('email', $userConfig['email'] ?? 'your-email@example.com'),
                    'phpVersion' => $cli->getOption('php-version', $userConfig['phpVersion'] ?? '8.1'),
                    'gitlogin' => $cli->getOption('gitlogin', $userConfig['gitlogin'] ?? 'your-username'),
                    'repository' => $cli->getOption('repository', $userConfig['repository'] ?? 'https://github.com/'),
                    'shortName' => $cli->getOption('short-name', substr($packageName, 0, 3)),
                ];
            }

            if ($configManager->createPackageTemplate($packageName, $options)) {
                echo "SUCCESS: Package template created: {$packageName}\n";
            } else {
                $cli->showError("Failed to create package template: {$packageName}");
            }
            break;

        case 'schema':
            $packageName = $cli->getPackageName();
            if (!$packageName) {
                $cli->showError('Package name is required for schema command');
            }

            $packageConfig = $configManager->loadPackageConfig($packageName);
            if (!$packageConfig) {
                $cli->showError("Package config not found: {$packageName}");
            }

            $builder = new ComponentBuilder($packageConfig);
            $modx = $builder->getModx();

            $root = dirname(MODX_CORE_PATH) . '/';
            $corePath = $root . ($packageConfig['paths']['core'] ?? 'core/components/' . $packageName . '/');
            $schemaFile = $corePath . ($packageConfig['schema']['file'] ?? 'schema/' . $packageName . '.mysql.schema.xml');

            if (!file_exists($schemaFile)) {
                $cli->showError("Schema file not found: {$schemaFile}");
                break;
            }

            $schemaContent = file_get_contents($schemaFile);
            if (empty(trim($schemaContent)) || !str_contains($schemaContent, '<object')) {
                $cli->showError("Schema file is empty or invalid: {$schemaFile}");
                break;
            }

            $schemaManager = new SchemaManager($modx, $packageConfig);

            if ($cli->hasOption('validate')) {
                $errors = $schemaManager->validateSchema($schemaFile);
                if (empty($errors)) {
                    echo "Schema is valid\n";
                } else {
                    echo "Schema errors:\n";
                    foreach ($errors as $error) {
                        echo "  - {$error}\n";
                    }
                }
                break;
            }

            $outputDir = $corePath . 'src/';

            if ($packageConfig['schema']['auto_generate_classes'] ?? true) {
                $schemaManager->generateClasses($schemaFile, $outputDir);
                echo "Classes generated for {$packageName}\n";
            }

            if ($packageConfig['schema']['update_tables'] ?? false) {
                $schemaManager->updateDatabase($schemaFile, $packageConfig['name_lower']);
                echo "Database updated for {$packageName}\n";
            }
            break;

        case 'elements':
            $packageName = $cli->getPackageName();
            if (!$packageName) {
                $cli->showError('Package name is required for elements command');
            }

            $packageConfig = $configManager->loadPackageConfig($packageName);
            if (!$packageConfig) {
                $cli->showError("Package config not found: {$packageName}");
            }

            $builder = new ComponentBuilder($packageConfig);
            $modx = $builder->getModx();

            $root = dirname(MODX_CORE_PATH) . '/';
            $elementsPath = dirname(__DIR__) . '/package_builder/packages/' . $packageName . '/elements/';

            if (!is_dir($elementsPath)) {
                $corePath = $root . ($packageConfig['paths']['core'] ?? 'core/components/' . $packageName . '/');
                $elementsPath = $corePath . 'elements/';
            }

            if (is_dir($elementsPath)) {
                $elementManager = new ElementManager($modx, $packageConfig);
                $elementManager->processElements($elementsPath);
                echo "Elements processed for {$packageName}\n";
            } else {
                $cli->showError("Elements directory not found: {$elementsPath}");
            }
            break;

        case 'extract-lexicons':
            $packageName = $cli->getPackageName();
            if (!$packageName) {
                $cli->showError('Package name is required for extract-lexicons command');
            }

            $packageConfig = $configManager->loadPackageConfig($packageName);
            if (!$packageConfig) {
                $cli->showError("Package config not found: {$packageName}");
            }

            $root = dirname(__DIR__) . '/';
            $corePath = $root . ($packageConfig['paths']['core'] ?? 'core/components/' . $packageName . '/');
            $directory = $corePath . 'src/';
            $outputPath = $corePath . 'lexicon/en/default.inc.php';

            $extractor = new LexiconExtractor();
            $extractor->extractFromDirectory($directory);

            $lexiconPackageName = $packageConfig['name_short'] ?? $packageConfig['name_lower'];
            $extractor->generateLexiconFile($lexiconPackageName, $outputPath);
            break;

        case 'extract-settings':
            $packageName = $cli->getPackageName();
            if (!$packageName) {
                $cli->showError('Package name is required for extract-settings command');
            }

            $packageConfig = $configManager->loadPackageConfig($packageName);
            if (!$packageConfig) {
                $cli->showError("Package config not found: {$packageName}");
            }

            $root = dirname(__DIR__) . '/';
            $corePath = $root . ($packageConfig['paths']['core'] ?? 'core/components/' . $packageName . '/');
            $directory = $corePath . 'src/';
            $outputPath = $corePath . 'elements/settings.php';

            $settingsPrefix = $packageConfig['name_short'] ?? $packageConfig['name_lower'];

            $extractor = new SettingsExtractor();
            $extractor->extractFromDirectory($directory, $settingsPrefix . '_');
            $extractor->generateSettingsFile($packageName, $settingsPrefix . '_', $outputPath);
            break;

        default:
            $cli->showError("Unknown command: {$command}");
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($cli->hasOption('verbose')) {
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}
