# Конфигурация

Package Builder использует три уровня конфигурации:

| Уровень | Файл | Создаётся | Назначение |
|---------|------|-----------|------------|
| Глобальный | `user.config.json` (внутри пакета) | `modxapp config` | Данные автора, настройки по умолчанию для всех проектов |
| Локальный | `modxapp.json` (корень проекта) | `modxapp init` или автоматически при `create` | Настройки текущего проекта |
| Пакет | `package_builder/packages/<name>/config.php` | `modxapp create` | Конфигурация сборки конкретного пакета |

## Глобальный конфиг

Настраивается один раз командой [`modxapp config`](commands/config.md). Содержит данные автора, путь к шаблонам, выбор инструментов. Используется как основа при создании локального конфига.

## Локальный конфиг (modxapp.json)

Создаётся командой [`modxapp init`](commands/init.md) или автоматически при первом `modxapp create`. Значения берутся из глобального конфига. Можно закоммитить в репозиторий, чтобы все участники команды использовали одинаковые настройки.

## Конфигурация пакета (config.php)

Каждый пакет имеет свой файл конфигурации сборки — `package_builder/packages/<name>/config.php`. Создаётся автоматически при `modxapp create`.

## Полный пример config.php

```php
<?php
return [
    'name' => 'MyPackage',
    'name_lower' => 'mypackage',
    'name_short' => 'my',
    'version' => '1.0.0',
    'release' => 'alpha',

    'paths' => [
        'core' => 'core/components/mypackage/',
        'assets' => 'assets/components/mypackage/',
    ],

    'schema' => [
        'file' => 'schema/mypackage.mysql.schema.xml',
        'auto_generate_classes' => true,
        'update_tables' => true,
    ],

    'elements' => [
        'category' => 'MyPackage',
        'chunks' => 'elements/chunks.php',
        'snippets' => 'elements/snippets.php',
        'plugins' => 'elements/plugins.php',
        'templates' => 'elements/templates.php',
        'tvs' => 'elements/tvs.php',
        'settings' => 'elements/settings.php',
        'menus' => 'elements/menus.php',
    ],

    'static' => [
        'chunks' => true,
        'snippets' => true,
        'templates' => true,
    ],

    'tools' => [
        'phpCsFixer' => false,
        'eslint' => false,
    ],

    'build' => [
        'download' => false,
        'install' => false,
        'update' => [
            'chunks' => true,
            'snippets' => true,
            'settings' => false,
        ],
    ],
];
```

## Описание секций

### Основные поля

| Поле | Описание |
|------|----------|
| `name` | CamelCase имя компонента |
| `name_lower` | Имя в lowercase |
| `name_short` | Краткое имя (для префиксов лексиконов и настроек) |
| `version` | Семантическая версия (`1.0.0`) |
| `release` | Тип релиза: `alpha`, `beta`, `pl` (production) |

### paths

Пути к файлам компонента относительно корня MODX:

```php
'paths' => [
    'core' => 'core/components/mypackage/',
    'assets' => 'assets/components/mypackage/',
],
```

### schema

Настройки работы с XML-схемой БД:

| Поле | Описание | По умолчанию |
|------|----------|-------------|
| `file` | Путь к файлу схемы | `schema/<name>.mysql.schema.xml` |
| `auto_generate_classes` | Генерировать PHP-классы | `true` |
| `update_tables` | Создавать/обновлять таблицы | `false` |

### elements

Описание файлов элементов. Каждый ключ указывает на PHP-файл, возвращающий массив элементов:

```php
'elements' => [
    'category' => 'MyPackage',           // категория в админке
    'chunks' => 'elements/chunks.php',
    'snippets' => 'elements/snippets.php',
    'plugins' => 'elements/plugins.php',
    'templates' => 'elements/templates.php',
    'tvs' => 'elements/tvs.php',
    'settings' => 'elements/settings.php',
    'menus' => 'elements/menus.php',
    'events' => 'elements/events.php',
    'policies' => 'elements/policies.php',
    'policyTemplates' => 'elements/policyTemplates.php',
],
```

### static

Какие элементы хранить как статические файлы:

```php
'static' => [
    'chunks' => true,       // чанки из файлов
    'snippets' => true,     // сниппеты из файлов
    'templates' => true,    // шаблоны из файлов
],
```

### tools

Инструменты разработки (влияют на создание пакета через `create`):

```php
'tools' => [
    'phpCsFixer' => false,  // PHP CS Fixer (PSR-12)
    'eslint' => false,      // ESLint для JS
    'toolsConfigPath' => '', // путь к своим конфигам инструментов
],
```

### build

Настройки сборки:

| Поле | Описание |
|------|----------|
| `download` | Предлагать скачать после сборки |
| `install` | Устанавливать после сборки |
| `update` | Какие элементы обновлять при переустановке |

```php
'build' => [
    'download' => false,
    'install' => false,
    'update' => [
        'chunks' => true,      // обновлять чанки
        'snippets' => true,    // обновлять сниппеты
        'settings' => false,   // НЕ обновлять настройки (сохранить значения)
    ],
],
```

!!! warning "update → settings"
    Рекомендуется оставить `settings: false`, чтобы при обновлении пакета не перезатирать настройки, которые пользователь уже изменил.
