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

class ExportManager
{
    private ?int $categoryId = null;

    public function __construct(
        private readonly modX $modx,
        private readonly array $config
    ) {
    }

    public function export(string $elementsPath, string $corePath): array
    {
        $this->resolveCategoryId();

        $exported = [];

        $exported['chunks'] = $this->exportChunks($elementsPath, $corePath);
        $exported['snippets'] = $this->exportSnippets($elementsPath, $corePath);
        $exported['plugins'] = $this->exportPlugins($elementsPath, $corePath);
        $exported['templates'] = $this->exportTemplates($elementsPath, $corePath);
        $exported['tvs'] = $this->exportTVs($elementsPath);
        $exported['settings'] = $this->exportSettings($elementsPath);
        $exported['menus'] = $this->exportMenus($elementsPath);
        $exported['events'] = $this->exportEvents($elementsPath);

        return $exported;
    }

    private function resolveCategoryId(): void
    {
        $categoryName = $this->config['elements']['category'] ?? $this->config['name'] ?? '';

        if (empty($categoryName)) {
            return;
        }

        $category = $this->modx->getObject(modCategory::class, ['category' => $categoryName]);

        if ($category) {
            $this->categoryId = $category->get('id');
        }
    }

    private function exportChunks(string $elementsPath, string $corePath): int
    {
        if ($this->categoryId === null) {
            return 0;
        }

        $items = [];
        $contentDir = $corePath . 'elements/chunks/';

        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0755, true);
        }

        foreach ($this->modx->getIterator(modChunk::class, ['category' => $this->categoryId]) as $chunk) {
            $name = $chunk->get('name');
            $fileName = $this->toFileName($name) . '.tpl';

            file_put_contents($contentDir . $fileName, $chunk->get('snippet'));

            $items[$name] = [
                'description' => $chunk->get('description') ?: '',
                'content' => 'file:elements/chunks/' . $fileName,
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/chunks.php', $items);
        }

        return count($items);
    }

    private function exportSnippets(string $elementsPath, string $corePath): int
    {
        if ($this->categoryId === null) {
            return 0;
        }

        $items = [];
        $contentDir = $corePath . 'elements/snippets/';

        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0755, true);
        }

        foreach ($this->modx->getIterator(modSnippet::class, ['category' => $this->categoryId]) as $snippet) {
            $name = $snippet->get('name');
            $fileName = $this->toFileName($name) . '.php';

            file_put_contents($contentDir . $fileName, $snippet->get('snippet'));

            $item = [
                'file' => $fileName,
                'description' => $snippet->get('description') ?: '',
            ];

            $props = $snippet->get('properties');
            if (!empty($props)) {
                $item['properties'] = $props;
            }

            $items[$name] = $item;
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/snippets.php', $items);
        }

        return count($items);
    }

    private function exportPlugins(string $elementsPath, string $corePath): int
    {
        if ($this->categoryId === null) {
            return 0;
        }

        $items = [];
        $contentDir = $corePath . 'elements/plugins/';

        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0755, true);
        }

        foreach ($this->modx->getIterator(modPlugin::class, ['category' => $this->categoryId]) as $plugin) {
            $name = $plugin->get('name');
            $fileName = $this->toFileName($name) . '.php';

            file_put_contents($contentDir . $fileName, $plugin->get('plugincode'));

            $events = [];
            foreach ($this->modx->getIterator(modPluginEvent::class, ['pluginid' => $plugin->get('id')]) as $event) {
                $eventName = $event->get('event');
                $priority = $event->get('priority');

                if ($priority > 0) {
                    $events[$eventName] = ['priority' => $priority];
                } else {
                    $events[] = $eventName;
                }
            }

            $items[$name] = [
                'file' => $fileName,
                'description' => $plugin->get('description') ?: '',
                'events' => $events,
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/plugins.php', $items);
        }

        return count($items);
    }

    private function exportTemplates(string $elementsPath, string $corePath): int
    {
        if ($this->categoryId === null) {
            return 0;
        }

        $items = [];
        $contentDir = $corePath . 'elements/templates/';

        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0755, true);
        }

        foreach ($this->modx->getIterator(modTemplate::class, ['category' => $this->categoryId]) as $template) {
            $name = $template->get('templatename');
            $fileName = $this->toFileName($name) . '.tpl';

            file_put_contents($contentDir . $fileName, $template->get('content'));

            $items[$name] = [
                'description' => $template->get('description') ?: '',
                'content' => 'file:elements/templates/' . $fileName,
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/templates.php', $items);
        }

        return count($items);
    }

    private function exportTVs(string $elementsPath): int
    {
        if ($this->categoryId === null) {
            return 0;
        }

        $items = [];

        foreach ($this->modx->getIterator(modTemplateVar::class, ['category' => $this->categoryId]) as $tv) {
            $items[$tv->get('name')] = [
                'caption' => $tv->get('caption') ?: '',
                'description' => $tv->get('description') ?: '',
                'type' => $tv->get('type') ?: 'text',
                'default' => $tv->get('default_text') ?: '',
                'elements' => $tv->get('elements') ?: '',
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/tvs.php', $items);
        }

        return count($items);
    }

    private function exportSettings(string $elementsPath): int
    {
        $namespace = $this->config['name_lower'] ?? '';

        if (empty($namespace)) {
            return 0;
        }

        $items = [];

        foreach ($this->modx->getIterator(modSystemSetting::class, ['namespace' => $namespace]) as $setting) {
            $items[$setting->get('key')] = [
                'value' => $setting->get('value') ?: '',
                'xtype' => $setting->get('xtype') ?: 'textfield',
                'area' => $setting->get('area') ?: '',
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/settings.php', $items);
        }

        return count($items);
    }

    private function exportMenus(string $elementsPath): int
    {
        $namespace = $this->config['name_lower'] ?? '';

        if (empty($namespace)) {
            return 0;
        }

        $items = [];

        foreach ($this->modx->getIterator(modMenu::class, ['namespace' => $namespace]) as $menu) {
            $items[$menu->get('text')] = [
                'description' => $menu->get('description') ?: '',
                'action' => $menu->get('action') ?: '',
                'parent' => $menu->get('parent') ?: 'components',
                'icon' => $menu->get('icon') ?: '',
                'menuindex' => $menu->get('menuindex') ?: 0,
                'params' => $menu->get('params') ?: '',
                'handler' => $menu->get('handler') ?: '',
            ];
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/menus.php', $items);
        }

        return count($items);
    }

    private function exportEvents(string $elementsPath): int
    {
        $namespace = $this->config['name_lower'] ?? '';
        $name = $this->config['name'] ?? '';

        if (empty($name)) {
            return 0;
        }

        $items = [];
        $criteria = ['name:LIKE' => "%{$name}%"];

        foreach ($this->modx->getIterator(modEvent::class, $criteria) as $event) {
            $service = $event->get('service');
            if ($service === 6) {
                $items[] = $event->get('name');
            }
        }

        if (!empty($items)) {
            $this->writeElementsFile($elementsPath . '/events.php', $items);
        }

        return count($items);
    }

    private function toFileName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name));
    }

    private function writeElementsFile(string $path, array $items): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\nreturn " . $this->arrayExport($items) . ";\n";
        file_put_contents($path, $content);
    }

    private function arrayExport(array $array, int $indent = 0): string
    {
        $isSequential = array_keys($array) === range(0, count($array) - 1);
        $pad = str_repeat('    ', $indent + 1);
        $padEnd = str_repeat('    ', $indent);
        $lines = [];

        foreach ($array as $key => $value) {
            $keyStr = $isSequential ? '' : var_export($key, true) . ' => ';

            if (is_array($value)) {
                $lines[] = $pad . $keyStr . $this->arrayExport($value, $indent + 1) . ',';
            } elseif (is_bool($value)) {
                $lines[] = $pad . $keyStr . ($value ? 'true' : 'false') . ',';
            } elseif (is_int($value)) {
                $lines[] = $pad . $keyStr . $value . ',';
            } else {
                $lines[] = $pad . $keyStr . var_export($value, true) . ',';
            }
        }

        return "[\n" . implode("\n", $lines) . "\n" . $padEnd . ']';
    }
}
