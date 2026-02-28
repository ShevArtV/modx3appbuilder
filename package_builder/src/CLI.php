<?php

namespace ComponentBuilder;

class CLI
{
    private array $args = [];
    private array $options = [];
    private string $userConfigPath;

    public function __construct(array $argv)
    {
        $this->userConfigPath = dirname(__FILE__, 2) . '/user.config.json';
        array_shift($argv);
        $this->parseArguments($argv);
    }

    private function parseArguments(array $argv): void
    {
        $args = [];
        $options = [];
        
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', $arg, 2);
                $key = substr($parts[0], 2);
                $value = $parts[1] ?? true;
                $options[$key] = $value;
            } else {
                $args[] = $arg;
            }
        }
        
        $this->args = $args;
        $this->options = $options;
    }

    public function getCommand(): ?string
    {
        return $this->args[0] ?? null;
    }

    public function getPackageName(): ?string
    {
        return $this->args[1] ?? null;
    }

    public function getOption(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function showHelp(): void
    {
        echo "ComponentBuilder for MODX Revolution 3\n\n";
        echo "Usage: php cli.php <command> [package_name] [options]\n\n";
        echo "Commands:\n";
        echo "  create <name>           Create new package from template\n";
        echo "  build <name>            Build transport package\n";
        echo "  schema <name>           Generate classes from XML schema\n";
        echo "  elements <name>         Sync elements to database\n";
        echo "  extract-lexicons <name> Extract lexicon keys from code\n";
        echo "  extract-settings <name> Extract settings from code\n";
        echo "  help                    Show this help\n";
        echo "\nBuild options:\n";
        echo "  --install        Install package after build\n";
        echo "  --download       Download package zip after build (web only)\n";
        echo "  --encrypt        Enable package encryption\n";
        echo "\nCreate options:\n";
        echo "  --elements       Generate element template files\n";
        echo "  --author=        Author name\n";
        echo "  --email=         Author email\n";
        echo "  --php-version=   Minimum PHP version (default: 8.1)\n";
        echo "  --gitlogin=      Git username\n";
        echo "  --repository=    Repository URL\n";
        echo "  --short-name=    Short name for lexicons/settings\n";
        echo "  --interactive    Interactive mode\n";
        echo "\nSchema options:\n";
        echo "  --validate       Validate schema without generating\n";
        echo "\nGeneral options:\n";
        echo "  --verbose        Show detailed output\n";
    }

    public function showError(string $message): void
    {
        echo "ERROR: {$message}\n";
        $this->showHelp();
        exit(1);
    }

    public function showMessage(string $message, string $level = 'INFO'): void
    {
        if ($this->hasOption('verbose') || $level === 'ERROR') {
            echo "[{$level}] {$message}\n";
        }
    }

    public function prompt(string $question, string $default = ''): string
    {
        if ($default) {
            echo "{$question} [{$default}]: ";
        } else {
            echo "{$question}: ";
        }
        
        $handle = fopen('php://stdin', 'r');
        $input = trim(fgets($handle));
        fclose($handle);
        
        return $input ?: $default;
    }

    public function promptRequired(string $question): string
    {
        while (true) {
            $input = $this->prompt($question);
            if (!empty($input)) {
                return $input;
            }
            echo "This field is required. Please enter a value.\n";
        }
    }

    public function promptYesNo(string $question, bool $default = true): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        
        while (true) {
            $input = strtolower($this->prompt("{$question} [{$defaultText}]", $default ? 'y' : 'n'));
            
            if (empty($input)) {
                return $default;
            }
            
            if (in_array($input, ['y', 'yes', 'true', '1'])) {
                return true;
            }
            
            if (in_array($input, ['n', 'no', 'false', '0'])) {
                return false;
            }
            
            echo "Please enter 'y' or 'n'.\n";
        }
    }

    public function showInteractiveHeader(): void
    {
        echo "\n=== ComponentBuilder Interactive Mode ===\n";
        echo "Let's create your MODX 3 package step by step.\n\n";
    }

    public function loadUserConfig(): array
    {
        if (!file_exists($this->userConfigPath)) {
            return [];
        }
        
        $configContent = file_get_contents($this->userConfigPath);
        $config = json_decode($configContent, true);
        
        return is_array($config) ? $config : [];
    }

    public function saveUserConfig(array $config): void
    {
        $jsonContent = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->userConfigPath, $jsonContent);
    }
}
