# Быстрый старт

Пошаговое руководство: от установки до готового transport.zip за 5 минут. Используем headless-режим — без MODX, без MySQL, без сервера.

## 1. Установка

```bash
composer global require shevartv/modx-builder
```

!!! note "PATH"
    Убедитесь, что путь к глобальным Composer-пакетам добавлен в `PATH`. Узнать путь:
    ```bash
    composer global config bin-dir --absolute
    ```

    === "Linux"

        Добавьте в `~/.bashrc`:
        ```bash
        export PATH="$HOME/.config/composer/vendor/bin:$PATH"
        ```

    === "macOS"

        Добавьте в `~/.zshrc`:
        ```bash
        export PATH="$HOME/.composer/vendor/bin:$PATH"
        ```

    === "Windows"

        Добавьте в системную переменную `PATH`:
        ```
        %USERPROFILE%\AppData\Roaming\Composer\vendor\bin
        ```

Также можно установить локально: `composer require shevartv/modx-builder --dev` — тогда команда вызывается как `vendor/bin/modxapp`.

## 2. Настройка окружения

```bash
mkdir my-package && cd my-package
modxapp setup
```

Команда скачает ядро MODX 3 и настроит окружение для headless-сборки. Это делается один раз для проекта.

Если у вас уже установлен MODX — этот шаг не нужен, Package Builder найдёт его автоматически.

## 3. Глобальные настройки

```bash
modxapp config
```

Введите данные автора (имя, email, git-логин). Они сохранятся и будут использоваться при создании всех пакетов.

## 4. Создание пакета

```bash
modxapp create my-package --elements
```

Будет создана стандартная структура компонента MODX 3:

```
core/components/my-package/
├── bootstrap.php              — точка входа
├── composer.json              — зависимости и скрипты
├── phpstan.neon               — конфигурация PHPStan
├── docs/                      — readme, license, changelog
├── lexicon/                   — файлы переводов
├── schema/                    — XML-схема БД
├── src/                       — PHP-классы
│   ├── MyPackage.php          — главный класс
│   └── Plugins/IPlugin.php    — интерфейс плагинов
└── elements/                  — файлы элементов
    ├── plugins/switch.php     — единая точка входа плагинов
    └── snippets/connector.php

package_builder/packages/my-package/
├── config.php                 — конфигурация сборки
└── elements/                  — описание элементов для сборки
    ├── snippets.php
    └── plugins.php
```

## 5. Описание элементов

Элементы описываются в PHP-файлах в `package_builder/packages/<name>/elements/`. Создайте нужные файлы:

### Чанки

```php
// package_builder/packages/my-package/elements/chunks.php
<?php
return [
    'tpl.my-package.hello' => [
        'description' => 'Приветственный чанк',
        'content' => 'file:elements/chunks/hello.tpl',
    ],
];
```

Контент чанка — в отдельном файле:

```html
<!-- core/components/my-package/elements/chunks/hello.tpl -->
<p>Привет, [[+name]]!</p>
```

### Сниппеты

```php
// package_builder/packages/my-package/elements/snippets.php
<?php
return [
    'mySnippet' => [
        'file' => 'mysnippet.php',
        'description' => 'Мой сниппет',
    ],
];
```

Код сниппета:

```php
// core/components/my-package/elements/snippets/mysnippet.php
<?php
return 'Hello from my snippet!';
```

### Плагины

```php
// package_builder/packages/my-package/elements/plugins.php
<?php
return [
    'myPlugin' => [
        'file' => 'myplugin.php',
        'description' => 'Мой плагин',
        'events' => [
            'OnPageNotFound' => [],
            'OnLoadWebDocument' => ['priority' => 0],
        ],
    ],
];
```

### Системные настройки

```php
// package_builder/packages/my-package/elements/settings.php
<?php
return [
    'my_api_key' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'general',
    ],
    'my_debug' => [
        'value' => '0',
        'xtype' => 'combo-boolean',
        'area' => 'system',
    ],
];
```

### Меню

```php
// package_builder/packages/my-package/elements/menus.php
<?php
return [
    'my-package' => [
        'description' => 'my_menu_desc',
        'action' => 'home',
        'parent' => 'components',
    ],
];
```

Все поддерживаемые типы элементов: [Команда elements](commands/elements.md)

## 6. Извлечение данных из кода

Если в PHP-коде вашего компонента есть вызовы лексиконов и настроек — Package Builder найдёт их автоматически:

```bash
# Найти все $modx->lexicon('key') и собрать ключи
modxapp extract-lexicons my-package

# Найти все $modx->getOption('key') и создать файл настроек
modxapp extract-settings my-package
```

## 7. Сборка

```bash
modxapp build my-package
```

Результат — файл `core/packages/my-package-1.0.0-alpha.transport.zip`, готовый для установки на любой MODX 3.

### Проверки перед сборкой

Перед сборкой автоматически запускаются PHPStan, PHP CS Fixer и ESLint (если установлены). Чтобы пропустить:

```bash
modxapp build my-package --no-check
```

## 8. Установка на MODX

Скопируйте `transport.zip` в `core/packages/` на сайте MODX и установите через менеджер пакетов в админке.

Или если Package Builder установлен на сервере с MODX:

```bash
modxapp build my-package --install
```

## Что дальше

- [Сценарии работы](workflows.md) — headless, разработка с MODX, смешанный режим
- [Конфигурация пакета](configuration.md) — версия, элементы, настройки сборки
- [Кастомные шаблоны](commands/templates.md) — свои шаблоны для разных типов пакетов
- [Инструменты](tools.md) — PHPStan, CS Fixer, ESLint, настройка IDE
- [Резолверы](commands/build.md#процесс-сборки) — скрипты установки/обновления/удаления
- [Шифрование](encryption.md) — защита платных пакетов
