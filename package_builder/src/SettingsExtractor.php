<?php

namespace ComponentBuilder;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SettingsExtractor
{
    private array $settings = [];

    /**
     * @param string $directory
     * @param string $packagePrefix
     * @return void
     */
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

    /**
     * @param string $filePath
     * @param string $packagePrefix
     * @return void
     */
    private function extractFromFile(string $filePath, string $packagePrefix): void
    {
        $content = file_get_contents($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        $pattern = '/\$modx->getOption\([\'"](' . preg_quote($packagePrefix, '/') . '[^\'"]+)[\'"]/';;

        preg_match_all($pattern, $content, $matches);

        if (empty($matches[1])) {
            return;
        }

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

    /**
     * @param string $match
     * @param array $lines
     * @return int
     */
    private function findLineNumber(string $match, array $lines): int
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, $match)) {
                return $index + 1;
            }
        }
        return 0;
    }

    /**
     * @param string $content
     * @param string $match
     * @return string
     */
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

    /**
     * @param string $content
     * @param string $match
     * @return string
     */
    private function extractDefaultValue(string $content, string $match): string
    {
        $pos = strpos($content, $match);
        $context = substr($content, $pos, 100);

        if (preg_match('/\$modx->getOption\([^,]+,\s*[^,]+,\s*[\'"]([^\'"]*)[\'"]/', $context, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param string $packageName
     * @param string $packagePrefix
     * @param string $outputPath
     * @return void
     */
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
            ];
        }

        ksort($settingsData);

        $content = "<?php\n\nreturn " . var_export($settingsData, true) . ";\n";

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $content);

        $lexiconPath = str_replace('elements/settings.php', 'lexicon/ru/setting.inc.php', $outputPath);
        $this->generateSettingsLexicon($packagePrefix, $lexiconPath);

        echo "Settings file generated: {$outputPath}\n";
        echo "Lexicon file generated: {$lexiconPath}\n";
        echo "Found " . count($this->settings) . " settings\n";
    }

    /**
     * @param string $packagePrefix
     * @param string $lexiconPath
     * @return void
     */
    private function generateSettingsLexicon(string $packagePrefix, string $lexiconPath): void
    {
        $content = "<?php\n\n";

        foreach ($this->settings as $key => $info) {
            $settingKey = str_replace($packagePrefix, '', $key);
            $content .= "\$_lang['setting_{$settingKey}'] = '" . ucfirst($settingKey) . "';\n";
            $content .= "\$_lang['setting_{$settingKey}_desc'] = '';\n";
        }

        $lexiconDir = dirname($lexiconPath);
        if (!is_dir($lexiconDir)) {
            mkdir($lexiconDir, 0755, true);
        }

        file_put_contents($lexiconPath, $content);
    }

    /**
     * @param string $type
     * @return string
     */
    private function getXType(string $type): string
    {
        return match ($type) {
            'boolean' => 'modx-combo-boolean',
            'number' => 'numberfield',
            'array' => 'textarea',
            default => 'textfield',
        };
    }

    /**
     * @param string $key
     * @return string
     */
    private function categorizeSetting(string $key): string
    {
        if (str_contains($key, '_path') || str_contains($key, '_dir')) {
            return 'paths';
        }

        if (str_contains($key, '_email')) {
            return 'email';
        }

        if (str_contains($key, '_cache') || str_contains($key, '_debug')) {
            return 'system';
        }

        return 'general';
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }
}
