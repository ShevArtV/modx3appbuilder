<?php

namespace ComponentBuilder;

use MODX\Revolution\modCategory;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOTransport;

class ComponentBuilder
{
    private readonly modX $modx;
    private array $config;
    private modPackageBuilder $builder;
    private modCategory $category;
    private array $categoryAttributes = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->initializeModx();
    }

    /**
     * @return modX
     */
    public function getModx(): modX
    {
        return $this->modx;
    }

    /**
     * @param string $packageName
     * @param array $buildOptions
     * @return bool
     */
    public function build(string $packageName, array $buildOptions = []): bool
    {
        $packageConfig = $this->resolveConfig($packageName);

        if (!$packageConfig) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Package config not found: {$packageName}");
            return false;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Building package: {$packageName}");

        $this->builder = new modPackageBuilder($this->modx);
        $this->builder->createPackage(
            $packageConfig['name_lower'],
            $packageConfig['version'],
            $packageConfig['release']
        );

        $this->builder->registerNamespace(
            $packageConfig['name_lower'],
            false,
            true,
            '{core_path}components/' . $packageConfig['name_lower'] . '/'
        );

        $encrypt = !empty($packageConfig['encrypt']) || !empty($buildOptions['encrypt']);
        $encryptionReady = false;

        if ($encrypt) {
            $encryptionReady = $this->setupEncryption($packageConfig);
        }

        $this->initializeCategory($packageConfig);

        if (isset($packageConfig['elements'])) {
            $this->processElements($packageConfig);
        }

        if ($encryptionReady) {
            $this->categoryAttributes['vehicle_class'] = $packageConfig['encrypt_vehicle_class']
                ?? $packageConfig['name'] . '\\Transport\\EncryptedVehicle';
            $this->categoryAttributes[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
        }

        $vehicle = $this->builder->createVehicle($this->category, $this->categoryAttributes);

        $vehicle->resolve('file', [
            'source' => $packageConfig['abs_core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ]);

        if (is_dir($packageConfig['abs_assets'])) {
            $vehicle->resolve('file', [
                'source' => $packageConfig['abs_assets'],
                'target' => "return MODX_ASSETS_PATH . 'components/';",
            ]);
        }

        $this->addResolvers($vehicle, $packageConfig);

        $this->builder->putVehicle($vehicle);

        if ($encryptionReady) {
            $this->addEncryptionResolverEnd($packageConfig);
        }

        $this->builder->setPackageAttributes([
            'changelog' => $this->readDocFile($packageConfig['abs_core'] . 'docs/changelog.txt'),
            'license' => $this->readDocFile($packageConfig['abs_core'] . 'docs/license.txt'),
            'readme' => $this->readDocFile($packageConfig['abs_core'] . 'docs/readme.txt'),
            'requires' => [
                'php' => '>=' . ($packageConfig['php_version'] ?? '8.1'),
                'modx' => '>=3.0.0',
            ],
        ]);

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        $signature = $this->builder->getSignature();
        $this->modx->log(modX::LOG_LEVEL_INFO, "Package built: {$signature}.transport.zip");

        if (!empty($buildOptions['install'])) {
            $this->install();
        }

        if (!empty($buildOptions['download'])) {
            $this->download();
        }

        return true;
    }

    /**
     * @return void
     */
    private function initializeModx(): void
    {
        if (!defined('MODX_CORE_PATH')) {
            $path = dirname(__DIR__, 2);
            while (!file_exists($path . '/core/config/config.inc.php') && (strlen($path) > 1)) {
                $path = dirname($path);
            }
            define('MODX_CORE_PATH', $path . '/core/');
        }

        require_once MODX_CORE_PATH . 'config/config.inc.php';
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

        $this->modx = new modX();
        $this->modx->initialize('mgr');

        $this->modx->setLogLevel(modX::LOG_LEVEL_INFO);
        $this->modx->setLogTarget(php_sapi_name() === 'cli' ? 'ECHO' : 'HTML');
    }

    /**
     * @param string $packageName
     * @return array|null
     */
    private function resolveConfig(string $packageName): ?array
    {
        $config = $this->config;
        $root = dirname(MODX_CORE_PATH) . '/';

        $config['abs_core'] = $root . ($config['paths']['core'] ?? 'core/components/' . $packageName . '/');
        $config['abs_assets'] = $root . ($config['paths']['assets'] ?? 'assets/components/' . $packageName . '/');

        if (!is_dir($config['abs_core'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Core path not found: {$config['abs_core']}");
            return null;
        }

        return $config;
    }

    /**
     * @param array $packageConfig
     * @return void
     */
    private function initializeCategory(array $packageConfig): void
    {
        $categoryName = $packageConfig['elements']['category'] ?? $packageConfig['name'];

        $this->category = $this->modx->newObject(modCategory::class);
        $this->category->set('category', $categoryName);

        $this->categoryAttributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [],
        ];

        $this->modx->log(modX::LOG_LEVEL_INFO, "Created category: {$categoryName}");
    }

    /**
     * @param array $packageConfig
     * @return void
     */
    private function processElements(array $packageConfig): void
    {
        $elementsManager = new ElementsManager(
            $this->modx,
            $this->builder,
            $this->category,
            $this->categoryAttributes,
            $packageConfig
        );

        $elementsManager->processElements($packageConfig['elements']);

        $this->categoryAttributes = $elementsManager->getCategoryAttributes();
    }

    /**
     * @param mixed $vehicle
     * @param array $packageConfig
     * @return void
     */
    private function addResolvers($vehicle, array $packageConfig): void
    {
        $resolversPath = $packageConfig['abs_core'] . 'resolvers/';

        if (!empty($packageConfig['resolvers_path'])) {
            $root = dirname(MODX_CORE_PATH) . '/';
            $resolversPath = $root . $packageConfig['resolvers_path'];
        }

        if (!is_dir($resolversPath)) {
            return;
        }

        $resolvers = array_filter(
            scandir($resolversPath),
            fn($file) => str_ends_with($file, '.php') && $file !== 'resolve.encryption.php'
        );

        sort($resolvers);

        foreach ($resolvers as $resolver) {
            if ($vehicle->resolve('php', ['source' => $resolversPath . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver: ' . $resolver);
            }
        }
    }

    /**
     * @param array $packageConfig
     * @return bool
     */
    private function setupEncryption(array $packageConfig): bool
    {
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Encryption enabled, requesting key...');

        /** @var modTransportProvider $provider */
        $provider = $this->modx->getObject(modTransportProvider::class, ['name' => 'modstore.pro']);
        if (!$provider) {
            $provider = $this->modx->getObject(modTransportProvider::class, 2);
        }

        if (!$provider) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'modstore.pro provider not found');
            return false;
        }

        $provider->xpdo->setOption('contentType', 'default');

        $params = [
            'package' => $packageConfig['name_lower'],
            'version' => $packageConfig['version'] . '-' . $packageConfig['release'],
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => '3.0.0',
        ];

        $response = $provider->request('package/encode', 'POST', $params);

        if ($response === false) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Failed to connect to modstore.pro');
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Encode API error: HTTP ' . $response->getStatusCode());
            return false;
        }

        $body = (string) $response->getBody();
        $data = @simplexml_load_string($body);

        if ($data === false || empty($data->key)) {
            $message = $data !== false && !empty($data->message) ? (string) $data->message : 'Invalid response';
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Encryption key error: ' . $message);
            return false;
        }

        define('PKG_ENCODE_KEY', (string) $data->key);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Encryption key received');

        $vehiclePath = $packageConfig['abs_core'] . 'src/Transport/EncryptedVehicle.php';
        if (file_exists($vehiclePath)) {
            require_once $vehiclePath;
        }

        $this->builder->package->put(
            [
                'source' => $vehiclePath,
                'target' => "return MODX_CORE_PATH . 'components/" . $packageConfig['name_lower'] . "/src/Transport/';",
            ],
            [
                'vehicle_class' => \xPDO\Transport\xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );

        $resolverPath = $this->findEncryptionResolver($packageConfig);
        if ($resolverPath) {
            $this->builder->putVehicle($this->builder->createVehicle(
                ['source' => $resolverPath],
                ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
            ));
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (install)');
        }

        return true;
    }

    /**
     * @param array $packageConfig
     * @return void
     */
    private function addEncryptionResolverEnd(array $packageConfig): void
    {
        $resolverPath = $this->findEncryptionResolver($packageConfig);
        if ($resolverPath) {
            $this->builder->putVehicle($this->builder->createVehicle(
                ['source' => $resolverPath],
                ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
            ));
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (uninstall)');
        }
    }

    /**
     * @param array $packageConfig
     * @return string|null
     */
    private function findEncryptionResolver(array $packageConfig): ?string
    {
        $paths = [
            $packageConfig['abs_core'] . 'resolvers/resolve.encryption.php',
            dirname(__DIR__) . '/packages/' . $packageConfig['name_lower'] . '/resolvers/resolve.encryption.php',
        ];

        if (!empty($packageConfig['resolvers_path'])) {
            $root = dirname(MODX_CORE_PATH) . '/';
            array_unshift($paths, $root . $packageConfig['resolvers_path'] . 'resolve.encryption.php');
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return void
     */
    private function install(): void
    {
        $signature = $this->builder->getSignature();
        $this->modx->log(modX::LOG_LEVEL_INFO, "Installing package: {$signature}");

        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);

        /** @var modTransportPackage $package */
        $package = $this->modx->getObject(modTransportPackage::class, ['signature' => $signature]);

        if (!$package) {
            $package = $this->modx->newObject(modTransportPackage::class);
            $package->set('signature', $signature);
            $package->fromArray([
                'created' => date('Y-m-d H:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $this->config['name'],
                'version_major' => $versionSignature[0],
                'version_minor' => $versionSignature[1] ?? 0,
                'version_patch' => $versionSignature[2] ?? 0,
            ]);

            if (!empty($sig[2])) {
                $r = preg_split('#([0-9]+)#', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', $r[1] ?? '0');
                } else {
                    $package->set('release', $sig[2]);
                }
            }

            $package->save();
        }

        if ($package->install()) {
            $this->modx->runProcessor('System/ClearCache');
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Package installed successfully');
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Package installation failed');
        }
    }

    /**
     * @return void
     */
    private function download(): void
    {
        $name = $this->builder->getSignature() . '.transport.zip';
        $filePath = MODX_CORE_PATH . 'packages/' . $name;

        if (!file_exists($filePath)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Package file not found: {$filePath}");
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        exit($content);
    }

    /**
     * @param string $filepath
     * @return string
     */
    private function readDocFile(string $filepath): string
    {
        if (!file_exists($filepath)) {
            return '';
        }

        $content = file_get_contents($filepath);

        return $content !== false ? $content : '';
    }
}
