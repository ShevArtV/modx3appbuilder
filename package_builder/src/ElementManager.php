<?php

namespace ComponentBuilder;

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

class ElementManager
{
    public function __construct(
        private readonly modX $modx,
        private readonly array $packageConfig
    ) {
    }

    /**
     * @param string $elementsPath
     * @return void
     */
    public function processElements(string $elementsPath): void
    {
        if (!is_dir($elementsPath)) {
            echo "Elements directory not found: {$elementsPath}\n";
            return;
        }

        $this->processChunks($elementsPath . 'chunks.php');
        $this->processSnippets($elementsPath . 'snippets.php');
        $this->processPlugins($elementsPath . 'plugins.php');
        $this->processTemplates($elementsPath . 'templates.php');
        $this->processTVs($elementsPath . 'tvs.php');
        $this->processSettings($elementsPath . 'settings.php');
        $this->processMenus($elementsPath . 'menus.php');
        $this->processEvents($elementsPath . 'events.php');
    }

    /**
     * @param string $chunksFile
     * @return void
     */
    private function processChunks(string $chunksFile): void
    {
        $chunks = $this->loadFile($chunksFile);
        if (!$chunks) {
            return;
        }

        $categoryId = $this->getCategoryId();

        foreach ($chunks as $name => $data) {
            /** @var modChunk $chunk */
            $chunk = $this->modx->getObject(modChunk::class, ['name' => $name]);

            if (!$chunk) {
                $chunk = $this->modx->newObject(modChunk::class);
                $chunk->set('name', $name);
            }

            $chunk->fromArray([
                'description' => $data['description'] ?? '',
                'snippet' => $data['content'] ?? '',
                'static' => $data['static'] ?? false,
                'static_file' => $data['static_file'] ?? '',
                'category' => $categoryId,
            ]);

            if ($chunk->save()) {
                echo "Chunk '{$name}' saved\n";
            }
        }
    }

    /**
     * @param string $snippetsFile
     * @return void
     */
    private function processSnippets(string $snippetsFile): void
    {
        $snippets = $this->loadFile($snippetsFile);
        if (!$snippets) {
            return;
        }

        $categoryId = $this->getCategoryId();

        foreach ($snippets as $name => $data) {
            /** @var modSnippet $snippet */
            $snippet = $this->modx->getObject(modSnippet::class, ['name' => $name]);

            if (!$snippet) {
                $snippet = $this->modx->newObject(modSnippet::class);
                $snippet->set('name', $name);
            }

            $snippet->fromArray([
                'description' => $data['description'] ?? '',
                'snippet' => $data['content'] ?? '',
                'static' => $data['static'] ?? false,
                'static_file' => $data['static_file'] ?? '',
                'category' => $categoryId,
            ]);

            if (!empty($data['properties'])) {
                $properties = [];
                foreach ($data['properties'] as $k => $v) {
                    $properties[] = array_merge(['name' => $k], $v);
                }
                $snippet->setProperties($properties);
            }

            if ($snippet->save()) {
                echo "Snippet '{$name}' saved\n";
            }
        }
    }

    /**
     * @param string $pluginsFile
     * @return void
     */
    private function processPlugins(string $pluginsFile): void
    {
        $plugins = $this->loadFile($pluginsFile);
        if (!$plugins) {
            return;
        }

        $categoryId = $this->getCategoryId();

        foreach ($plugins as $name => $data) {
            /** @var modPlugin $plugin */
            $plugin = $this->modx->getObject(modPlugin::class, ['name' => $name]);

            if (!$plugin) {
                $plugin = $this->modx->newObject(modPlugin::class);
                $plugin->set('name', $name);
            }

            $plugin->fromArray([
                'description' => $data['description'] ?? '',
                'plugincode' => $data['content'] ?? '',
                'static' => $data['static'] ?? false,
                'static_file' => $data['static_file'] ?? '',
                'category' => $categoryId,
            ]);

            $plugin->save();

            if (!empty($data['events'])) {
                foreach ($data['events'] as $eventName => $eventData) {
                    if (is_numeric($eventName)) {
                        $eventName = $eventData;
                        $eventData = [];
                    }

                    $event = $this->modx->getObject(modPluginEvent::class, [
                        'pluginid' => $plugin->get('id'),
                        'event' => $eventName,
                    ]);

                    if (!$event) {
                        $event = $this->modx->newObject(modPluginEvent::class);
                        $event->set('pluginid', $plugin->get('id'));
                        $event->set('event', $eventName);
                        $event->set('priority', $eventData['priority'] ?? 0);
                        $event->save();
                    }
                }
            }

            echo "Plugin '{$name}' saved\n";
        }
    }

    /**
     * @param string $templatesFile
     * @return void
     */
    private function processTemplates(string $templatesFile): void
    {
        $templates = $this->loadFile($templatesFile);
        if (!$templates) {
            return;
        }

        $categoryId = $this->getCategoryId();

        foreach ($templates as $name => $data) {
            /** @var modTemplate $template */
            $template = $this->modx->getObject(modTemplate::class, ['templatename' => $name]);

            if (!$template) {
                $template = $this->modx->newObject(modTemplate::class);
                $template->set('templatename', $name);
            }

            $template->fromArray([
                'description' => $data['description'] ?? '',
                'content' => $data['content'] ?? '',
                'static' => $data['static'] ?? false,
                'static_file' => $data['static_file'] ?? '',
                'category' => $categoryId,
            ]);

            if ($template->save()) {
                echo "Template '{$name}' saved\n";
            }
        }
    }

    /**
     * @param string $tvsFile
     * @return void
     */
    private function processTVs(string $tvsFile): void
    {
        $tvs = $this->loadFile($tvsFile);
        if (!$tvs) {
            return;
        }

        $categoryId = $this->getCategoryId();

        foreach ($tvs as $name => $data) {
            /** @var modTemplateVar $tv */
            $tv = $this->modx->getObject(modTemplateVar::class, ['name' => $name]);

            if (!$tv) {
                $tv = $this->modx->newObject(modTemplateVar::class);
                $tv->set('name', $name);
            }

            $tv->fromArray([
                'caption' => $data['caption'] ?? $name,
                'description' => $data['description'] ?? '',
                'type' => $data['type'] ?? 'text',
                'default_text' => $data['default'] ?? '',
                'elements' => $data['elements'] ?? '',
                'category' => $categoryId,
            ]);

            if ($tv->save()) {
                echo "TV '{$name}' saved\n";
            }
        }
    }

    /**
     * @param string $settingsFile
     * @return void
     */
    private function processSettings(string $settingsFile): void
    {
        $settings = $this->loadFile($settingsFile);
        if (!$settings) {
            return;
        }

        foreach ($settings as $key => $data) {
            /** @var modSystemSetting $setting */
            $setting = $this->modx->getObject(modSystemSetting::class, ['key' => $key]);

            if (!$setting) {
                $setting = $this->modx->newObject(modSystemSetting::class);
                $setting->set('key', $key);
            }

            $setting->fromArray(array_merge([
                'namespace' => $this->packageConfig['name_lower'],
            ], $data));

            if ($setting->save()) {
                echo "Setting '{$key}' saved\n";
            }
        }
    }

    /**
     * @param string $menusFile
     * @return void
     */
    private function processMenus(string $menusFile): void
    {
        $menus = $this->loadFile($menusFile);
        if (!$menus) {
            return;
        }

        foreach ($menus as $text => $data) {
            /** @var modMenu $menu */
            $menu = $this->modx->getObject(modMenu::class, ['text' => $text]);

            if (!$menu) {
                $menu = $this->modx->newObject(modMenu::class);
                $menu->set('text', $text);
            }

            $menu->fromArray(array_merge([
                'parent' => 'components',
                'namespace' => $this->packageConfig['name_lower'],
                'icon' => '',
                'menuindex' => 0,
                'params' => '',
                'handler' => '',
            ], $data));

            if ($menu->save()) {
                echo "Menu '{$text}' saved\n";
            }
        }
    }

    /**
     * @param string $eventsFile
     * @return void
     */
    private function processEvents(string $eventsFile): void
    {
        $events = $this->loadFile($eventsFile);
        if (!$events) {
            return;
        }

        foreach ($events as $name) {
            /** @var modEvent $event */
            $event = $this->modx->getObject(modEvent::class, ['name' => $name]);

            if (!$event) {
                $event = $this->modx->newObject(modEvent::class);
                $event->fromArray([
                    'name' => $name,
                    'service' => 6,
                    'groupname' => $this->packageConfig['name'],
                ]);

                if ($event->save()) {
                    echo "Event '{$name}' saved\n";
                }
            }
        }
    }

    /**
     * @return int
     */
    private function getCategoryId(): int
    {
        $categoryName = $this->packageConfig['elements']['category']
            ?? $this->packageConfig['name'];

        /** @var modCategory $category */
        $category = $this->modx->getObject(modCategory::class, ['category' => $categoryName]);

        if (!$category) {
            $category = $this->modx->newObject(modCategory::class);
            $category->set('category', $categoryName);
            $category->save();
        }

        return $category->get('id');
    }

    /**
     * @param string $filePath
     * @return array|null
     */
    private function loadFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $data = include $filePath;

        return is_array($data) ? $data : null;
    }
}
