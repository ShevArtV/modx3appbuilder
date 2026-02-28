<?php

if (php_sapi_name() === 'cli') {
    exit('This script must be run via web server');
}

$packageName = $_GET['package'] ?? '';
if (empty($packageName) || !preg_match('/^[a-z][a-z0-9_]*$/', $packageName)) {
    header('Content-Type: text/plain; charset=utf-8');
    exit('Error: invalid or missing package name. Usage: ?package=testpkg');
}

require_once __DIR__ . '/vendor/autoload.php';

use ComponentBuilder\ConfigManager;
use ComponentBuilder\ComponentBuilder;

header('Content-Type: text/plain; charset=utf-8');

$configManager = new ConfigManager();
$packageConfig = $configManager->loadPackageConfig($packageName);

if (!$packageConfig) {
    exit("Error: Package config not found: {$packageName}");
}

$buildOptions = [
    'install' => isset($_GET['install']),
    'download' => isset($_GET['download']),
    'encrypt' => isset($_GET['encrypt']),
];

try {
    $builder = new ComponentBuilder($packageConfig);
    $success = $builder->build($packageName, $buildOptions);

    if ($success) {
        echo "SUCCESS: Package {$packageName} built successfully\n";
    } else {
        echo "FAILED: Could not build package {$packageName}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
