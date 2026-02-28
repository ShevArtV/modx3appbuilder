#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ComponentBuilder\ConfigManager;

class SettingsExtractor
{
    private array $config;
    private array $settings = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function extractFromDirectory(string $directory, string $packagePrefix): void
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
                $this->extractFromFile($file->getPathname(), $packagePrefix);
            }
        }
    }

    private function extractFromFile(string $filePath, string $packagePrefix): void
    {
        $content = file_get_contents($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        preg_match_all('/\$modx->getOption\([\'"](' . $packagePrefix . '_[^\'"]+)[\'"]/', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $key) {
                $lineNumber = $this->findLineNumber($matches[0][$index], $lines);
                
                $this->settings[$key] = [
                    'file' => basename($filePath),
                    'line' => $lineNumber,
                    'type' => $this->inferSettingType($content, $matches[0][$index]),
                    'default' => $this->extractDefaultValue($content, $matches[0][$index]),
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

    private function inferSettingType(string $content, string $match): string
    {
        $pos = strpos($content, $match);
        $context = substr($content, $pos, 200);
        
        if (preg_match('/(if|foreach|while|switch)/', $context)) {
            return 'boolean';
        }
        
        if (preg_match('/(intval|floatval|number_format)/', $context)) {
            return 'number';
        }
        
        if (preg_match('/(array|explode|json_decode)/', $context)) {
            return 'array';
        }
        
        return 'string';
    }

    private function extractDefaultValue(string $content, string $match): string
    {
        $pos = strpos($content, $match);
        $context = substr($content, $pos, 100);
        
        if (preg_match('/\$modx->getOption\([^,]+,\s*[^,]+,\s*[\'"]([^\'"]*)[\'"]/', $context, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    public function generateSettingsFile(string $packageName, string $packagePrefix, string $outputPath): void
    {
        $settingsData = [];
        
        foreach ($this->settings as $key => $info) {
            $settingsData[$key] = [
                'key' => $key,
                'value' => $info['default'],
                'xtype' => $this->getXType($info['type']),
                'namespace' => $packageName,
                'area' => $this->categorizeSetting($key),
                'editedon' => time(),
            ];
        }
        
        ksort($settingsData);
        
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Settings Russian Lexicon Entries for {$packageName}\n";
        $content .= " *\n";
        $content .= " * @package {$packageName}\n";
        $content .= " * @subpackage lexicon\n";
        $content .= " */\n";
        $content .= "\n";
        
        $content .= "\$_lang = [];\n\n";
        
        foreach ($this->settings as $key => $info) {
            $settingKey = str_replace($packagePrefix . '_', '', $key);
            $content .= "\$_lang['setting_{$settingKey}'] = '" . ucfirst($settingKey) . "';\n";
            
            // Добавляем описание если есть значение по умолчанию
            if (!empty($info['default'])) {
                $content .= "\$_lang['setting_{$settingKey}_desc'] = '" . addslashes($info['default']) . "';\n";
            } else {
                $content .= "\$_lang['setting_{$settingKey}_desc'] = 'Описание настройки " . ucfirst($settingKey) . "';\n";
            }
            $content .= "\n";
        }
        
        $content .= "return \$_lang;\n";
        
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Генерируем файл настроек
        $settingsContent = "<?php\n\n";
        $settingsContent .= "// Auto-generated settings for {$packageName}\n";
        $settingsContent .= "// Generated on: " . date('Y-m-d H:i:s') . "\n";
        $settingsContent .= "// Use short package name for settings keys (e.g., 'example' instead of 'examplepackage')\n\n";
        $settingsContent .= "return " . var_export($settingsData, true) . ";\n";
        
        file_put_contents($outputPath, $settingsContent);
        
        // Генерируем файл лексиконов
        $lexiconPath = str_replace('elements/settings.php', 'lexicon/ru/setting.inc.php', $outputPath);
        $lexiconDir = dirname($lexiconPath);
        if (!is_dir($lexiconDir)) {
            mkdir($lexiconDir, 0755, true);
        }
        
        file_put_contents($lexiconPath, $content);
        
        echo "Settings file generated: {$outputPath}\n";
        echo "Lexicon file generated: {$lexiconPath}\n";
        echo "Found " . count($this->settings) . " settings\n";
        
        // Рекомендация по использованию краткого имени
        $this->showShortNameRecommendation($packagePrefix);
    }

    private function getXType(string $type): string
    {
        switch ($type) {
            case 'boolean':
                return 'modx-combo-boolean';
            case 'number':
                return 'numberfield';
            case 'array':
                return 'textarea';
            default:
                return 'textfield';
        }
    }

    private function categorizeSetting(string $key): string
    {
        if (strpos($key, '_path') !== false || strpos($key, '_dir') !== false) {
            return 'paths';
        }
        
        if (strpos($key, '_email') !== false) {
            return 'email';
        }
        
        if (strpos($key, '_cache') !== false || strpos($key, '_debug') !== false) {
            return 'system';
        }
        
        return 'general';
    }

    public function showReport(): void
    {
        echo "\nSettings Extraction Report:\n";
        echo "==========================\n\n";
        
        $shortKeys = 0;
        $longKeys = 0;
        
        foreach ($this->settings as $key => $info) {
            // Проверяем длину префикса (короткие обычно до 10 символов)
            $prefixParts = explode('_', $key);
            $prefix = $prefixParts[0] ?? '';
            
            if (strlen($prefix) <= 10) {
                echo "✅ Setting: {$key}\n";
                $shortKeys++;
            } else {
                echo "⚠️  Setting: {$key} (consider using shorter package name)\n";
                $longKeys++;
            }
            
            echo "  File: {$info['file']}\n";
            echo "  Line: {$info['line']}\n";
            echo "  Type: {$info['type']}\n";
            echo "  Default: {$info['default']}\n\n";
        }
        
        echo "Summary:\n";
        echo "Short keys: {$shortKeys}\n";
        echo "Long keys: {$longKeys}\n";
        echo "Total: " . ($shortKeys + $longKeys) . "\n\n";
        
        echo "Generated files:\n";
        echo "- Settings: elements/settings.php\n";
        echo "- Lexicon: lexicon/ru/setting.inc.php\n\n";
        
        if ($longKeys > 0) {
            echo "💡 Consider using 'name_short' in package config for shorter setting keys\n";
        }
        
        echo "📝 Lexicon keys format:\n";
        echo "   setting_{key} - Название настройки\n";
        echo "   setting_{key}_desc - Описание настройки\n\n";
    }
    
    private function showShortNameRecommendation(string $packagePrefix): void
    {
        echo "\n💡 Recommendation for settings keys:\n";
        echo "   Consider using short package name for better readability:\n";
        echo "   Instead of: {$packagePrefix}_api_timeout\n";
        echo "   Use: example_api_timeout\n\n";
        echo "   Add 'name_short' => 'example' to package config to customize.\n\n";
    }
}

if ($argv[1] ?? null) {
    $packageName = $argv[1];
    $packagePrefix = $argv[2] ?? $packageName . '_';
    $directory = $argv[3] ?? 'core/components/' . $packageName . '/src/';
    $outputPath = $argv[4] ?? 'core/components/' . $packageName . '/elements/settings.php';
    
    $configManager = new ConfigManager(__DIR__ . '/../config/default.config.php');
    $config = $configManager->getDefaultConfig();
    
    $extractor = new SettingsExtractor($config);
    $extractor->extractFromDirectory($directory, $packagePrefix);
    $extractor->generateSettingsFile($packageName, $packagePrefix, $outputPath);
    $extractor->showReport();
} else {
    echo "Usage: php extract_settings.php <package_name> [package_prefix] [directory] [output_path]\n";
}
