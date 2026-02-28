#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ComponentBuilder\ConfigManager;

class LexiconExtractor
{
    private array $config;
    private array $lexiconKeys = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function extractFromDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            echo "Directory not found: {$directory}\n";
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->extractFromFile($file->getPathname());
            }
        }
    }

    private function extractFromFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        // Ищем только ключи с нижним подчеркиванием как разделителем
        preg_match_all('/\$modx->lexicon\([\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $key) {
                // Проверяем что ключ содержит только нижнее подчеркивание как разделитель
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key) || strpos($key, '-') !== false) {
                    continue; // Пропускаем ключи с дефисами или другими разделителями
                }
                
                $lineNumber = $this->findLineNumber($matches[0][$index], $lines);
                $className = $this->extractClassName($content);
                $methodName = $this->extractMethodName($content, $lineNumber);
                
                $this->lexiconKeys[$key] = [
                    'file' => basename($filePath),
                    'class' => $className,
                    'method' => $methodName,
                    'line' => $lineNumber,
                ];
            }
        }
    }

    private function findLineNumber(string $match, array $lines): int
    {
        foreach ($lines as $index => $line) {
            if (strpos($line, $match) !== false) {
                return $index + 1;
            }
        }
        return 0;
    }

    private function extractClassName(string $content): string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    private function extractMethodName(string $content, int $lineNumber): string
    {
        $lines = explode("\n", $content);
        $currentLine = $lineNumber - 1;
        
        for ($i = $currentLine; $i >= 0; $i--) {
            if (preg_match('/(public|private|protected)\s+function\s+(\w+)/', $lines[$i], $matches)) {
                return $matches[2];
            }
        }
        
        return 'unknown';
    }

    public function generateLexiconFile(string $packageName, string $outputPath): void
    {
        $lexiconData = [];
        
        foreach ($this->lexiconKeys as $key => $info) {
            $lexiconData[$key] = $key;
        }
        
        ksort($lexiconData);
        
        $content = "<?php\n\n";
        $content .= "// Auto-generated lexicon keys for {$packageName}\n";
        $content .= "// Generated on: " . date('Y-m-d H:i:s') . "\n";
        $content .= "// Use short package name for lexicon keys (e.g., 'example' instead of 'examplepackage')\n\n";
        $content .= "return " . var_export($lexiconData, true) . ";\n";
        
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        file_put_contents($outputPath, $content);
        echo "Lexicon file generated: {$outputPath}\n";
        echo "Found " . count($this->lexiconKeys) . " lexicon keys\n";
        
        // Рекомендация по использованию краткого имени
        $this->showShortNameRecommendation($packageName);
    }

    public function showReport(): void
    {
        echo "\nLexicon Extraction Report:\n";
        echo "========================\n\n";
        
        $validKeys = 0;
        $invalidKeys = 0;
        
        foreach ($this->lexiconKeys as $key => $info) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $key) && strpos($key, '-') === false) {
                echo "✅ Key: {$key}\n";
                $validKeys++;
            } else {
                echo "❌ Key: {$key} (invalid format - use only underscores)\n";
                $invalidKeys++;
            }
            echo "  File: {$info['file']}\n";
            echo "  Class: {$info['class']}\n";
            echo "  Method: {$info['method']}\n";
            echo "  Line: {$info['line']}\n\n";
        }
        
        echo "Summary:\n";
        echo "Valid keys: {$validKeys}\n";
        echo "Invalid keys: {$invalidKeys}\n";
        echo "Total: " . ($validKeys + $invalidKeys) . "\n\n";
        
        if ($invalidKeys > 0) {
            echo "⚠️  Please fix invalid keys to use only underscores as separators\n";
        }
    }
    
    private function showShortNameRecommendation(string $packageName): void
    {
        echo "\n💡 Recommendation for lexicon keys:\n";
        echo "   Consider using short package name for better readability:\n";
        echo "   Instead of: {$packageName}_user_created\n";
        echo "   Use: example_user_created\n\n";
        echo "   Add 'name_short' => 'example' to package config to customize.\n\n";
    }
}

if ($argv[1] ?? null) {
    $packageName = $argv[1];
    $directory = $argv[2] ?? 'core/components/' . $packageName . '/src/';
    $outputPath = $argv[3] ?? 'core/components/' . $packageName . '/lexicon/en/default.inc.php';
    
    $configManager = new ConfigManager(__DIR__ . '/../config/default.config.php');
    $config = $configManager->getDefaultConfig();
    
    $extractor = new LexiconExtractor($config);
    $extractor->extractFromDirectory($directory);
    $extractor->generateLexiconFile($packageName, $outputPath);
    $extractor->showReport();
} else {
    echo "Usage: php extract_lexicons.php <package_name> [directory] [output_path]\n";
}
