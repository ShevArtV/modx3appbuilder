# build — Сборка пакета

Собирает transport.zip пакет из исходников компонента.

## Использование

```bash
modxapp build <name> [options]
```

## Опции

| Опция | Описание |
|-------|----------|
| `--install` | Установить пакет после сборки |
| `--download` | Скачать transport.zip (только web-режим) |
| `--encrypt` | Зашифровать пакет через modstore.pro API |
| `--no-check` | Пропустить проверки PHPStan/CS Fixer/ESLint |
| `--verbose` | Подробный вывод |

## Примеры

```bash
# Собрать пакет
modxapp build mypackage

# Собрать и установить
modxapp build mypackage --install

# Собрать с шифрованием
modxapp build mypackage --encrypt

# Через web
# http://site.ru/build_web.php?package=mypackage&install&download
```

## Проверки перед сборкой

Перед сборкой автоматически запускаются проверки качества кода (если инструменты установлены):

1. **PHPStan** — статический анализ
2. **PHP CS Fixer** — проверка или автоисправление стиля (режим настраивается в `config.php`)
3. **ESLint** — проверка JavaScript

Если проверка не прошла — сборка прерывается. Используйте `--no-check` для пропуска.

Подробнее: [Инструменты разработки](../tools.md#автоматические-проверки-при-сборке)

## Процесс сборки

1. Загружает конфигурацию из `packages/<name>/config.php`
2. Создаёт `modPackageBuilder`
3. Регистрирует namespace компонента
4. Создаёт категорию элементов
5. Обрабатывает элементы (chunks, snippets, plugins, templates, TV, settings, menus, events, policies)
6. Копирует файлы `core/` и `assets/` с фильтрацией по `.packignore`
7. Добавляет резолверы
8. Упаковывает в `core/packages/<name>-<version>-<release>.transport.zip`
9. Устанавливает или предлагает скачать (если указаны флаги)

## Обработка элементов

Элементы описываются в файлах `packages/<name>/elements/`:

```php
// elements/snippets.php
return [
    [
        'name' => 'mySnippet',
        'description' => 'My snippet description',
        'file' => 'elements/snippets/mysnippet.snippet.php',
    ],
];
```

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

## Фильтрация файлов

При копировании файлов в transport-пакет применяется фильтрация через [.packignore](../packignore.md). Технические файлы (PHPStan, ESLint, node_modules и т.д.) исключаются автоматически.

## Web-режим

Для серверов без CLI PHP 8 доступна web-сборка:

```
build_web.php?package=mypackage
build_web.php?package=mypackage&install
build_web.php?package=mypackage&download
build_web.php?package=mypackage&encrypt
```
