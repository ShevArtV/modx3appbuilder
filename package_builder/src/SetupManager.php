<?php

namespace ComponentBuilder;

class SetupManager
{
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: getcwd();
    }

    public function isSetupComplete(): bool
    {
        return file_exists($this->basePath . '/core/config/config.inc.php');
    }

    public function hasCore(): bool
    {
        return file_exists($this->basePath . '/core/src/Revolution/modX.php');
    }

    public function setup(): bool
    {
        if (!$this->hasCore()) {
            echo "Downloading MODX 3 core...\n";
            if (!$this->downloadCore()) {
                return false;
            }
        } else {
            echo "MODX core found\n";
        }

        echo "Creating config...\n";
        $this->createConfig();

        echo "Initializing SQLite database...\n";
        $this->initDatabase();

        $cachePath = $this->basePath . '/core/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $packagesPath = $this->basePath . '/core/packages';
        if (!is_dir($packagesPath)) {
            mkdir($packagesPath, 0755, true);
        }

        return true;
    }

    public function downloadCore(): bool
    {
        $tagsUrl = 'https://api.github.com/repos/modxcms/revolution/tags?per_page=1';

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: modxapp\r\n",
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($tagsUrl, false, $context);
        if (!$response) {
            echo "ERROR: Failed to fetch tags from GitHub\n";
            return false;
        }

        $tags = json_decode($response, true);
        if (empty($tags) || !is_array($tags)) {
            echo "ERROR: No tags found\n";
            return false;
        }

        $zipUrl = $tags[0]['zipball_url'] ?? null;
        $tag = $tags[0]['name'] ?? 'unknown';

        if (!$zipUrl) {
            echo "ERROR: Could not find download URL\n";
            return false;
        }

        echo "Downloading MODX {$tag}...\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'modx_') . '.zip';
        $zipContent = @file_get_contents($zipUrl, false, $context);

        if (!$zipContent) {
            echo "ERROR: Failed to download MODX\n";
            return false;
        }

        file_put_contents($tmpFile, $zipContent);

        echo "Extracting core...\n";
        $extracted = $this->extractCore($tmpFile);
        @unlink($tmpFile);

        return $extracted;
    }

    private function extractCore(string $zipFile): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            echo "ERROR: Failed to open zip archive\n";
            return false;
        }

        $tmpDir = sys_get_temp_dir() . '/modx_extract_' . uniqid();
        $zip->extractTo($tmpDir);
        $zip->close();

        $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            echo "ERROR: Archive is empty\n";
            return false;
        }

        $sourceCore = $dirs[0] . '/core';
        if (!is_dir($sourceCore)) {
            echo "ERROR: core/ directory not found in archive\n";
            $this->removeDir($tmpDir);
            return false;
        }

        $targetCore = $this->basePath . '/core';
        if (!is_dir($targetCore)) {
            mkdir($targetCore, 0755, true);
        }

        $this->recursiveCopy($sourceCore, $targetCore);

        $rootDir = $dirs[0];
        foreach (['composer.json', 'composer.lock'] as $file) {
            if (file_exists($rootDir . '/' . $file)) {
                copy($rootDir . '/' . $file, $targetCore . '/' . $file);
            }
        }

        $this->removeDir($tmpDir);

        echo "Core extracted to {$targetCore}\n";

        if (file_exists($targetCore . '/composer.json')) {
            echo "Installing MODX dependencies...\n";
            $this->composerInstall($targetCore);
        }

        return true;
    }

    public function createConfig(): void
    {
        $configDir = $this->basePath . '/core/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $dbPath = $this->basePath . '/core/modxapp.sqlite';
        $corePath = $this->basePath . '/core/';

        $config = <<<PHP
<?php
global \$database_dsn, \$database_user, \$database_password, \$config_options, \$driver_options, \$table_prefix, \$site_id, \$uuid;

\$database_dsn = 'sqlite:{$dbPath}';
\$database_user = '';
\$database_password = '';
\$table_prefix = 'modx_';
\$config_options = [];
\$driver_options = [];
\$site_id = 'modxapp';
\$uuid = 'modxapp-local';

if (!defined('MODX_CORE_PATH')) define('MODX_CORE_PATH', '{$corePath}');
if (!defined('MODX_PROCESSORS_PATH')) define('MODX_PROCESSORS_PATH', '{$corePath}src/Revolution/Processors/');
if (!defined('MODX_CONNECTORS_PATH')) define('MODX_CONNECTORS_PATH', '{$this->basePath}/connectors/');
if (!defined('MODX_MANAGER_PATH')) define('MODX_MANAGER_PATH', '{$this->basePath}/manager/');
if (!defined('MODX_MANAGER_URL')) define('MODX_MANAGER_URL', '/manager/');
if (!defined('MODX_BASE_PATH')) define('MODX_BASE_PATH', '{$this->basePath}/');
if (!defined('MODX_BASE_URL')) define('MODX_BASE_URL', '/');
if (!defined('MODX_ASSETS_PATH')) define('MODX_ASSETS_PATH', '{$this->basePath}/assets/');
if (!defined('MODX_ASSETS_URL')) define('MODX_ASSETS_URL', '/assets/');
PHP;

        file_put_contents($configDir . '/config.inc.php', $config);
    }

    public function initDatabase(): void
    {
        $dbPath = $this->basePath . '/core/modxapp.sqlite';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $corePath = $this->basePath . '/core/';

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modx_workspaces (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT DEFAULT '',
                path TEXT DEFAULT '',
                created TEXT,
                active INTEGER DEFAULT 0,
                attributes TEXT
            )
        ");

        $stmt = $pdo->query("SELECT COUNT(*) FROM modx_workspaces WHERE active = 1");
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare("INSERT INTO modx_workspaces (name, path, active, created) VALUES (?, ?, 1, datetime('now'))");
            $stmt->execute(['default', '{core_path}']);
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modx_context (
                key TEXT PRIMARY KEY,
                name TEXT,
                description TEXT,
                rank INTEGER DEFAULT 0
            )
        ");

        $stmt = $pdo->query("SELECT COUNT(*) FROM modx_context WHERE key = 'mgr'");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("INSERT INTO modx_context (key, name, rank) VALUES ('mgr', 'mgr', 0)");
            $pdo->exec("INSERT INTO modx_context (key, name, rank) VALUES ('web', 'web', 1)");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modx_context_setting (
                context_key TEXT,
                key TEXT,
                value TEXT,
                xtype TEXT DEFAULT 'textfield',
                namespace TEXT DEFAULT 'core',
                area TEXT DEFAULT '',
                editedon TEXT,
                PRIMARY KEY (context_key, key)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modx_namespaces (
                name TEXT PRIMARY KEY,
                path TEXT,
                assets_path TEXT
            )
        ");

        $stmt = $pdo->query("SELECT COUNT(*) FROM modx_namespaces WHERE name = 'core'");
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare("INSERT INTO modx_namespaces (name, path) VALUES ('core', ?)");
            $stmt->execute([$corePath]);
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS modx_system_settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                xtype TEXT DEFAULT 'textfield',
                namespace TEXT DEFAULT 'core',
                area TEXT DEFAULT '',
                editedon TEXT
            )
        ");

        echo "SQLite database initialized: {$dbPath}\n";
    }

    private function composerInstall(string $dir): void
    {
        $composerBin = $this->findComposer();

        if (!$composerBin) {
            echo "  WARNING: Composer not found. Run 'composer install' manually in {$dir}\n";
            return;
        }

        $cmd = escapeshellarg($composerBin) . ' install --no-dev --no-interaction --working-dir=' . escapeshellarg($dir) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $phpBin = PHP_BINARY;
            $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($composerBin) . ' install --no-dev --no-interaction --working-dir=' . escapeshellarg($dir) . ' 2>&1';
            exec($cmd, $output, $exitCode);
        }

        if ($exitCode === 0) {
            echo "  Dependencies installed\n";
        } else {
            echo "  WARNING: composer install failed. Run manually in {$dir}\n";
            echo "  " . implode("\n  ", array_slice($output, -3)) . "\n";
        }
    }

    private function findComposer(): ?string
    {
        $paths = [
            'composer',
            'composer.phar',
            getenv('HOME') . '/bin/composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ];

        foreach ($paths as $path) {
            $which = trim(shell_exec("which {$path} 2>/dev/null") ?? '');
            if (!empty($which)) {
                return $which;
            }
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function recursiveCopy(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $sourceLen = strlen($source) + 1;

        foreach ($iterator as $file) {
            $target = $destination . '/' . substr($file->getPathname(), $sourceLen);

            if ($file->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($file->getPathname(), $target);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
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
