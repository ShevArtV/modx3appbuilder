<?php
/**
 * @file ElementsManager.php
 */

namespace ComponentBuilder;

use MODX\Revolution\modAccessPermission;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modEvent;
use MODX\Revolution\modMenu;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modTemplate;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOTransport;

class ElementsManager
{
    private array $categoryAttributes;

    /**
     * @param modX $modx
     * @param modPackageBuilder $builder
     * @param modCategory $category
     * @param array $categoryAttributes
     * @param array $config
     */
    public function __construct(
        private readonly modX $modx,
        private readonly modPackageBuilder $builder,
        private modCategory $category,
        array $categoryAttributes,
        private readonly array $config
    ) {
        $this->categoryAttributes = $categoryAttributes;
    }

    /**
     * @return array
     */
    public function getCategoryAttributes(): array
    {
        return $this->categoryAttributes;
    }

    /**
     * @param array $elementsConfig
     * @return void
     */
    public function processElements(array $elementsConfig): void
    {
        $typeMap = [
            'chunks' => 'processChunks',
            'snippets' => 'processSnippets',
            'plugins' => 'processPlugins',
            'templates' => 'processTemplates',
            'tvs' => 'processTVs',
            'settings' => 'processSettings',
            'menus' => 'processMenus',
            'events' => 'processEvents',
            'policies' => 'processPolicies',
            'policyTemplates' => 'processPolicyTemplates',
        ];

        foreach ($typeMap as $type => $method) {
            if (!isset($elementsConfig[$type])) {
                continue;
            }

            $elements = $this->loadElementsFile($elementsConfig[$type]);
            if ($elements === null) {
                continue;
            }

            $this->$method($elements);
        }
    }

    /**
     * @param string $relativePath
     * @return array|null
     */
    private function loadElementsFile(string $relativePath): ?array
    {
        $basePath = dirname(__DIR__) . '/packages/' . $this->config['name_lower'] . '/';
        $filePath = $basePath . $relativePath;

        if (!file_exists($filePath)) {
            $this->modx->log(modX::LOG_LEVEL_WARN, "Elements file not found: {$filePath}");
            return null;
        }

        $elements = include $filePath;

        if (!is_array($elements)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Invalid elements file: {$filePath}");
            return null;
        }

        return $elements;
    }

    /**
     * @param array $chunks
     * @return void
     */
    private function processChunks(array $chunks): void
    {
        $this->categoryAttributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Chunks'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['chunks'] ?? true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ];

        $objects = [];
        foreach ($chunks as $name => $data) {
            $chunk = $this->modx->newObject(modChunk::class);
            $chunk->fromArray([
                'id' => 0,
                'name' => $name,
                'description' => $data['description'] ?? '',
                'snippet' => $this->resolveContent($data),
                'static' => !empty($this->config['static']['chunks']),
                'source' => 1,
                'static_file' => $this->resolveStaticFile($data),
            ], '', true, true);
            $objects[] = $chunk;
        }

        $this->category->addMany($objects, 'Chunks');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Chunks');
    }

    /**
     * @param array $snippets
     * @return void
     */
    private function processSnippets(array $snippets): void
    {
        $this->categoryAttributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Snippets'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['snippets'] ?? true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ];

        $objects = [];
        foreach ($snippets as $name => $data) {
            $snippet = $this->modx->newObject(modSnippet::class);
            $snippet->fromArray([
                'id' => 0,
                'name' => $name,
                'description' => $data['description'] ?? '',
                'snippet' => $this->resolveContent($data),
                'static' => !empty($this->config['static']['snippets']),
                'source' => 1,
                'static_file' => $this->resolveStaticFile($data),
            ], '', true, true);

            if (!empty($data['properties'])) {
                $properties = [];
                foreach ($data['properties'] as $k => $v) {
                    $properties[] = array_merge(['name' => $k], $v);
                }
                $snippet->setProperties($properties);
            }

            $objects[] = $snippet;
        }

        $this->category->addMany($objects, 'Snippets');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Snippets');
    }

    /**
     * @param array $plugins
     * @return void
     */
    private function processPlugins(array $plugins): void
    {
        $this->categoryAttributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Plugins'] = [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['plugins'] ?? true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                ],
            ],
        ];

        $objects = [];
        foreach ($plugins as $name => $data) {
            $plugin = $this->modx->newObject(modPlugin::class);
            $plugin->fromArray([
                'id' => 0,
                'name' => $name,
                'description' => $data['description'] ?? '',
                'plugincode' => $this->resolveContent($data),
                'static' => !empty($this->config['static']['plugins']),
                'source' => 1,
                'static_file' => $this->resolveStaticFile($data),
            ], '', true, true);

            if (!empty($data['events'])) {
                $events = [];
                foreach ($data['events'] as $eventName => $eventData) {
                    if (is_numeric($eventName)) {
                        $eventName = $eventData;
                        $eventData = [];
                    }
                    $event = $this->modx->newObject(modPluginEvent::class);
                    $event->fromArray([
                        'event' => $eventName,
                        'priority' => $eventData['priority'] ?? 0,
                        'propertyset' => $eventData['propertyset'] ?? 0,
                    ], '', true, true);
                    $events[] = $event;
                }
                $plugin->addMany($events);
            }

            $objects[] = $plugin;
        }

        $this->category->addMany($objects, 'Plugins');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Plugins');
    }

    /**
     * @param array $templates
     * @return void
     */
    private function processTemplates(array $templates): void
    {
        $this->categoryAttributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Templates'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['templates'] ?? true,
            xPDOTransport::UNIQUE_KEY => 'templatename',
        ];

        $objects = [];
        foreach ($templates as $name => $data) {
            $template = $this->modx->newObject(modTemplate::class);
            $template->fromArray([
                'id' => 0,
                'templatename' => $name,
                'description' => $data['description'] ?? '',
                'content' => $this->resolveContent($data),
                'static' => !empty($this->config['static']['templates']),
                'source' => 1,
                'static_file' => $this->resolveStaticFile($data),
            ], '', true, true);
            $objects[] = $template;
        }

        $this->category->addMany($objects, 'Templates');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Templates');
    }

    /**
     * @param array $tvs
     * @return void
     */
    private function processTVs(array $tvs): void
    {
        $this->categoryAttributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['TemplateVars'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['tvs'] ?? true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ];

        $objects = [];
        foreach ($tvs as $name => $data) {
            $tv = $this->modx->newObject(modTemplateVar::class);
            $tv->fromArray([
                'id' => 0,
                'name' => $name,
                'caption' => $data['caption'] ?? $name,
                'description' => $data['description'] ?? '',
                'type' => $data['type'] ?? 'text',
                'default_text' => $data['default'] ?? '',
                'elements' => $data['elements'] ?? '',
            ], '', true, true);
            $objects[] = $tv;
        }

        $this->category->addMany($objects, 'TemplateVars');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' TVs');
    }

    /**
     * @param array $settings
     * @return void
     */
    private function processSettings(array $settings): void
    {
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['settings'] ?? false,
            xPDOTransport::RELATED_OBJECTS => false,
        ];

        foreach ($settings as $key => $data) {
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->fromArray(array_merge([
                'key' => $key,
                'namespace' => $this->config['name_lower'],
            ], $data), '', true, true);

            $vehicle = $this->builder->createVehicle($setting, $attributes);
            $this->builder->putVehicle($vehicle);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings');
    }

    /**
     * @param array $menus
     * @return void
     */
    private function processMenus(array $menus): void
    {
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['menus'] ?? true,
            xPDOTransport::UNIQUE_KEY => 'text',
            xPDOTransport::RELATED_OBJECTS => false,
        ];

        foreach ($menus as $text => $data) {
            $menu = $this->modx->newObject(modMenu::class);
            $menu->fromArray(array_merge([
                'text' => $text,
                'parent' => 'components',
                'namespace' => $this->config['name_lower'],
                'icon' => '',
                'menuindex' => 0,
                'params' => '',
                'handler' => '',
            ], $data), '', true, true);

            $vehicle = $this->builder->createVehicle($menu, $attributes);
            $this->builder->putVehicle($vehicle);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($menus) . ' Menus');
    }

    /**
     * @param array $events
     * @return void
     */
    private function processEvents(array $events): void
    {
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['events'] ?? true,
        ];

        foreach ($events as $name) {
            $event = $this->modx->newObject(modEvent::class);
            $event->fromArray([
                'name' => $name,
                'service' => 6,
                'groupname' => $this->config['name'],
            ], '', true, true);

            $vehicle = $this->builder->createVehicle($event, $attributes);
            $this->builder->putVehicle($vehicle);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($events) . ' Events');
    }

    /**
     * @param array $policies
     * @return void
     */
    private function processPolicies(array $policies): void
    {
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => ['name'],
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['policies'] ?? false,
        ];

        foreach ($policies as $name => $data) {
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data'] = json_encode($data['data']);
            }

            $policy = $this->modx->newObject(modAccessPolicy::class);
            $policy->fromArray(array_merge([
                'name' => $name,
                'lexicon' => $this->config['name_lower'] . ':permissions',
            ], $data), '', true, true);

            $vehicle = $this->builder->createVehicle($policy, $attributes);
            $this->builder->putVehicle($vehicle);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policies) . ' Access Policies');
    }

    /**
     * @param array $templates
     * @return void
     */
    private function processPolicyTemplates(array $templates): void
    {
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => ['name'],
            xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['policy_templates'] ?? false,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'Permissions' => [
                    xPDOTransport::PRESERVE_KEYS => false,
                    xPDOTransport::UPDATE_OBJECT => $this->config['build']['update']['permissions'] ?? false,
                    xPDOTransport::UNIQUE_KEY => ['template', 'name'],
                ],
            ],
        ];

        foreach ($templates as $name => $data) {
            $permissions = [];
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $permName => $permData) {
                    $permission = $this->modx->newObject(modAccessPermission::class);
                    $permission->fromArray(array_merge([
                        'name' => $permName,
                        'description' => $permName,
                        'value' => true,
                    ], $permData), '', true, true);
                    $permissions[] = $permission;
                }
            }

            $template = $this->modx->newObject(modAccessPolicyTemplate::class);
            $template->fromArray(array_merge([
                'name' => $name,
                'lexicon' => $this->config['name_lower'] . ':permissions',
            ], $data), '', true, true);

            if (!empty($permissions)) {
                $template->addMany($permissions);
            }

            $vehicle = $this->builder->createVehicle($template, $attributes);
            $this->builder->putVehicle($vehicle);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($templates) . ' Policy Templates');
    }

    /**
     * @param array $data
     * @return string
     */
    private function resolveContent(array $data): string
    {
        $content = $data['content'] ?? '';

        if (str_starts_with($content, 'file:')) {
            $filePath = $this->config['abs_core'] . substr($content, 5);
            if (!file_exists($filePath)) {
                $this->modx->log(modX::LOG_LEVEL_WARN, "Element file not found: {$filePath}");
                return '';
            }

            $raw = file_get_contents($filePath);
            if ($raw === false) {
                return '';
            }

            $raw = trim($raw);

            if (preg_match('#\<\?php(.*)#is', $raw, $matches)) {
                return rtrim(rtrim(trim($matches[1] ?? ''), '?>'));
            }

            return $raw;
        }

        return $content;
    }

    /**
     * @param array $data
     * @return string
     */
    private function resolveStaticFile(array $data): string
    {
        $content = $data['content'] ?? '';

        if (str_starts_with($content, 'file:')) {
            return 'core/components/' . $this->config['name_lower'] . '/' . substr($content, 5);
        }

        return '';
    }
}
