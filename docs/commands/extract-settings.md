# extract-settings — Извлечение настроек

Автоматически находит все вызовы `$modx->getOption()` в PHP-коде и генерирует файл системных настроек.

## Использование

```bash
modxapp extract-settings <name>
```

## Пример

```bash
modxapp extract-settings mypackage
```

## Что делает

1. Сканирует все PHP-файлы в `core/components/<name>/src/`
2. Находит вызовы `$modx->getOption('prefix_setting_name')`
3. Автоматически определяет тип настройки
4. Генерирует `core/components/<name>/elements/settings.php`

## Автоопределение типов

Экстрактор анализирует контекст использования настройки:

| Контекст | Тип |
|----------|-----|
| В условии `if/foreach/while` | `boolean` |
| `intval()`, `floatval()`, `number_format()` | `number` |
| `array`, `explode()`, `json_decode()` | `array` |
| Всё остальное | `string` |

## Автокатегоризация

Настройки автоматически распределяются по областям (area):

| Паттерн имени | Область |
|---------------|---------|
| `*_path`, `*_dir` | `paths` |
| `*_email` | `email` |
| `*_cache`, `*_debug` | `system` |
| Все остальные | `general` |

## Результат

Из кода:

```php
$timeout = $modx->getOption('mypackage_api_timeout');
$debug = $modx->getOption('mypackage_debug');
```

Будет сгенерирован:

```php
<?php
return [
    'mypackage_api_timeout' => [
        'key' => 'mypackage_api_timeout',
        'value' => '',
        'xtype' => 'textfield',
        'namespace' => 'mypackage',
        'area' => 'general',
    ],
    'mypackage_debug' => [
        'key' => 'mypackage_debug',
        'value' => '',
        'xtype' => 'combo-boolean',
        'namespace' => 'mypackage',
        'area' => 'system',
    ],
];
```
