<?php

namespace ComponentBuilder;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class IgnoreFilter
{
    private const IGNORE_FILE = '.packignore';

    private const DEFAULT_PATTERNS = [
        '.git/',
        '.gitignore',
        '.gitattributes',
        '.idea/',
        '.vscode/',
        '.DS_Store',
        'Thumbs.db',
    ];

    /** @var array<int, array{pattern: string, negated: bool, dirOnly: bool, anchored: bool}> */
    private array $rules = [];

    public function __construct(string ...$ignoreFilePaths)
    {
        $this->addPatterns(self::DEFAULT_PATTERNS);

        foreach ($ignoreFilePaths as $path) {
            $this->loadFile($path);
        }
    }

    public function loadFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        $this->addPatterns($lines);
    }

    /**
     * @param string[] $patterns
     */
    public function addPatterns(array $patterns): void
    {
        foreach ($patterns as $line) {
            $rule = $this->parseLine($line);
            if ($rule !== null) {
                $this->rules[] = $rule;
            }
        }
    }

    /**
     * @return array{pattern: string, negated: bool, dirOnly: bool, anchored: bool}|null
     */
    private function parseLine(string $line): ?array
    {
        $line = rtrim($line);

        if ($line === '' || $line[0] === '#') {
            return null;
        }

        $negated = false;
        if ($line[0] === '!') {
            $negated = true;
            $line = substr($line, 1);
        }

        $line = ltrim($line, '\\');

        $dirOnly = false;
        if (str_ends_with($line, '/')) {
            $dirOnly = true;
            $line = rtrim($line, '/');
        }

        $anchored = str_contains($line, '/');
        $line = ltrim($line, '/');

        return [
            'pattern' => $line,
            'negated' => $negated,
            'dirOnly' => $dirOnly,
            'anchored' => $anchored,
        ];
    }

    public function isIgnored(string $relativePath, bool $isDir): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        $ignored = false;

        foreach ($this->rules as $rule) {
            if ($rule['dirOnly'] && !$isDir) {
                continue;
            }

            if ($this->matchRule($rule, $relativePath)) {
                $ignored = !$rule['negated'];
            }
        }

        return $ignored;
    }

    private function matchRule(array $rule, string $path): bool
    {
        $pattern = $rule['pattern'];

        if ($rule['anchored']) {
            return $this->matchGlob($pattern, $path);
        }

        if ($this->matchGlob($pattern, $path)) {
            return true;
        }

        $parts = explode('/', $path);
        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            $subPath = implode('/', array_slice($parts, $i));
            if ($this->matchGlob($pattern, $subPath)) {
                return true;
            }
        }

        return false;
    }

    private function matchGlob(string $pattern, string $path): bool
    {
        $regex = $this->globToRegex($pattern);

        return (bool) preg_match($regex, $path);
    }

    private function globToRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);
        $i = 0;

        while ($i < $length) {
            $char = $pattern[$i];

            switch ($char) {
                case '*':
                    if ($i + 1 < $length && $pattern[$i + 1] === '*') {
                        if ($i + 2 < $length && $pattern[$i + 2] === '/') {
                            $regex .= '(.+/)?';
                            $i += 3;
                        } else {
                            $regex .= '.*';
                            $i += 2;
                        }
                    } else {
                        $regex .= '[^/]*';
                        $i++;
                    }
                    break;

                case '?':
                    $regex .= '[^/]';
                    $i++;
                    break;

                case '[':
                    $j = $i + 1;
                    if ($j < $length && $pattern[$j] === '!') {
                        $j++;
                    }
                    while ($j < $length && $pattern[$j] !== ']') {
                        $j++;
                    }
                    if ($j < $length) {
                        $class = substr($pattern, $i + 1, $j - $i - 1);
                        if (str_starts_with($class, '!')) {
                            $class = '^' . substr($class, 1);
                        }
                        $regex .= '[' . $class . ']';
                        $i = $j + 1;
                    } else {
                        $regex .= '\\[';
                        $i++;
                    }
                    break;

                case '.':
                case '(':
                case ')':
                case '{':
                case '}':
                case '+':
                case '|':
                case '^':
                case '$':
                case '\\':
                    $regex .= '\\' . $char;
                    $i++;
                    break;

                default:
                    $regex .= $char;
                    $i++;
                    break;
            }
        }

        return '#^' . $regex . '$#';
    }

    public function copyFiltered(string $source, string $destination): int
    {
        $source = rtrim($source, '/\\');
        $destination = rtrim($destination, '/\\');

        if (!is_dir($source)) {
            return 0;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $sourceLen = strlen($source) + 1;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), $sourceLen);
            $isDir = $file->isDir();

            if ($this->isIgnored($relativePath, $isDir)) {
                continue;
            }

            if ($this->isParentIgnored($relativePath, $isDir)) {
                continue;
            }

            $target = $destination . '/' . $relativePath;

            if ($isDir) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($file->getPathname(), $target);
                $copied++;
            }
        }

        return $copied;
    }

    private function isParentIgnored(string $relativePath, bool $isDir): bool
    {
        $parts = explode('/', str_replace('\\', '/', $relativePath));

        if (count($parts) <= 1) {
            return false;
        }

        $path = '';
        $limit = count($parts) - ($isDir ? 0 : 1);

        for ($i = 0; $i < $limit; $i++) {
            $path = $path === '' ? $parts[$i] : $path . '/' . $parts[$i];

            if ($i < count($parts) - 1 && $this->isIgnored($path, true)) {
                return true;
            }
        }

        return false;
    }

    public static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
