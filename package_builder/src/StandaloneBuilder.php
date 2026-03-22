<?php

namespace ComponentBuilder;

class StandaloneBuilder
{
    private string $signature;
    private string $buildDir;
    private string $packagesDir;
    private array $vehicles = [];
    private array $attributes = [];

    public function __construct(string $packagesDir)
    {
        $this->packagesDir = rtrim($packagesDir, '/') . '/';

        if (!is_dir($this->packagesDir)) {
            mkdir($this->packagesDir, 0755, true);
        }
    }

    public function createPackage(string $name, string $version, string $release): void
    {
        $this->signature = strtolower($name);
        if (!empty($version)) {
            $this->signature .= '-' . $version;
        }
        if (!empty($release)) {
            $this->signature .= '-' . $release;
        }

        $this->buildDir = $this->packagesDir . $this->signature . '/';

        if (is_dir($this->buildDir)) {
            $this->removeDir($this->buildDir);
        }
        mkdir($this->buildDir, 0755, true);

        $zipFile = $this->packagesDir . $this->signature . '.transport.zip';
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        echo "Creating package: {$this->signature}\n";
    }

    public function addNamespace(string $name, string $path, string $assetsPath = ''): void
    {
        $object = [
            'name' => $name,
            'path' => $path,
            'assets_path' => $assetsPath,
        ];

        $hash = md5(json_encode($object));

        $vehicle = [
            'unique_key' => 'name',
            'preserve_keys' => true,
            'update_object' => true,
            'resolve' => null,
            'validate' => null,
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'vehicle_package' => '',
            'class' => 'MODX\\Revolution\\modNamespace',
            'guid' => $this->generateGuid(),
            'native_key' => $name,
            'signature' => $hash,
            'object' => json_encode($object),
        ];

        $dir = $this->buildDir . 'MODX/Revolution/modNamespace/';
        $this->writeVehicle($dir, $hash, $vehicle);

        $this->vehicles[] = [
            'vehicle_package' => '',
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'class' => 'MODX\\Revolution\\modNamespace',
            'guid' => $vehicle['guid'],
            'native_key' => $name,
            'filename' => 'MODX/Revolution/modNamespace/' . $hash . '.vehicle',
        ];

        echo "  Namespace: {$name}\n";
    }

    public function addCategory(string $categoryName, array $elements, array $config, IgnoreFilter $filter, string $corePath, string $assetsPath = ''): void
    {
        $categoryObject = [
            'id' => null,
            'parent' => 0,
            'category' => $categoryName,
            'rank' => 0,
        ];

        $relatedObjects = [];
        $relatedObjectAttributes = [];

        if (!empty($elements['chunks'])) {
            $this->processChunksForCategory($elements['chunks'], $config, $relatedObjects, $relatedObjectAttributes, $corePath);
        }

        if (!empty($elements['snippets'])) {
            $this->processSnippetsForCategory($elements['snippets'], $config, $relatedObjects, $relatedObjectAttributes, $corePath);
        }

        if (!empty($elements['plugins'])) {
            $this->processPluginsForCategory($elements['plugins'], $config, $relatedObjects, $relatedObjectAttributes, $corePath);
        }

        if (!empty($elements['templates'])) {
            $this->processTemplatesForCategory($elements['templates'], $config, $relatedObjects, $relatedObjectAttributes, $corePath);
        }

        if (!empty($elements['tvs'])) {
            $this->processTVsForCategory($elements['tvs'], $config, $relatedObjects, $relatedObjectAttributes);
        }

        $hash = md5(json_encode($categoryObject) . $this->signature);

        $vehicleDir = 'MODX/Revolution/modCategory/' . $hash;
        $resolvers = [];
        $fileIndex = 0;

        $filteredCore = $this->prepareBuildSource($filter, $corePath, $config['name_lower'] . '_core');
        $targetDir = $this->buildDir . $vehicleDir . '/' . $fileIndex . '/';
        $this->recursiveCopy($filteredCore, $targetDir);
        $this->removeDir($filteredCore);

        $resolvers[] = [
            'type' => 'file',
            'body' => json_encode([
                'source' => $this->signature . '/' . $vehicleDir . '/' . $fileIndex . '/',
                'target' => "return MODX_CORE_PATH . 'components/';",
                'name' => $config['name_lower'],
            ]),
        ];
        $fileIndex++;

        if (!empty($assetsPath) && is_dir($assetsPath)) {
            $filteredAssets = $this->prepareBuildSource($filter, $assetsPath, $config['name_lower'] . '_assets');
            $targetDir = $this->buildDir . $vehicleDir . '/' . $fileIndex . '/';
            $this->recursiveCopy($filteredAssets, $targetDir);
            $this->removeDir($filteredAssets);

            $resolvers[] = [
                'type' => 'file',
                'body' => json_encode([
                    'source' => $this->signature . '/' . $vehicleDir . '/' . $fileIndex . '/',
                    'target' => "return MODX_ASSETS_PATH . 'components/';",
                    'name' => $config['name_lower'],
                ]),
            ];
        }

        $vehicle = [
            'unique_key' => 'category',
            'preserve_keys' => false,
            'update_object' => true,
            'related_objects' => !empty($relatedObjects) ? $relatedObjects : null,
            'related_object_attributes' => $relatedObjectAttributes,
            'resolve' => !empty($resolvers) ? $resolvers : null,
            'validate' => null,
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'vehicle_package' => '',
            'class' => 'MODX\\Revolution\\modCategory',
            'guid' => $this->generateGuid(),
            'native_key' => null,
            'signature' => $hash,
            'object' => json_encode($categoryObject),
        ];

        $dir = $this->buildDir . 'MODX/Revolution/modCategory/';
        $this->writeVehicle($dir, $hash, $vehicle);

        $this->vehicles[] = [
            'vehicle_package' => '',
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'class' => 'MODX\\Revolution\\modCategory',
            'guid' => $vehicle['guid'],
            'native_key' => null,
            'filename' => 'MODX/Revolution/modCategory/' . $hash . '.vehicle',
        ];

        echo "  Category: {$categoryName}\n";
    }

    public function addSetting(string $key, array $data, string $namespace, bool $update = false): void
    {
        $object = array_merge([
            'key' => $key,
            'value' => '',
            'xtype' => 'textfield',
            'namespace' => $namespace,
            'area' => '',
            'editedon' => null,
        ], $data);

        $hash = md5(json_encode($object));

        $vehicle = [
            'unique_key' => 'key',
            'preserve_keys' => true,
            'update_object' => $update,
            'resolve' => null,
            'validate' => null,
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'vehicle_package' => '',
            'class' => 'MODX\\Revolution\\modSystemSetting',
            'guid' => $this->generateGuid(),
            'native_key' => $key,
            'signature' => $hash,
            'object' => json_encode($object),
        ];

        $dir = $this->buildDir . 'MODX/Revolution/modSystemSetting/';
        $this->writeVehicle($dir, $hash, $vehicle);

        $this->vehicles[] = [
            'vehicle_package' => '',
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'class' => 'MODX\\Revolution\\modSystemSetting',
            'guid' => $vehicle['guid'],
            'native_key' => $key,
            'filename' => 'MODX/Revolution/modSystemSetting/' . $hash . '.vehicle',
        ];
    }

    public function addMenu(string $text, array $data, string $namespace, bool $update = true): void
    {
        $object = array_merge([
            'text' => $text,
            'parent' => 'components',
            'action' => '',
            'description' => '',
            'icon' => '',
            'menuindex' => 0,
            'params' => '',
            'handler' => '',
            'permissions' => '',
            'namespace' => $namespace,
        ], $data);

        $hash = md5(json_encode($object));

        $vehicle = [
            'unique_key' => 'text',
            'preserve_keys' => true,
            'update_object' => $update,
            'resolve' => null,
            'validate' => null,
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'vehicle_package' => '',
            'class' => 'MODX\\Revolution\\modMenu',
            'guid' => $this->generateGuid(),
            'native_key' => $text,
            'signature' => $hash,
            'object' => json_encode($object),
        ];

        $dir = $this->buildDir . 'MODX/Revolution/modMenu/';
        $this->writeVehicle($dir, $hash, $vehicle);

        $this->vehicles[] = [
            'vehicle_package' => '',
            'vehicle_class' => 'xPDO\\Transport\\xPDOObjectVehicle',
            'class' => 'MODX\\Revolution\\modMenu',
            'guid' => $vehicle['guid'],
            'native_key' => $text,
            'filename' => 'MODX/Revolution/modMenu/' . $hash . '.vehicle',
        ];
    }

    public function setPackageAttributes(array $attrs): void
    {
        $this->attributes = $attrs;
    }

    public function pack(): bool
    {
        $manifest = [
            'manifest-version' => '1.1',
            'manifest-attributes' => $this->attributes,
            'manifest-vehicles' => $this->vehicles,
        ];

        $manifestContent = "<?php return " . var_export($manifest, true) . ";\n";
        file_put_contents($this->buildDir . 'manifest.php', $manifestContent);

        $zipFile = $this->packagesDir . $this->signature . '.transport.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            echo "ERROR: Failed to create zip archive\n";
            return false;
        }

        $this->addDirToZip($zip, $this->buildDir, $this->signature . '/');
        $zip->close();

        $this->removeDir($this->buildDir);

        echo "Package built: {$this->signature}.transport.zip\n";
        return true;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    private function processChunksForCategory(array $chunks, array $config, array &$relatedObjects, array &$relatedObjectAttributes, string $corePath): void
    {
        $relatedObjectAttributes['Chunks'] = [
            'preserve_keys' => false,
            'update_object' => $config['build']['update']['chunks'] ?? true,
            'unique_key' => 'name',
        ];

        $relatedObjects['Chunks'] = [];

        foreach ($chunks as $name => $data) {
            $content = $this->resolveContent($data, $corePath);
            $hash = md5($name . $this->signature . 'chunk');

            $relatedObjects['Chunks'][$hash] = [
                'preserve_keys' => false,
                'update_object' => $config['build']['update']['chunks'] ?? true,
                'unique_key' => 'name',
                'class' => 'MODX\\Revolution\\modChunk',
                'object' => json_encode([
                    'id' => null,
                    'source' => 0,
                    'property_preprocess' => 0,
                    'name' => $name,
                    'description' => $data['description'] ?? '',
                    'editor_type' => 0,
                    'category' => 0,
                    'cache_type' => 0,
                    'snippet' => $content,
                    'locked' => 0,
                    'properties' => 'a:0:{}',
                    'static' => !empty($config['static']['chunks']) ? 1 : 0,
                    'static_file' => $this->resolveStaticFile($data),
                    'content' => $content,
                ]),
                'guid' => $this->generateGuid(),
                'native_key' => null,
                'signature' => $hash,
            ];
        }

        echo "    Chunks: " . count($chunks) . "\n";
    }

    private function processSnippetsForCategory(array $snippets, array $config, array &$relatedObjects, array &$relatedObjectAttributes, string $corePath): void
    {
        $relatedObjectAttributes['Snippets'] = [
            'preserve_keys' => false,
            'update_object' => $config['build']['update']['snippets'] ?? true,
            'unique_key' => 'name',
        ];

        $relatedObjects['Snippets'] = [];

        foreach ($snippets as $name => $data) {
            $content = $this->resolveContent($data, $corePath);
            $hash = md5($name . $this->signature . 'snippet');

            $props = 'a:0:{}';
            if (!empty($data['properties'])) {
                $props = serialize($data['properties']);
            }

            $relatedObjects['Snippets'][$hash] = [
                'preserve_keys' => false,
                'update_object' => $config['build']['update']['snippets'] ?? true,
                'unique_key' => 'name',
                'class' => 'MODX\\Revolution\\modSnippet',
                'object' => json_encode([
                    'id' => null,
                    'source' => 0,
                    'property_preprocess' => 0,
                    'name' => $name,
                    'description' => $data['description'] ?? '',
                    'editor_type' => 0,
                    'category' => 0,
                    'cache_type' => 0,
                    'snippet' => $content,
                    'locked' => 0,
                    'properties' => $props,
                    'static' => !empty($config['static']['snippets']) ? 1 : 0,
                    'static_file' => $this->resolveStaticFile($data),
                    'content' => $content,
                ]),
                'guid' => $this->generateGuid(),
                'native_key' => null,
                'signature' => $hash,
            ];
        }

        echo "    Snippets: " . count($snippets) . "\n";
    }

    private function processPluginsForCategory(array $plugins, array $config, array &$relatedObjects, array &$relatedObjectAttributes, string $corePath): void
    {
        $relatedObjectAttributes['Plugins'] = [
            'unique_key' => 'name',
            'preserve_keys' => false,
            'update_object' => $config['build']['update']['plugins'] ?? true,
            'related_objects' => true,
            'related_object_attributes' => [
                'PluginEvents' => [
                    'preserve_keys' => true,
                    'update_object' => true,
                    'unique_key' => ['pluginid', 'event'],
                ],
            ],
        ];

        $relatedObjects['Plugins'] = [];

        foreach ($plugins as $name => $data) {
            $content = $this->resolveContent($data, $corePath);
            $hash = md5($name . $this->signature . 'plugin');

            $pluginRelated = null;
            if (!empty($data['events'])) {
                $pluginRelated = ['PluginEvents' => []];
                foreach ($data['events'] as $eventName => $eventData) {
                    if (is_numeric($eventName)) {
                        $eventName = $eventData;
                        $eventData = [];
                    }
                    $eventHash = md5($name . $eventName);
                    $pluginRelated['PluginEvents'][$eventHash] = [
                        'preserve_keys' => true,
                        'update_object' => true,
                        'unique_key' => ['pluginid', 'event'],
                        'class' => 'MODX\\Revolution\\modPluginEvent',
                        'object' => json_encode([
                            'pluginid' => 0,
                            'event' => $eventName,
                            'priority' => $eventData['priority'] ?? 0,
                            'propertyset' => $eventData['propertyset'] ?? 0,
                        ]),
                        'guid' => $this->generateGuid(),
                        'native_key' => [0, $eventName],
                        'signature' => $eventHash,
                    ];
                }
            }

            $relatedObjects['Plugins'][$hash] = [
                'unique_key' => 'name',
                'preserve_keys' => false,
                'update_object' => $config['build']['update']['plugins'] ?? true,
                'related_objects' => $pluginRelated,
                'related_object_attributes' => [
                    'PluginEvents' => [
                        'preserve_keys' => true,
                        'update_object' => true,
                        'unique_key' => ['pluginid', 'event'],
                    ],
                ],
                'class' => 'MODX\\Revolution\\modPlugin',
                'object' => json_encode([
                    'id' => null,
                    'source' => 0,
                    'property_preprocess' => 0,
                    'name' => $name,
                    'description' => $data['description'] ?? '',
                    'editor_type' => 0,
                    'category' => 0,
                    'cache_type' => 0,
                    'plugincode' => $content,
                    'locked' => 0,
                    'properties' => 'a:0:{}',
                    'disabled' => 0,
                    'static' => !empty($config['static']['plugins']) ? 1 : 0,
                    'static_file' => $this->resolveStaticFile($data),
                ]),
                'guid' => $this->generateGuid(),
                'native_key' => null,
                'signature' => $hash,
            ];
        }

        echo "    Plugins: " . count($plugins) . "\n";
    }

    private function processTemplatesForCategory(array $templates, array $config, array &$relatedObjects, array &$relatedObjectAttributes, string $corePath): void
    {
        $relatedObjectAttributes['Templates'] = [
            'preserve_keys' => false,
            'update_object' => $config['build']['update']['templates'] ?? true,
            'unique_key' => 'templatename',
        ];

        $relatedObjects['Templates'] = [];

        foreach ($templates as $name => $data) {
            $content = $this->resolveContent($data, $corePath);
            $hash = md5($name . $this->signature . 'template');

            $relatedObjects['Templates'][$hash] = [
                'preserve_keys' => false,
                'update_object' => $config['build']['update']['templates'] ?? true,
                'unique_key' => 'templatename',
                'class' => 'MODX\\Revolution\\modTemplate',
                'object' => json_encode([
                    'id' => null,
                    'source' => 0,
                    'property_preprocess' => 0,
                    'templatename' => $name,
                    'description' => $data['description'] ?? '',
                    'editor_type' => 0,
                    'category' => 0,
                    'icon' => '',
                    'template_type' => 0,
                    'content' => $content,
                    'locked' => 0,
                    'properties' => 'a:0:{}',
                    'static' => !empty($config['static']['templates']) ? 1 : 0,
                    'static_file' => $this->resolveStaticFile($data),
                ]),
                'guid' => $this->generateGuid(),
                'native_key' => null,
                'signature' => $hash,
            ];
        }

        echo "    Templates: " . count($templates) . "\n";
    }

    private function processTVsForCategory(array $tvs, array $config, array &$relatedObjects, array &$relatedObjectAttributes): void
    {
        $relatedObjectAttributes['TemplateVars'] = [
            'preserve_keys' => false,
            'update_object' => $config['build']['update']['tvs'] ?? true,
            'unique_key' => 'name',
        ];

        $relatedObjects['TemplateVars'] = [];

        foreach ($tvs as $name => $data) {
            $hash = md5($name . $this->signature . 'tv');

            $relatedObjects['TemplateVars'][$hash] = [
                'preserve_keys' => false,
                'update_object' => $config['build']['update']['tvs'] ?? true,
                'unique_key' => 'name',
                'class' => 'MODX\\Revolution\\modTemplateVar',
                'object' => json_encode([
                    'id' => null,
                    'source' => 0,
                    'property_preprocess' => 0,
                    'type' => $data['type'] ?? 'text',
                    'name' => $name,
                    'caption' => $data['caption'] ?? $name,
                    'description' => $data['description'] ?? '',
                    'editor_type' => 0,
                    'category' => 0,
                    'locked' => 0,
                    'elements' => $data['elements'] ?? '',
                    'rank' => 0,
                    'display' => 'default',
                    'default_text' => $data['default'] ?? '',
                    'properties' => 'a:0:{}',
                    'input_properties' => 'a:0:{}',
                    'output_properties' => 'a:0:{}',
                    'static' => 0,
                    'static_file' => '',
                ]),
                'guid' => $this->generateGuid(),
                'native_key' => null,
                'signature' => $hash,
            ];
        }

        echo "    TVs: " . count($tvs) . "\n";
    }

    private function resolveContent(array $data, string $corePath): string
    {
        if (!empty($data['content']) && str_starts_with($data['content'], 'file:')) {
            $file = $corePath . substr($data['content'], 5);
            return file_exists($file) ? file_get_contents($file) : '';
        }

        if (!empty($data['file'])) {
            $searchPaths = [
                $corePath . 'elements/snippets/' . $data['file'],
                $corePath . 'elements/plugins/' . $data['file'],
                $corePath . 'elements/chunks/' . $data['file'],
                $corePath . $data['file'],
            ];
            foreach ($searchPaths as $path) {
                if (file_exists($path)) {
                    return file_get_contents($path);
                }
            }
        }

        return $data['content'] ?? $data['snippet'] ?? $data['plugincode'] ?? '';
    }

    private function resolveStaticFile(array $data): string
    {
        if (!empty($data['content']) && str_starts_with($data['content'], 'file:')) {
            return substr($data['content'], 5);
        }
        return '';
    }

    private function prepareBuildSource(IgnoreFilter $filter, string $sourcePath, string $tmpName): string
    {
        $tmpDir = sys_get_temp_dir() . '/' . $tmpName . '_' . uniqid();
        $filter->copyFiltered($sourcePath, $tmpDir);
        return $tmpDir;
    }

    private function writeVehicle(string $dir, string $hash, array $vehicle): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php return " . var_export($vehicle, true) . ";\n";
        file_put_contents($dir . $hash . '.vehicle', $content);
    }

    private function generateGuid(): string
    {
        return sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $dirLen = strlen($dir);

        foreach ($iterator as $file) {
            $relativePath = $prefix . substr($file->getPathname(), $dirLen);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
    }

    private function recursiveCopy(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

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
