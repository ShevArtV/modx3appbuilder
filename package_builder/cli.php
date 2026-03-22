#!/usr/bin/env php
<?php

$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    echo "ERROR: Could not find autoload.php. Run 'composer install'.\n";
    exit(1);
}

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
            if (!$cli->localConfigExists()) {
                $globalConfig = $cli->loadUserConfig();
                $cli->saveLocalConfig($globalConfig);
                echo "Created mxbuilder.json from global config\n";
            }

            $localConfig = $cli->loadLocalConfig();

            if ($cli->hasOption('interactive')) {
                $cli->showInteractiveHeader();

                $packageName = $cli->getPackageName();
                if (!$packageName) {
                    $packageName = $cli->promptRequired('Package name (lowercase, letters, digits, hyphens)');
                }
                if (!$cli->validatePackageName($packageName)) {
                    $cli->showError('Invalid package name. Use only lowercase letters, digits and hyphens. Must start with a letter.');
                }

                $shortName = $cli->prompt('Short component name (for lexicons and settings)', substr($packageName, 0, 3));

                $options = [
                    'generateElements' => $localConfig['generateElements'] ?? true,
                    'phpCsFixer' => $localConfig['phpCsFixer'] ?? false,
                    'eslint' => $localConfig['eslint'] ?? false,
                    'template' => $localConfig['template'] ?? '',
                    'author' => $localConfig['author'] ?? 'Your Name',
                    'email' => $localConfig['email'] ?? 'your-email@example.com',
                    'phpVersion' => $localConfig['phpVersion'] ?? '8.1',
                    'gitlogin' => $localConfig['gitlogin'] ?? 'your-username',
                    'repository' => $localConfig['repository'] ?? 'https://github.com/',
                    'shortName' => $shortName,
                ];
            } else {
                $packageName = $cli->getPackageName();
                if (!$packageName) {
                    $cli->showError('Package name is required for create command');
                }
                if (!$cli->validatePackageName($packageName)) {
                    $cli->showError('Invalid package name. Use only lowercase letters, digits and hyphens. Must start with a letter.');
                }

                $options = [
                    'generateElements' => $cli->hasOption('elements') || ($localConfig['generateElements'] ?? true),
                    'phpCsFixer' => $cli->hasOption('php-cs-fixer') || ($localConfig['phpCsFixer'] ?? false),
                    'eslint' => $cli->hasOption('eslint') || ($localConfig['eslint'] ?? false),
                    'template' => $cli->getOption('template', $localConfig['template'] ?? ''),
                    'author' => $cli->getOption('author', $localConfig['author'] ?? 'Your Name'),
                    'email' => $cli->getOption('email', $localConfig['email'] ?? 'your-email@example.com'),
                    'phpVersion' => $cli->getOption('php-version', $localConfig['phpVersion'] ?? '8.1'),
                    'gitlogin' => $cli->getOption('gitlogin', $localConfig['gitlogin'] ?? 'your-username'),
                    'repository' => $cli->getOption('repository', $localConfig['repository'] ?? 'https://github.com/'),
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

        case 'config':
            echo "\n=== Package Builder Global Config ===\n";
            echo "These settings will be used as defaults for all projects.\n\n";

            $globalConfig = $cli->loadUserConfig();

            $globalConfig['author'] = $cli->prompt('Author name', $globalConfig['author'] ?? 'Your Name');
            $globalConfig['email'] = $cli->prompt('Author email', $globalConfig['email'] ?? 'your-email@example.com');
            $globalConfig['gitlogin'] = $cli->prompt('Git login (GitHub/GitLab username)', $globalConfig['gitlogin'] ?? 'your-username');
            $globalConfig['phpVersion'] = $cli->prompt('Minimum PHP version', $globalConfig['phpVersion'] ?? '8.1');
            $globalConfig['repository'] = $cli->prompt('Repository URL', $globalConfig['repository'] ?? '');
            $globalConfig['template'] = $cli->prompt('Default templates path (leave empty for built-in)', $globalConfig['template'] ?? '');
            $globalConfig['generateElements'] = $cli->promptYesNo('Generate elements files by default?', $globalConfig['generateElements'] ?? true);
            $globalConfig['phpCsFixer'] = $cli->promptYesNo('Add PHP CS Fixer by default?', $globalConfig['phpCsFixer'] ?? false);
            $globalConfig['eslint'] = $cli->promptYesNo('Add ESLint by default?', $globalConfig['eslint'] ?? false);

            $cli->saveUserConfig($globalConfig);
            echo "\nSUCCESS: Global config saved\n";
            break;

        case 'init':
            if ($cli->localConfigExists()) {
                echo "mxbuilder.json already exists in this directory.\n";
                if (!$cli->promptYesNo('Overwrite?', false)) {
                    echo "Aborted.\n";
                    break;
                }
            }

            echo "\n=== Package Builder Project Init ===\n";
            echo "Configure settings for this project. Press Enter to use defaults.\n\n";

            $globalConfig = $cli->loadUserConfig();

            $localConfig = [];
            $localConfig['author'] = $cli->prompt('Author name', $globalConfig['author'] ?? 'Your Name');
            $localConfig['email'] = $cli->prompt('Author email', $globalConfig['email'] ?? 'your-email@example.com');
            $localConfig['gitlogin'] = $cli->prompt('Git login', $globalConfig['gitlogin'] ?? 'your-username');
            $localConfig['phpVersion'] = $cli->prompt('Minimum PHP version', $globalConfig['phpVersion'] ?? '8.1');
            $localConfig['repository'] = $cli->prompt('Repository URL', $globalConfig['repository'] ?? '');
            $localConfig['template'] = $cli->prompt('Templates path (leave empty for default)', $globalConfig['template'] ?? '');
            $localConfig['generateElements'] = $cli->promptYesNo('Generate elements files?', $globalConfig['generateElements'] ?? true);
            $localConfig['phpCsFixer'] = $cli->promptYesNo('Add PHP CS Fixer?', $globalConfig['phpCsFixer'] ?? false);
            $localConfig['eslint'] = $cli->promptYesNo('Add ESLint?', $globalConfig['eslint'] ?? false);

            $cli->saveLocalConfig($localConfig);
            echo "\nSUCCESS: mxbuilder.json created\n";
            break;

        case 'templates':
            $subCommand = $cli->getPackageName();

            if ($subCommand === 'path') {
                echo $configManager->getTemplatesPath() . "\n";
            } elseif ($subCommand === 'copy') {
                $destination = $cli->getArg(2);
                if (!$destination) {
                    $cli->showError('Destination path is required: mxbuilder templates copy <path>');
                }

                if ($configManager->copyTemplates($destination)) {
                    echo "SUCCESS: Templates copied to {$destination}\n";
                } else {
                    $cli->showError("Failed to copy templates to {$destination}");
                }
            } else {
                echo "Usage:\n";
                echo "  mxbuilder templates path              Show default templates path\n";
                echo "  mxbuilder templates copy <path>       Copy default templates to <path>\n";
            }
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
