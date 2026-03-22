# elements — Добавление элементов в MODX

Создаёт и обновляет элементы MODX в базе данных из файлов описания. Используется при разработке, чтобы не добавлять элементы вручную через админку.

## Использование

```bash
modxapp elements <name>
```

## Пример

```bash
modxapp elements mypackage
```

## Поддерживаемые типы

Команда сканирует папку `elements/` и обрабатывает файлы:

| Файл | Элемент MODX |
|------|-------------|
| `chunks.php` | Чанки |
| `snippets.php` | Сниппеты |
| `plugins.php` | Плагины + события |
| `templates.php` | Шаблоны |
| `tvs.php` | TV-параметры |
| `settings.php` | Системные настройки |
| `menus.php` | Пункты меню админки |
| `events.php` | Кастомные события |

## Формат файлов элементов

### Чанки

```php
// elements/chunks.php
return [
    [
        'name' => 'myChunk',
        'description' => 'My chunk',
        'file' => 'elements/chunks/mychunk.chunk.tpl',
    ],
];
```

### Сниппеты

```php
// elements/snippets.php
return [
    [
        'name' => 'mySnippet',
        'description' => 'My snippet',
        'file' => 'elements/snippets/mysnippet.snippet.php',
    ],
];
```

### Плагины

```php
// elements/plugins.php
return [
    [
        'name' => 'myPlugin',
        'description' => 'My plugin',
        'file' => 'elements/plugins/switch.php',
        'events' => [
            'OnPageNotFound' => [],
            'OnHandleRequest' => ['priority' => 0],
        ],
    ],
];
```

### Системные настройки

```php
// elements/settings.php
return [
    [
        'key' => 'mypackage_api_key',
        'value' => '',
        'xtype' => 'textfield',
        'namespace' => 'mypackage',
        'area' => 'general',
    ],
];
```

### Меню

```php
// elements/menus.php
return [
    [
        'text' => 'mypackage',
        'description' => 'mypackage_desc',
        'parent' => 'components',
        'action' => 'home',
        'namespace' => 'mypackage',
    ],
];
```

## Поддерживаемые элементы

| Элемент | Описание |
|---------|----------|
| Chunks | Компоненты шаблонов |
| Snippets | PHP-сниппеты |
| Plugins | Плагины с привязкой к событиям |
| Templates | Шаблоны страниц |
| TV | Дополнительные поля |
| Settings | Системные настройки |
| Menus | Пункты меню админки |
| Events | Кастомные события |
| Policies | Политики доступа |
| Policy Templates | Шаблоны политик |

## Логика работы

Для каждого элемента:

1. Ищет существующий элемент по имени/ключу
2. Если не найден — создаёт новый
3. Если найден — обновляет поля
