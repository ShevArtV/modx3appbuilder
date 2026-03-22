<?php

namespace ComponentBuilder;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class ConfigManager
{
    private array $defaultConfig;
    public function __construct() {
        $this->defaultConfig = $this->getDefaultConfig();
    }

    public function getDefaultConfig(): array
    {
        $cwd = getcwd() . '/';

        return [
            'packages_path' => $cwd . 'package_builder/packages/',
            'core_path' => $cwd . 'core/components/',
            'templates_path' => dirname(__DIR__) . '/templates/',
        ];
    }

    public function loadPackageConfig(string $packageName): ?array
    {
        $configPath = $this->defaultConfig['packages_path'] . $packageName . '/config.php';

        if (!file_exists($configPath)) {
            return null;
        }

        $config = include $configPath;

        return is_array($config) ? $config : null;
    }   

    public function getTemplatesPath(): string
    {
        return $this->defaultConfig['templates_path'];
    }

    public function copyTemplates(string $destination): bool
    {
        $source = rtrim($this->defaultConfig['templates_path'], '/');
        $destination = rtrim($destination, '/');

        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $sourceLen = strlen($source) + 1;

        foreach ($iterator as $file) {
            $target = $destination . '/' . substr($file->getPathname(), $sourceLen);

            if ($file->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($file->getPathname(), $target);
            }
        }

        return true;
    }

    public function createPackageTemplate(string $packageName, array $options = []): bool
    {
        try {
            // Создаем структуру директорий
            $this->createPackageStructure($packageName, $options);

            // Создаем конфигурационный файл пакета
            $this->createPackageConfig($packageName, $options);

            return true;
        } catch (Exception $e) {
            error_log("Error creating package template: " . $e->getMessage());
            return false;
        }
    }   
    
    private function createPackageStructure(string $packageName, array $options = []): void
    {
        $basePath = !empty($options['template']) ? rtrim($options['template'], '/') . '/' : $this->defaultConfig['templates_path'];
        $templatePath = $basePath . 'components';
        $targetPath = $this->defaultConfig['core_path'] . $packageName;
        
        // Создаем целевую директорию
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }
        
        // Рекурсивно копируем все файлы и папки
        $this->recursiveCopyWithProcessing($templatePath, $targetPath, $packageName, $options);
        
        // Если не нужно генерировать элементы, удаляем папку
        if (!($options['generateElements'] ?? false)) {
            $elementsPath = $targetPath . '/elements';
            if (is_dir($elementsPath)) {
                $this->removeDirectory($elementsPath);
            }
        }

        // Удаляем опциональные файлы если не нужны
        if (!($options['phpCsFixer'] ?? false)) {
            $file = $targetPath . '/.php-cs-fixer.dist.php';
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (!($options['eslint'] ?? false)) {
            foreach (['eslint.config.js', 'package.json'] as $f) {
                $file = $targetPath . '/' . $f;
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        $this->applyToolsConfig($targetPath, $options);
    }

    private function applyToolsConfig(string $targetPath, array $options): void
    {
        $toolsPath = $options['toolsConfigPath'] ?? '';

        if (empty($toolsPath) || !is_dir($toolsPath)) {
            return;
        }

        $toolsPath = rtrim($toolsPath, '/');

        $map = [
            'phpstan.neon' => 'phpstan.neon',
            '.php-cs-fixer.dist.php' => '.php-cs-fixer.dist.php',
            'eslint.config.js' => 'eslint.config.js',
            'package.json' => 'package.json',
        ];

        foreach ($map as $source => $target) {
            $sourcePath = $toolsPath . '/' . $source;
            $targetFile = $targetPath . '/' . $target;

            if (file_exists($sourcePath) && file_exists($targetFile)) {
                copy($sourcePath, $targetFile);
            }
        }
    }
    
    private function createPackageConfig(string $packageName, array $options = []): void
    {
        $basePath = !empty($options['template']) ? rtrim($options['template'], '/') . '/' : $this->defaultConfig['templates_path'];
        $packagesTemplatePath = $basePath . 'packages';
        $packageConfigPath = $this->defaultConfig['packages_path'] . $packageName;
        
        // Создаем директорию для конфига пакета
        if (!is_dir($packageConfigPath)) {
            mkdir($packageConfigPath, 0755, true);
        }
        
        // Копируем шаблоны конфига с обработкой
        $this->recursiveCopyWithProcessing($packagesTemplatePath, $packageConfigPath, $packageName, $options);
    }
    
    private function recursiveCopyWithProcessing(string $source, string $destination, string $packageName, array $options): void
    {
        if (!is_dir($source)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $sourceLength = strlen($source);
        
        foreach ($iterator as $file) {
            // Получаем относительный путь от source
            $relativePath = substr($file->getPathname(), $sourceLength);
            $relativePath = ltrim($relativePath, '/\\');
            
            // Заменяем плейсхолдеры в относительном пути
            $relativePath = str_replace('{{package_name}}', $packageName, $relativePath);
            
            $targetPath = $destination . '/' . $relativePath;
            
            if ($file->isDir()) {
                // Создаем директорию
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // Обрабатываем файл
                $fileName = basename($targetPath);
                
                // Заменяем плейсхолдеры в имени файла
                $fileName = $this->replacePlaceholders($fileName, $packageName, $options);
                $targetPath = dirname($targetPath) . '/' . $fileName;
                
                // Если это .template файл, обрабатываем его
                if (str_ends_with($fileName, '.template')) {
                    $targetPath = str_replace('.template', '', $targetPath);
                    $this->processTemplateFile($file->getPathname(), $targetPath, $packageName, $options);
                } else {
                    // Просто копируем файл
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    copy($file->getPathname(), $targetPath);
                }
            }
        }
    }
    
    private function replacePlaceholders(string $text, string $packageName, array $options): string
    {
        $placeholders = $this->getPlaceholders($packageName, $options);
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }
    
    private function processTemplateFile(string $sourcePath, string $targetPath, string $packageName, array $options): void
    {
        $content = $this->replacePlaceholders(file_get_contents($sourcePath), $packageName, $options);
        
        // Создаем директорию если нужно
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        file_put_contents($targetPath, $content);
    }
    
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    private function getPlaceholders(string $packageName, ?array $options = []): array
    {
        return [
            '{{package_name}}' => strtolower($packageName),
            '{{Package_name}}' => $this->toPascalCase($packageName),
            '{{short_name}}' => $options['shortName'] ?? substr($packageName, 0, 3),
            '{{author_name}}' => $options['author'] ?? 'Your Name',
            '{{author_email}}' => $options['email'] ?? 'your-email@example.com',
            '{{php_version}}' => $options['phpVersion'] ?? '8.1',
            '{{gitlogin}}' => $options['gitlogin'] ?? 'your-username',
            '{{repository}}' => $options['repository'] ?? 'https://github.com/',
            '{{current_year}}' => date('Y'),
            '{{current_date}}' => date('Y-m-d'),
            '{{composer_require_dev_extra}}' => ($options['phpCsFixer'] ?? false)
                ? ",\n    \"friendsofphp/php-cs-fixer\": \"^3.0\""
                : '',
            '{{composer_scripts_extra}}' => ($options['phpCsFixer'] ?? false)
                ? ",\n    \"cs-check\": \"php-cs-fixer fix --dry-run\",\n    \"cs-fix\": \"php-cs-fixer fix\""
                : '',
        ];
    }
}
