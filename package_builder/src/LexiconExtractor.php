<?php

namespace ComponentBuilder;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LexiconExtractor
{
    private array $lexiconKeys = [];

    /**
     * @param string $directory
     * @return void
     */
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

    /**
     * @param string $filePath
     * @return void
     */
    private function extractFromFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        preg_match_all('/\$modx->lexicon\([\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        foreach ($matches[1] as $index => $key) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                continue;
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
     * @return string
     */
    private function extractClassName(string $content): string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * @param string $content
     * @param int $lineNumber
     * @return string
     */
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

    /**
     * @param string $packageName
     * @param string $outputPath
     * @return void
     */
    public function generateLexiconFile(string $packageName, string $outputPath): void
    {
        $lexiconData = [];

        foreach ($this->lexiconKeys as $key => $info) {
            $lexiconData[$key] = $key;
        }

        ksort($lexiconData);

        $content = "<?php\n\n";
        foreach ($lexiconData as $key => $value) {
            $content .= "\$_lang['{$key}'] = '{$value}';\n";
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $content);
        echo "Lexicon file generated: {$outputPath}\n";
        echo "Found " . count($this->lexiconKeys) . " lexicon keys\n";
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return $this->lexiconKeys;
    }
}
